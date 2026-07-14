<?php

namespace App\Services\Receivables;

use App\Enums\CreditLimitStatus;
use App\Enums\CreditNoteStatus;
use App\Enums\CustomerStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReceivableEntryType;
use App\Enums\ReceivableStatus;
use App\Exceptions\ServiceException;
use App\Models\CashShift;
use App\Models\CollectionNote;
use App\Models\CreditLimit;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PosSale;
use App\Models\Receivable;
use App\Models\ReceivableEntry;
use App\Models\ReceivablePayment;
use App\Models\User;
use App\Services\Organization\DocumentNumberService;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReceivableService
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    public function assertCanUseCredit(Customer $customer, string|int|float $amount): void
    {
        $amount = Decimal::normalize($amount, 2);
        $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
        $limit = $this->creditLimitFor($customer, true);

        if (! $customer->is_active || $customer->getRawOriginal('account_status') !== CustomerStatus::ACTIVE->value) {
            throw ServiceException::validation('Status kredit pelanggan tidak aktif atau sedang diblokir.');
        }

        if ($this->creditLimitStatus($limit) !== CreditLimitStatus::ACTIVE) {
            throw ServiceException::validation('Limit kredit pelanggan sedang diblokir atau menunggu approval.');
        }

        if ($limit->max_overdue_days > 0 && $this->hasOverdueBeyond($customer, (int) $limit->max_overdue_days)) {
            throw ServiceException::validation('Pelanggan memiliki tunggakan melewati batas hari overdue.');
        }

        $available = Decimal::sub((string) $limit->credit_limit, (string) $limit->current_balance, 2);
        if (Decimal::compare($amount, $available, 2) > 0) {
            throw ServiceException::validation('Transaksi melebihi sisa limit kredit pelanggan.');
        }
    }

    public function createFromInvoice(Invoice $invoice, User $actor): Receivable
    {
        return DB::transaction(function () use ($invoice, $actor): Receivable {
            $invoice = Invoice::query()->with(['customer', 'order'])->lockForUpdate()->findOrFail($invoice->id);
            $existing = Receivable::query()->where('invoice_id', $invoice->id)->first();
            if ($existing instanceof Receivable) {
                return $existing->fresh(['entries', 'customer']);
            }

            $customer = $this->requireCustomer($invoice->customer);
            $this->assertCanUseCredit($customer, (string) $invoice->total_amount);
            $receivable = Receivable::query()->create([
                'number' => $this->numbers->next('receivable'),
                'customer_id' => $customer->id,
                'work_location_id' => $invoice->order?->reservations()->value('work_location_id'),
                'invoice_id' => $invoice->id,
                'source_type' => 'invoice',
                'source_id' => $invoice->id,
                'source_no' => $invoice->number,
                'channel' => 'warehouse',
                'issue_date' => $invoice->issue_date ?? now()->toDateString(),
                'due_date' => $invoice->due_date ?? now()->addDays((int) $customer->payment_term_days)->toDateString(),
                'principal_amount' => $invoice->total_amount,
                'outstanding_amount' => $invoice->total_amount,
                'aging_bucket' => $this->agingBucket($invoice->due_date ?? now()),
                'status' => $this->statusForBalance((string) $invoice->total_amount, $invoice->due_date ?? now()),
            ]);

            $this->entry($receivable, ReceivableEntryType::INVOICE, (string) $invoice->total_amount, '0.00', (string) $invoice->total_amount, 'invoice', $invoice->id, $invoice->number, $actor, 'Invoice diterbitkan.');
            $this->syncCustomerBalance($customer);

            return $receivable->fresh(['entries', 'customer']);
        });
    }

    public function createFromPosSale(PosSale $sale, User $actor): ?Receivable
    {
        return DB::transaction(function () use ($sale, $actor): ?Receivable {
            $sale = PosSale::query()->with(['payments', 'customer'])->lockForUpdate()->findOrFail($sale->id);
            $creditAmount = Decimal::normalize($sale->payments()->where('method', PaymentMethod::CREDIT->value)->sum('amount'), 2);
            if (Decimal::compare($creditAmount, '0', 2) <= 0) {
                return null;
            }

            $customer = $this->requireCustomer($sale->customer);
            $this->assertCanUseCredit($customer, $creditAmount);
            $existing = Receivable::query()->where('pos_sale_id', $sale->id)->first();
            if ($existing instanceof Receivable) {
                return $existing;
            }

            $dueDate = now()->addDays((int) $customer->payment_term_days);
            $receivable = Receivable::query()->create([
                'number' => $this->numbers->next('receivable', $sale->workLocation),
                'customer_id' => $customer->id,
                'work_location_id' => $sale->work_location_id,
                'pos_sale_id' => $sale->id,
                'source_type' => 'pos_sale',
                'source_id' => $sale->id,
                'source_no' => $sale->number,
                'channel' => 'retail',
                'issue_date' => now()->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'principal_amount' => $creditAmount,
                'outstanding_amount' => $creditAmount,
                'aging_bucket' => $this->agingBucket($dueDate),
                'status' => $this->statusForBalance($creditAmount, $dueDate),
            ]);

            $this->entry($receivable, ReceivableEntryType::INVOICE, $creditAmount, '0.00', $creditAmount, 'pos_sale', $sale->id, $sale->number, $actor, 'POS kredit toko internal.');
            $this->syncCustomerBalance($customer);

            return $receivable->fresh(['entries', 'customer']);
        });
    }

    /**
     * @param  array<int, string|int|float>  $allocations
     * @param  array<string, mixed>  $data
     */
    public function recordPayment(Customer $customer, array $allocations, array $data, User $actor, ?CashShift $shift = null): ReceivablePayment
    {
        return DB::transaction(function () use ($customer, $allocations, $data, $actor, $shift): ReceivablePayment {
            $idempotencyKey = filled($data['idempotency_key'] ?? null) ? (string) $data['idempotency_key'] : null;
            if ($idempotencyKey !== null) {
                $existing = ReceivablePayment::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing instanceof ReceivablePayment) {
                    return $existing->fresh(['allocations.receivable', 'customer']);
                }
            }

            $total = '0.00';
            foreach ($allocations as $amount) {
                $total = Decimal::add($total, Decimal::normalize($amount, 2), 2);
            }
            if (Decimal::compare($total, '0', 2) <= 0) {
                throw ServiceException::validation('Total alokasi pembayaran harus lebih besar dari nol.');
            }

            $payment = ReceivablePayment::query()->create([
                'number' => $this->numbers->next('payment'),
                'customer_id' => $customer->id,
                'cash_shift_id' => $shift?->id,
                'received_by' => $actor->id,
                'method' => $data['method'],
                'amount' => $total,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'reference_no' => $data['reference_no'] ?? null,
                'proof_path' => $data['proof_path'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($allocations as $receivableId => $amount) {
                $amount = Decimal::normalize($amount, 2);
                if (Decimal::compare($amount, '0', 2) <= 0) {
                    continue;
                }

                $receivable = Receivable::query()->lockForUpdate()->findOrFail((int) $receivableId);
                if ((int) $receivable->customer_id !== (int) $customer->id) {
                    throw ServiceException::validation('Alokasi piutang tidak sesuai pelanggan.');
                }
                if (Decimal::compare($amount, (string) $receivable->outstanding_amount, 2) > 0) {
                    throw ServiceException::validation('Alokasi pembayaran melebihi saldo piutang.');
                }

                $payment->allocations()->create(['receivable_id' => $receivable->id, 'amount' => $amount]);
                $this->applyDelta($receivable, Decimal::sub('0', $amount, 2), ReceivableEntryType::PAYMENT, 'receivable_payment', $payment->id, $payment->number, $actor, 'Pembayaran piutang.');

                if ($receivable->invoice instanceof Invoice) {
                    $this->syncInvoiceFromReceivable($receivable->fresh());
                }
            }

            if ($shift instanceof CashShift && $data['method'] === PaymentMethod::CASH->value) {
                $shift->forceFill([
                    'expected_cash_amount' => Decimal::add((string) $shift->expected_cash_amount, $total, 2),
                ])->save();
            }

            $this->syncCustomerBalance($customer);

            return $payment->fresh(['allocations.receivable', 'customer']);
        });
    }

    public function applyExternalPayment(Receivable $receivable, string $amount, string $sourceType, int $sourceId, string $sourceNo, User $actor): Receivable
    {
        return DB::transaction(function () use ($receivable, $amount, $sourceType, $sourceId, $sourceNo, $actor): Receivable {
            $receivable = Receivable::query()->with(['customer', 'invoice'])->lockForUpdate()->findOrFail($receivable->id);
            $amount = Decimal::normalize($amount, 2);
            if (Decimal::compare($amount, '0', 2) <= 0) {
                throw ServiceException::validation('Nominal pembayaran harus lebih besar dari nol.');
            }
            if (Decimal::compare($amount, (string) $receivable->outstanding_amount, 2) > 0) {
                throw ServiceException::validation('Pembayaran melebihi saldo piutang.');
            }

            $this->applyDelta($receivable, Decimal::sub('0', $amount, 2), ReceivableEntryType::PAYMENT, $sourceType, $sourceId, $sourceNo, $actor, 'Pembayaran terverifikasi.');
            $receivable = $receivable->fresh(['customer', 'invoice']);
            $this->syncCustomerBalance($this->requireCustomer($receivable->customer));
            $this->syncInvoiceFromReceivable($receivable);

            return $receivable;
        });
    }

    public function createCreditNote(Receivable $receivable, string $amount, string $reason, User $actor): CreditNote
    {
        return DB::transaction(function () use ($receivable, $amount, $reason, $actor): CreditNote {
            $receivable = Receivable::query()->lockForUpdate()->findOrFail($receivable->id);
            $amount = Decimal::normalize($amount, 2);
            if (Decimal::compare($amount, '0', 2) <= 0 || Decimal::compare($amount, (string) $receivable->outstanding_amount, 2) > 0) {
                throw ServiceException::validation('Nominal credit note tidak valid.');
            }

            return CreditNote::query()->create([
                'number' => $this->numbers->next('credit_note'),
                'receivable_id' => $receivable->id,
                'customer_id' => $receivable->customer_id,
                'amount' => $amount,
                'status' => CreditNoteStatus::PENDING,
                'reason' => $reason,
                'created_by' => $actor->id,
            ]);
        });
    }

    public function approveCreditNote(CreditNote $creditNote, User $actor, ?string $note = null): CreditNote
    {
        return DB::transaction(function () use ($creditNote, $actor, $note): CreditNote {
            $creditNote = CreditNote::query()->with('receivable')->lockForUpdate()->findOrFail($creditNote->id);
            if ($this->creditNoteStatus($creditNote) === CreditNoteStatus::APPROVED) {
                return $creditNote;
            }
            if ($this->creditNoteStatus($creditNote) !== CreditNoteStatus::PENDING) {
                throw ServiceException::validation('Credit note tidak dapat diproses pada status saat ini.');
            }

            $this->applyDelta($creditNote->receivable, Decimal::sub('0', (string) $creditNote->amount, 2), ReceivableEntryType::CREDIT_NOTE, 'credit_note', $creditNote->id, $creditNote->number, $actor, $creditNote->reason);
            $creditNote->forceFill(['status' => CreditNoteStatus::APPROVED, 'approved_by' => $actor->id, 'approved_at' => now(), 'approval_note' => $note])->save();
            $this->syncCustomerBalance($creditNote->receivable->customer);
            if ($creditNote->receivable->invoice instanceof Invoice) {
                $this->syncInvoiceFromReceivable($creditNote->receivable->fresh());
            }

            return $creditNote->fresh(['receivable']);
        });
    }

    /** @param array<string, mixed> $data */
    public function addCollectionNote(Customer $customer, ?Receivable $receivable, array $data, User $actor): CollectionNote
    {
        return CollectionNote::query()->create([
            'customer_id' => $customer->id,
            'receivable_id' => $receivable?->id,
            'created_by' => $actor->id,
            'channel' => $data['channel'] ?? 'manual',
            'contact_person' => $data['contact_person'] ?? null,
            'note' => $data['note'],
            'next_follow_up_date' => $data['next_follow_up_date'] ?? null,
            'delivery_status' => $data['delivery_status'] ?? 'draft',
        ]);
    }

    public function refreshAging(?Carbon $asOf = null): int
    {
        $asOf ??= now();
        $count = 0;
        Receivable::query()->whereNotIn('status', [ReceivableStatus::PAID->value, ReceivableStatus::CANCELLED->value])->orderBy('id')->get()->each(function (Receivable $receivable) use ($asOf, &$count): void {
            $receivable->forceFill([
                'aging_bucket' => $this->agingBucket($receivable->due_date ?? $asOf, $asOf),
                'status' => $this->statusForBalance((string) $receivable->outstanding_amount, $receivable->due_date ?? $asOf, $asOf, (string) $receivable->paid_amount),
            ])->save();
            $count++;
        });

        return $count;
    }

    public function agingBucket(Carbon|string $dueDate, ?Carbon $asOf = null): string
    {
        $asOf ??= now();
        $due = $dueDate instanceof Carbon ? $dueDate : Carbon::parse($dueDate);
        $days = $due->startOfDay()->diffInDays($asOf->copy()->startOfDay(), false);

        return match (true) {
            $days <= 0 => 'not_due',
            $days <= 7 => '1_7',
            $days <= 30 => '8_30',
            $days <= 60 => '31_60',
            default => 'over_60',
        };
    }

    public function reconcileCustomerBalance(Customer $customer): string
    {
        return Decimal::normalize(Receivable::query()->where('customer_id', $customer->id)->sum('outstanding_amount'), 2);
    }

    private function applyDelta(Receivable $receivable, string $delta, ReceivableEntryType $type, string $sourceType, int $sourceId, string $sourceNo, User $actor, ?string $notes = null): void
    {
        $receivable = Receivable::query()->lockForUpdate()->findOrFail($receivable->id);
        $before = (string) $receivable->outstanding_amount;
        $after = Decimal::add($before, $delta, 2);
        if (Decimal::compare($after, '0', 2) < 0) {
            throw ServiceException::validation('Saldo piutang tidak boleh negatif.');
        }

        $paid = in_array($type, [ReceivableEntryType::PAYMENT, ReceivableEntryType::CREDIT_NOTE], true)
            ? Decimal::add((string) $receivable->paid_amount, Decimal::sub('0', $delta, 2), 2)
            : (string) $receivable->paid_amount;
        $adjustment = $type === ReceivableEntryType::CREDIT_NOTE
            ? Decimal::add((string) $receivable->adjustment_amount, Decimal::sub('0', $delta, 2), 2)
            : (string) $receivable->adjustment_amount;

        $receivable->forceFill([
            'paid_amount' => $paid,
            'adjustment_amount' => $adjustment,
            'outstanding_amount' => $after,
            'aging_bucket' => $this->agingBucket($receivable->due_date ?? now()),
            'status' => $this->statusForBalance($after, $receivable->due_date ?? now(), null, $paid),
        ])->save();

        $this->entry($receivable, $type, $delta, $before, $after, $sourceType, $sourceId, $sourceNo, $actor, $notes);
    }

    private function entry(Receivable $receivable, ReceivableEntryType $type, string $amount, string $before, string $after, ?string $sourceType, ?int $sourceId, ?string $sourceNo, ?User $actor, ?string $notes = null): ReceivableEntry
    {
        return ReceivableEntry::query()->create([
            'receivable_id' => $receivable->id,
            'customer_id' => $receivable->customer_id,
            'entry_type' => $type,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_no' => $sourceNo,
            'actor_user_id' => $actor?->id,
            'notes' => $notes,
            'occurred_at' => now(),
        ]);
    }

    private function syncCustomerBalance(Customer $customer): void
    {
        $balance = $this->reconcileCustomerBalance($customer);
        $customer->forceFill(['receivable_balance' => $balance])->save();
        $limit = $this->creditLimitFor($customer, false);
        $limit->forceFill(['current_balance' => $balance])->save();
    }

    private function syncInvoiceFromReceivable(Receivable $receivable): void
    {
        if (! $receivable->invoice instanceof Invoice) {
            return;
        }

        $paid = Decimal::sub((string) $receivable->principal_amount, (string) $receivable->outstanding_amount, 2);
        $receivable->invoice->forceFill([
            'paid_amount' => $paid,
            'outstanding_amount' => $receivable->outstanding_amount,
            'status' => Decimal::compare((string) $receivable->outstanding_amount, '0', 2) <= 0 ? 'paid' : (Decimal::compare($paid, '0', 2) > 0 ? 'partial' : 'issued'),
            'paid_at' => Decimal::compare((string) $receivable->outstanding_amount, '0', 2) <= 0 ? now() : $receivable->invoice->paid_at,
        ])->save();
    }

    private function statusForBalance(string $balance, Carbon|string $dueDate, ?Carbon $asOf = null, string $paid = '0.00'): ReceivableStatus
    {
        if (Decimal::compare($balance, '0', 2) <= 0) {
            return ReceivableStatus::PAID;
        }

        $asOf ??= now();
        $due = $dueDate instanceof Carbon ? $dueDate : Carbon::parse($dueDate);

        if ($due->isBefore($asOf->copy()->startOfDay())) {
            return ReceivableStatus::OVERDUE;
        }

        return Decimal::compare($paid, '0', 2) > 0 ? ReceivableStatus::PARTIAL : ReceivableStatus::OPEN;
    }

    private function hasOverdueBeyond(Customer $customer, int $days): bool
    {
        return Receivable::query()
            ->where('customer_id', $customer->id)
            ->where('outstanding_amount', '>', 0)
            ->whereDate('due_date', '<', now()->subDays($days)->toDateString())
            ->exists();
    }

    private function creditLimitFor(Customer $customer, bool $lock): CreditLimit
    {
        CreditLimit::query()->firstOrCreate(
            ['customer_id' => $customer->id],
            ['credit_limit' => $customer->credit_limit, 'payment_term_days' => $customer->payment_term_days, 'current_balance' => $customer->receivable_balance, 'effective_from' => now()->toDateString()],
        );

        $query = CreditLimit::query()->where('customer_id', $customer->id);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private function creditLimitStatus(CreditLimit $limit): CreditLimitStatus
    {
        return CreditLimitStatus::from((string) $limit->getRawOriginal('status'));
    }

    private function creditNoteStatus(CreditNote $creditNote): CreditNoteStatus
    {
        return CreditNoteStatus::from((string) $creditNote->getRawOriginal('status'));
    }

    private function requireCustomer(?Customer $customer): Customer
    {
        if (! $customer instanceof Customer) {
            throw ServiceException::validation('Pelanggan wajib dipilih untuk transaksi tempo.');
        }

        return $customer;
    }
}
