<?php

namespace App\Services\Retail;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentMethod;
use App\Enums\PosSaleStatus;
use App\Exceptions\ServiceException;
use App\Models\Branch;
use App\Models\CashCount;
use App\Models\CashShift;
use App\Models\PosReturn;
use App\Models\PosSale;
use App\Models\SalePayment;
use App\Models\ShiftApproval;
use App\Models\ShiftExpense;
use App\Models\User;
use App\Services\Organization\DocumentNumberService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class CashShiftService
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    /** @param array<string, mixed> $data */
    public function open(array $data, User $cashier): CashShift
    {
        return DB::transaction(function () use ($data, $cashier): CashShift {
            $branch = $this->branch((int) $data['branch_id'], $cashier);
            $active = CashShift::query()
                ->where('branch_id', $branch->id)
                ->where('cashier_user_id', $cashier->id)
                ->where('status', CashShiftStatus::OPEN->value)
                ->lockForUpdate()
                ->exists();

            if ($active) {
                throw ServiceException::validation('Kasir masih memiliki shift aktif pada cabang ini.');
            }

            return CashShift::query()->create([
                'number' => $this->numbers->next('payment', $branch->workLocation),
                'branch_id' => $branch->id,
                'work_location_id' => $branch->work_location_id,
                'cashier_user_id' => $cashier->id,
                'opened_by' => $cashier->id,
                'status' => CashShiftStatus::OPEN,
                'terminal_code' => $data['terminal_code'] ?? null,
                'opening_cash_amount' => $data['opening_cash_amount'] ?? 0,
                'expected_cash_amount' => $data['opening_cash_amount'] ?? 0,
                'discrepancy_threshold_amount' => $data['discrepancy_threshold_amount'] ?? 50000,
                'opened_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    public function current(User $user): ?CashShift
    {
        return CashShift::query()
            ->with(['branch', 'cashier'])
            ->where('cashier_user_id', $user->id)
            ->whereIn('status', [CashShiftStatus::OPEN->value, CashShiftStatus::REJECTED->value])
            ->latest('opened_at')
            ->first();
    }

    /** @param array<string, mixed> $data */
    public function addExpense(CashShift $shift, array $data, User $actor): ShiftExpense
    {
        return DB::transaction(function () use ($shift, $data, $actor): ShiftExpense {
            $shift = CashShift::query()->lockForUpdate()->findOrFail($shift->id);
            if ($shift->status !== CashShiftStatus::OPEN) {
                throw ServiceException::validation('Pengeluaran hanya dapat dicatat pada shift terbuka.');
            }
            if (! $actor->canAccessWorkLocation((int) $shift->work_location_id)) {
                throw ServiceException::validation('Anda tidak memiliki akses ke cabang shift ini.');
            }
            if (Decimal::compare($data['amount'], '1000000', 2) > 0 && ! $actor->can('cash_shifts.approve')) {
                throw ServiceException::validation('Pengeluaran di atas batas kasir membutuhkan supervisor.');
            }

            return ShiftExpense::query()->create([
                'cash_shift_id' => $shift->id,
                'branch_id' => $shift->branch_id,
                'work_location_id' => $shift->work_location_id,
                'created_by' => $actor->id,
                'category' => $data['category'],
                'payment_method' => $data['payment_method'] ?? PaymentMethod::CASH->value,
                'amount' => $data['amount'],
                'notes' => $data['notes'] ?? null,
                'proof_path' => $data['proof_path'] ?? null,
                'spent_at' => $data['spent_at'] ?? now(),
            ]);
        });
    }

    /** @return array<string, string|array<string, string>> */
    public function summary(CashShift $shift): array
    {
        $cashSales = $this->paymentTotal($shift, PaymentMethod::CASH->value);
        $transferSales = $this->paymentTotal($shift, PaymentMethod::BANK_TRANSFER->value);
        $qrisSales = $this->paymentTotal($shift, PaymentMethod::QRIS->value);
        $manualSales = $this->paymentTotal($shift, PaymentMethod::MANUAL->value);
        $creditSales = $this->paymentTotal($shift, PaymentMethod::CREDIT->value);
        $expenses = $this->expenseTotal($shift);
        $refunds = $this->refundTotal($shift);
        $expectedCash = Decimal::sub(Decimal::add((string) $shift->opening_cash_amount, $cashSales, 2), Decimal::add($expenses, $refunds, 2), 2);
        $actualCash = Decimal::normalize($shift->actual_cash_amount ?? 0, 2);

        return [
            'opening_cash' => (string) $shift->opening_cash_amount,
            'cash_sales' => $cashSales,
            'transfer_sales' => $transferSales,
            'qris_sales' => $qrisSales,
            'manual_sales' => $manualSales,
            'non_cash_sales' => Decimal::add(Decimal::add($transferSales, $qrisSales, 2), $manualSales, 2),
            'receivable_sales' => $creditSales,
            'refunds' => $refunds,
            'expenses' => $expenses,
            'expected_cash' => $expectedCash,
            'actual_cash' => $actualCash,
            'difference' => Decimal::sub($actualCash, $expectedCash, 2),
            'sales_count' => (string) PosSale::query()->where('cash_shift_id', $shift->id)->whereIn('status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value])->count(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function submitClosing(CashShift $shift, array $data, User $cashier): CashShift
    {
        return DB::transaction(function () use ($shift, $data, $cashier): CashShift {
            $shift = CashShift::query()->lockForUpdate()->findOrFail($shift->id);
            if ((int) $shift->cashier_user_id !== (int) $cashier->id) {
                throw ServiceException::validation('Shift hanya dapat ditutup oleh kasir pemilik shift.');
            }
            if (! in_array($shift->status, [CashShiftStatus::OPEN, CashShiftStatus::REJECTED], true)) {
                throw ServiceException::validation('Shift tidak dapat ditutup pada status saat ini.');
            }

            $actualCash = $data['actual_cash_amount'] ?? $this->cashCountTotal($data['cash_counts'] ?? []);
            $shift->forceFill(['actual_cash_amount' => $actualCash])->save();
            $summary = $this->summary($shift);
            $difference = $summary['difference'];
            if (Decimal::compare($difference, '0', 2) !== 0 && empty($data['discrepancy_reason'])) {
                throw ServiceException::validation('Alasan selisih wajib diisi jika kas fisik berbeda dengan expected cash.');
            }

            CashCount::query()->where('cash_shift_id', $shift->id)->delete();
            foreach ($data['cash_counts'] ?? [] as $count) {
                if (! is_array($count) || (int) ($count['quantity'] ?? 0) < 1) {
                    continue;
                }
                $amount = Decimal::mul((string) $count['denomination'], (string) $count['quantity'], 2, 0, 2);
                CashCount::query()->create([
                    'cash_shift_id' => $shift->id,
                    'denomination' => $count['denomination'],
                    'quantity' => $count['quantity'],
                    'amount' => $amount,
                ]);
            }

            $shift->forceFill([
                'status' => CashShiftStatus::CLOSING_SUBMITTED,
                'cash_sales_amount' => $summary['cash_sales'],
                'non_cash_sales_amount' => $summary['non_cash_sales'],
                'refund_amount' => $summary['refunds'],
                'expense_amount' => $summary['expenses'],
                'receivable_amount' => $summary['receivable_sales'],
                'expected_cash_amount' => $summary['expected_cash'],
                'difference_amount' => $difference,
                'closing_submitted_at' => now(),
                'closing_submitted_by' => $cashier->id,
                'discrepancy_reason' => $data['discrepancy_reason'] ?? null,
                'handover_notes' => $data['handover_notes'] ?? null,
            ])->save();

            $this->logApproval($shift, $cashier, 'submit', $data['handover_notes'] ?? null);

            return $shift->fresh(['branch', 'cashier', 'expenses', 'cashCounts']);
        });
    }

    public function approve(CashShift $shift, User $actor, ?string $notes = null): CashShift
    {
        return DB::transaction(function () use ($shift, $actor, $notes): CashShift {
            $shift = CashShift::query()->lockForUpdate()->findOrFail($shift->id);
            if ($shift->status !== CashShiftStatus::CLOSING_SUBMITTED) {
                throw ServiceException::validation('Hanya closing submitted yang dapat di-approve.');
            }
            if (! $actor->canAccessWorkLocation((int) $shift->work_location_id)) {
                throw ServiceException::validation('Anda tidak memiliki akses ke cabang shift ini.');
            }

            $shift->forceFill([
                'status' => CashShiftStatus::CLOSED,
                'closed_by' => $actor->id,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'closed_at' => now(),
                'approval_notes' => $notes,
            ])->save();
            $this->logApproval($shift, $actor, 'approve', $notes);

            return $shift->fresh(['branch', 'cashier']);
        });
    }

    public function reject(CashShift $shift, User $actor, string $notes): CashShift
    {
        return DB::transaction(function () use ($shift, $actor, $notes): CashShift {
            $shift = CashShift::query()->lockForUpdate()->findOrFail($shift->id);
            if ($shift->status !== CashShiftStatus::CLOSING_SUBMITTED) {
                throw ServiceException::validation('Hanya closing submitted yang dapat ditolak.');
            }
            if (! $actor->canAccessWorkLocation((int) $shift->work_location_id)) {
                throw ServiceException::validation('Anda tidak memiliki akses ke cabang shift ini.');
            }

            $shift->forceFill([
                'status' => CashShiftStatus::REJECTED,
                'rejected_at' => now(),
                'approval_notes' => $notes,
            ])->save();
            $this->logApproval($shift, $actor, 'reject', $notes);

            return $shift->fresh(['branch', 'cashier']);
        });
    }

    private function branch(int $branchId, User $user): Branch
    {
        $branch = Branch::query()->with('workLocation')->where('is_active', true)->findOrFail($branchId);
        if ($branch->work_location_id === null || ! $user->canAccessWorkLocation((int) $branch->work_location_id)) {
            throw ServiceException::validation('Anda tidak memiliki akses ke cabang ini.');
        }

        return $branch;
    }

    private function paymentTotal(CashShift $shift, string $method): string
    {
        $total = SalePayment::query()
            ->whereHas('sale', fn ($query) => $query->where('cash_shift_id', $shift->id)->whereNot('status', PosSaleStatus::VOID_APPROVED->value))
            ->where('method', $method)
            ->sum('amount');

        return Decimal::normalize($total, 2);
    }

    private function expenseTotal(CashShift $shift): string
    {
        return Decimal::normalize(ShiftExpense::query()->where('cash_shift_id', $shift->id)->where('payment_method', PaymentMethod::CASH->value)->sum('amount'), 2);
    }

    private function refundTotal(CashShift $shift): string
    {
        return Decimal::normalize(PosReturn::query()
            ->whereHas('sale', fn ($query) => $query->where('cash_shift_id', $shift->id))
            ->sum('refund_amount'), 2);
    }

    private function cashCountTotal(mixed $counts): string
    {
        $total = '0.00';
        if (! is_array($counts)) {
            return $total;
        }
        foreach ($counts as $count) {
            if (! is_array($count)) {
                continue;
            }
            $total = Decimal::add($total, Decimal::mul((string) ($count['denomination'] ?? 0), (string) ($count['quantity'] ?? 0), 2, 0, 2), 2);
        }

        return $total;
    }

    private function logApproval(CashShift $shift, User $actor, string $action, ?string $notes = null): void
    {
        ShiftApproval::query()->create([
            'cash_shift_id' => $shift->id,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'notes' => $notes,
            'snapshot' => $this->summary($shift),
        ]);
    }
}
