<?php

namespace App\Services\Tax;

use App\Enums\InvoiceStatus;
use App\Enums\PosReturnStatus;
use App\Enums\PosSaleStatus;
use App\Enums\TaxDirection;
use App\Enums\TaxDocumentStatus;
use App\Enums\TaxPeriodStatus;
use App\Enums\TaxType;
use App\Exceptions\ServiceException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PosReturn;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\TaxDocument;
use App\Models\TaxPeriod;
use App\Models\TaxProfile;
use App\Models\TaxRule;
use App\Models\User;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TaxComplianceService
{
    /**
     * @return array{enabled: bool, rule: ?TaxRule, dpp_amount: string, tax_rate: string, dpp_factor: string, tax_amount: string, luxury_tax_amount: string, total_amount: string}
     */
    public function calculate(Product $product, string|int|float $netAmount, mixed $at = null): array
    {
        $netAmount = Decimal::normalize($netAmount, 2);
        $profile = TaxProfile::query()->where('scope_key', 'company')->first();
        $date = CarbonImmutable::parse($at ?? now())->toDateString();

        if (! $profile instanceof TaxProfile || ! $profile->calculation_enabled || ! $profile->is_pkp || ! $product->is_taxable) {
            return $this->emptyCalculation($netAmount);
        }

        $ruleId = $product->tax_rule_id ?: $profile->default_output_tax_rule_id;
        $rule = TaxRule::query()
            ->whereKey($ruleId)
            ->where('is_active', true)
            ->whereIn('direction', ['output', 'both'])
            ->where('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $date))
            ->first();

        if (! $rule instanceof TaxRule) {
            return $this->emptyCalculation($netAmount);
        }

        $dpp = Decimal::mul($netAmount, (string) $rule->dpp_factor, 2, 8, 2);
        $tax = Decimal::mul($dpp, bcdiv((string) $rule->rate, '100', 8), 2, 8, 2);
        $luxuryTax = Decimal::mul($dpp, bcdiv((string) $rule->luxury_tax_rate, '100', 8), 2, 8, 2);

        return [
            'enabled' => true,
            'rule' => $rule,
            'dpp_amount' => $dpp,
            'tax_rate' => (string) $rule->rate,
            'dpp_factor' => (string) $rule->dpp_factor,
            'tax_amount' => $tax,
            'luxury_tax_amount' => $luxuryTax,
            'total_amount' => Decimal::add($netAmount, Decimal::add($tax, $luxuryTax, 2), 2),
        ];
    }

    public function ensurePeriod(int $month, int $year): TaxPeriod
    {
        $period = TaxPeriod::query()->firstOrCreate(
            ['month' => $month, 'year' => $year],
            ['status' => TaxPeriodStatus::OPEN],
        );

        return $period->refresh();
    }

    public function syncPeriod(TaxPeriod $period, User $actor): TaxPeriod
    {
        $this->assertMutable($period);
        [$start, $end] = $this->periodBounds($period);

        DB::transaction(function () use ($period, $actor, $start, $end): void {
            Invoice::query()
                ->with(['customer', 'items.product'])
                ->whereIn('status', [InvoiceStatus::ISSUED->value, InvoiceStatus::PARTIAL->value, InvoiceStatus::PAID->value, InvoiceStatus::OVERDUE->value])
                ->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
                ->each(fn (Invoice $invoice) => $this->syncInvoice($period, $invoice, $actor));

            PosSale::query()
                ->with(['customer', 'items.product'])
                ->whereIn('status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value])
                ->whereBetween('completed_at', [$start, $end])
                ->each(fn (PosSale $sale) => $this->syncPosSale($period, $sale, $actor));

            PosReturn::query()
                ->with(['sale.customer', 'items'])
                ->where('status', PosReturnStatus::COMPLETED->value)
                ->whereBetween('completed_at', [$start, $end])
                ->each(fn (PosReturn $return) => $this->syncPosReturn($period, $return, $actor));

            $this->recalculate($period);
        });

        activity()->causedBy($actor)->performedOn($period)->log('tax.period.synced');

        return $period->fresh('documents');
    }

    /** @param array<string, mixed> $data */
    public function storeManualDocument(array $data, User $actor): TaxDocument
    {
        $date = CarbonImmutable::parse($data['tax_date']);
        $period = $this->ensurePeriod($date->month, $date->year);
        $this->assertMutable($period);

        return DB::transaction(function () use ($data, $actor, $period): TaxDocument {
            $rule = isset($data['tax_rule_id']) ? TaxRule::query()->find($data['tax_rule_id']) : null;
            $dpp = Decimal::normalize($data['dpp_amount'], 2);
            $ruleRate = $rule instanceof TaxRule ? $rule->rate : 0;
            $ruleFactor = $rule instanceof TaxRule ? $rule->dpp_factor : 1;
            $ruleCreditable = $rule instanceof TaxRule ? $rule->is_creditable : true;
            $rate = Decimal::normalize($data['tax_rate'] ?? $ruleRate, 4);
            $factor = Decimal::normalize($data['dpp_factor'] ?? $ruleFactor, 8);
            $taxAmount = isset($data['tax_amount'])
                ? Decimal::normalize($data['tax_amount'], 2)
                : Decimal::mul(Decimal::mul($dpp, $factor, 2, 8, 2), bcdiv($rate, '100', 8), 2, 8, 2);
            $withholding = Decimal::normalize($data['withholding_tax_amount'] ?? 0, 2);

            $document = TaxDocument::query()->create([
                ...$data,
                'tax_period_id' => $period->id,
                'source_key' => 'manual:'.str()->uuid(),
                'tax_rate' => $rate,
                'dpp_factor' => $factor,
                'tax_amount' => $taxAmount,
                'withholding_tax_amount' => $withholding,
                'total_amount' => Decimal::add($dpp, $taxAmount, 2),
                'is_creditable' => (bool) ($data['is_creditable'] ?? $ruleCreditable),
                'status' => TaxDocumentStatus::POSTED,
                'reconciliation_status' => 'unmatched',
                'created_by' => $actor->id,
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            $this->recalculate($period);
            activity()->causedBy($actor)->performedOn($document)->log('tax.document.created');

            return $document;
        });
    }

    public function reconcile(TaxDocument $document, User $actor, ?string $coretaxReference, ?string $notes): TaxDocument
    {
        $this->assertMutable($document->period);
        $document->forceFill([
            'status' => TaxDocumentStatus::RECONCILED,
            'reconciliation_status' => 'matched',
            'coretax_reference' => $coretaxReference,
            'reconciliation_notes' => $notes,
            'reconciled_by' => $actor->id,
            'reconciled_at' => now(),
        ])->save();

        activity()->causedBy($actor)->performedOn($document)->log('tax.document.reconciled');

        return $document->fresh();
    }

    public function reverse(TaxDocument $document, User $actor, string $reason): TaxDocument
    {
        $this->assertMutable($document->period);
        if ($document->status === TaxDocumentStatus::REVERSED) {
            return $document;
        }

        return DB::transaction(function () use ($document, $actor, $reason): TaxDocument {
            $document->forceFill([
                'status' => TaxDocumentStatus::REVERSED,
                'reconciliation_status' => 'reversed',
                'reversed_by' => $actor->id,
                'reversed_at' => now(),
                'reconciliation_notes' => $reason,
            ])->save();
            $this->recalculate($document->period);
            activity()->causedBy($actor)->performedOn($document)->withProperties(['reason' => $reason])->log('tax.document.reversed');

            return $document;
        });
    }

    /** @param array<string, mixed> $data */
    public function transition(TaxPeriod $period, string $action, User $actor, array $data = []): TaxPeriod
    {
        $current = $period->status;
        $updates = match ($action) {
            'review' => $current === TaxPeriodStatus::OPEN
                ? ['status' => TaxPeriodStatus::REVIEWED, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]
                : null,
            'approve' => $current === TaxPeriodStatus::REVIEWED
                ? ['status' => TaxPeriodStatus::APPROVED, 'approved_by' => $actor->id, 'approved_at' => now()]
                : null,
            'report' => $current === TaxPeriodStatus::APPROVED
                ? ['status' => TaxPeriodStatus::REPORTED, 'reported_by' => $actor->id, 'reported_at' => now(), 'filing_reference' => $data['filing_reference'] ?? null]
                : null,
            'pay' => $current === TaxPeriodStatus::REPORTED
                ? ['status' => TaxPeriodStatus::PAID, 'paid_by' => $actor->id, 'paid_at' => now(), 'payment_reference' => $data['payment_reference'] ?? null]
                : null,
            'lock' => in_array($current, [TaxPeriodStatus::REPORTED, TaxPeriodStatus::PAID], true)
                ? ['status' => TaxPeriodStatus::LOCKED, 'locked_by' => $actor->id, 'locked_at' => now()]
                : null,
            'reopen' => $current === TaxPeriodStatus::LOCKED
                ? ['status' => TaxPeriodStatus::OPEN, 'reopened_by' => $actor->id, 'reopened_at' => now(), 'notes' => $data['notes'] ?? null]
                : null,
            default => null,
        };

        if (! is_array($updates)) {
            throw ServiceException::validation('Perubahan status masa pajak tidak sesuai urutan workflow.');
        }

        if (in_array($action, ['review', 'approve'], true)) {
            $this->recalculate($period);
        }
        $period->forceFill($updates)->save();
        activity()->causedBy($actor)->performedOn($period)->withProperties(['action' => $action])->log('tax.period.status_changed');

        return $period->fresh();
    }

    public function recalculate(TaxPeriod $period): TaxPeriod
    {
        $active = $period->documents()->whereNotIn('status', [TaxDocumentStatus::CANCELLED->value, TaxDocumentStatus::REVERSED->value]);
        $outputDpp = (string) (clone $active)->where('direction', TaxDirection::OUTPUT->value)->sum('dpp_amount');
        $outputTax = (string) (clone $active)->where('direction', TaxDirection::OUTPUT->value)->sum('tax_amount');
        $inputDpp = (string) (clone $active)->where('direction', TaxDirection::INPUT->value)->sum('dpp_amount');
        $creditable = (string) (clone $active)->where('direction', TaxDirection::INPUT->value)->where('is_creditable', true)->sum('tax_amount');
        $nonCreditable = (string) (clone $active)->where('direction', TaxDirection::INPUT->value)->where('is_creditable', false)->sum('tax_amount');
        $withholding = (string) (clone $active)->where('direction', TaxDirection::WITHHOLDING->value)->sum('withholding_tax_amount');
        $payable = Decimal::sub(Decimal::sub($outputTax, $creditable, 2), (string) $period->compensation_amount, 2);

        $period->forceFill([
            'output_dpp_amount' => $outputDpp,
            'output_tax_amount' => $outputTax,
            'input_dpp_amount' => $inputDpp,
            'creditable_input_tax_amount' => $creditable,
            'non_creditable_input_tax_amount' => $nonCreditable,
            'withholding_tax_amount' => $withholding,
            'payable_amount' => $payable,
        ])->save();

        return $period;
    }

    private function syncInvoice(TaxPeriod $period, Invoice $invoice, User $actor): void
    {
        $customer = $invoice->customer;
        $document = TaxDocument::query()->updateOrCreate(
            ['source_key' => 'invoice:'.$invoice->id],
            [
                'tax_period_id' => $period->id,
                'direction' => TaxDirection::OUTPUT,
                'tax_type' => TaxType::PPN,
                'document_type' => 'sales_invoice',
                'document_number' => $invoice->number,
                'source_type' => 'invoice',
                'source_id' => $invoice->id,
                'counterparty_type' => 'customer',
                'counterparty_id' => $invoice->customer_id,
                'counterparty_name' => $customer->business_name,
                'counterparty_tax_number' => $customer->tax_number,
                'counterparty_address' => $customer->tax_address ?: $customer->business_address,
                'issue_date' => $invoice->issue_date,
                'tax_date' => $invoice->issue_date,
                'dpp_amount' => Decimal::add(Decimal::sub((string) $invoice->subtotal_amount, (string) $invoice->discount_amount, 2), (string) $invoice->shipping_amount, 2),
                'tax_amount' => $invoice->tax_amount,
                'total_amount' => $invoice->total_amount,
                'status' => TaxDocumentStatus::POSTED,
                'created_by' => $actor->id,
                'posted_by' => $actor->id,
                'posted_at' => $invoice->issued_at ?? now(),
                'metadata' => ['source_status' => $invoice->status->value],
            ],
        );

        $document->items()->delete();
        foreach ($invoice->items as $item) {
            $net = Decimal::sub(Decimal::mul((string) $item->quantity, (string) $item->unit_price, 4, 2, 2), (string) $item->discount_amount, 2);
            $document->items()->create([
                'product_id' => $item->product_id,
                'product_code' => $item->product?->sku,
                'description' => $item->description,
                'unit_name' => $item->unit_name_snapshot,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_amount' => $item->discount_amount,
                'dpp_amount' => $net,
                'tax_amount' => $item->tax_amount,
                'line_total' => $item->line_total,
            ]);
        }
    }

    private function syncPosSale(TaxPeriod $period, PosSale $sale, User $actor): void
    {
        $customer = $sale->customer;
        $completedAt = $sale->completed_at ?? now();
        $document = TaxDocument::query()->updateOrCreate(
            ['source_key' => 'pos_sale:'.$sale->id],
            [
                'tax_period_id' => $period->id,
                'work_location_id' => $sale->work_location_id,
                'direction' => TaxDirection::OUTPUT,
                'tax_type' => TaxType::PPN,
                'document_type' => 'retail_receipt',
                'document_number' => $sale->number,
                'source_type' => 'pos_sale',
                'source_id' => $sale->id,
                'counterparty_type' => $sale->customer_id ? 'customer' : 'consumer',
                'counterparty_id' => $sale->customer_id,
                'counterparty_name' => $customer instanceof Customer ? $customer->business_name : 'Konsumen Akhir',
                'counterparty_tax_number' => $customer instanceof Customer ? $customer->tax_number : null,
                'counterparty_address' => $customer instanceof Customer ? ($customer->tax_address ?: $customer->business_address) : null,
                'issue_date' => $completedAt->toDateString(),
                'tax_date' => $completedAt->toDateString(),
                'dpp_amount' => Decimal::sub((string) $sale->subtotal_amount, (string) $sale->discount_amount, 2),
                'tax_amount' => $sale->tax_amount,
                'total_amount' => $sale->grand_total_amount,
                'status' => TaxDocumentStatus::POSTED,
                'created_by' => $actor->id,
                'posted_by' => $actor->id,
                'posted_at' => $sale->completed_at,
                'metadata' => ['source_status' => $sale->status->value],
            ],
        );

        $document->items()->delete();
        foreach ($sale->items as $item) {
            $net = Decimal::sub(Decimal::mul((string) $item->quantity, (string) $item->selected_price, 4, 2, 2), (string) $item->discount_amount, 2);
            $document->items()->create([
                'product_id' => $item->product_id,
                'product_code' => $item->sku_snapshot,
                'description' => $item->product_name_snapshot,
                'unit_name' => $item->unit_name_snapshot,
                'quantity' => $item->quantity,
                'unit_price' => $item->selected_price,
                'discount_amount' => $item->discount_amount,
                'dpp_amount' => $net,
                'tax_amount' => $item->tax_amount,
                'line_total' => $item->line_total,
            ]);
        }
    }

    private function syncPosReturn(TaxPeriod $period, PosReturn $return, User $actor): void
    {
        $sale = $return->sale;
        $customer = $sale->customer;
        $completedAt = $return->completed_at ?? now();
        $saleTotal = (string) $sale->grand_total_amount;
        $saleTax = (string) $sale->tax_amount;
        $ratio = Decimal::compare($saleTotal, '0', 2) > 0 ? bcdiv($saleTax, $saleTotal, 8) : '0';
        $tax = Decimal::mul((string) $return->refund_amount, $ratio, 2, 8, 2);
        $dpp = Decimal::sub((string) $return->refund_amount, $tax, 2);

        TaxDocument::query()->updateOrCreate(
            ['source_key' => 'pos_return:'.$return->id],
            [
                'tax_period_id' => $period->id,
                'work_location_id' => $return->work_location_id,
                'direction' => TaxDirection::OUTPUT,
                'tax_type' => TaxType::PPN,
                'document_type' => 'sales_return',
                'document_number' => $return->number,
                'source_type' => 'pos_return',
                'source_id' => $return->id,
                'counterparty_type' => $sale->customer_id ? 'customer' : 'consumer',
                'counterparty_id' => $sale->customer_id,
                'counterparty_name' => $customer instanceof Customer ? $customer->business_name : 'Konsumen Akhir',
                'issue_date' => $completedAt->toDateString(),
                'tax_date' => $completedAt->toDateString(),
                'dpp_amount' => '-'.$dpp,
                'tax_amount' => '-'.$tax,
                'total_amount' => '-'.Decimal::normalize((string) $return->refund_amount, 2),
                'status' => TaxDocumentStatus::POSTED,
                'created_by' => $actor->id,
                'posted_by' => $actor->id,
                'posted_at' => $return->completed_at,
                'metadata' => ['original_sale_id' => $return->pos_sale_id],
            ],
        );
    }

    private function assertMutable(TaxPeriod $period): void
    {
        if ($period->status === TaxPeriodStatus::LOCKED) {
            throw ServiceException::validation('Masa pajak sudah dikunci. Buka kembali periode dengan alasan sebelum melakukan perubahan.');
        }
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function periodBounds(TaxPeriod $period): array
    {
        $start = CarbonImmutable::create($period->year, $period->month, 1)->startOfMonth();

        return [$start, $start->endOfMonth()];
    }

    /** @return array{enabled: bool, rule: null, dpp_amount: string, tax_rate: string, dpp_factor: string, tax_amount: string, luxury_tax_amount: string, total_amount: string} */
    private function emptyCalculation(string $netAmount): array
    {
        return [
            'enabled' => false,
            'rule' => null,
            'dpp_amount' => $netAmount,
            'tax_rate' => '0.0000',
            'dpp_factor' => '1.00000000',
            'tax_amount' => '0.00',
            'luxury_tax_amount' => '0.00',
            'total_amount' => $netAmount,
        ];
    }
}
