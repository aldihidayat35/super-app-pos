<?php

namespace App\Services\Finance;

use App\Enums\FinancialMonthStatus;
use App\Enums\FinancialYearStatus;
use App\Enums\IncomeTaxScheme;
use App\Models\B2bOrder;
use App\Models\FinancialMonthlyClosing;
use App\Models\FinancialMonthlyEntry;
use App\Models\FinancialYear;
use App\Models\PosReturn;
use App\Models\PosSale;
use App\Models\Receivable;
use App\Models\ShiftExpense;
use App\Models\Stock;
use App\Models\User;
use App\Services\Control\AuditLogService;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnnualFinanceService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public const ACCOUNTS = [
        'sales_adjustment' => 'Penyesuaian penjualan', 'cogs_adjustment' => 'Penyesuaian HPP',
        'salary_expense' => 'Gaji', 'rent_expense' => 'Sewa', 'utilities_expense' => 'Listrik dan air',
        'other_expense' => 'Biaya lain', 'cash_bank' => 'Kas dan bank',
        'receivable_adjustment' => 'Penyesuaian piutang', 'inventory_adjustment' => 'Penyesuaian persediaan',
        'fixed_assets' => 'Aset tetap', 'other_assets' => 'Aset lain', 'accounts_payable' => 'Utang usaha',
        'tax_payable' => 'Utang pajak', 'other_liabilities' => 'Kewajiban lain',
        'paid_in_capital' => 'Modal disetor', 'retained_earnings' => 'Laba ditahan tahun sebelumnya',
    ];

    /** @param array<string, mixed> $data */
    public function createYear(array $data, User $actor): FinancialYear
    {
        return DB::transaction(function () use ($data, $actor): FinancialYear {
            $year = FinancialYear::create([
                'year' => $data['year'], 'is_historical' => (bool) ($data['is_historical'] ?? false),
                'legal_name' => $data['legal_name'] ?: config('app.name'), 'created_by' => $actor->id,
            ]);
            foreach (range(1, 12) as $month) {
                $year->months()->create(['month' => $month, 'prepared_by' => $actor->id]);
            }
            $this->audit->record('finance.year.created', 'finance_annual', $actor, $year, [], $year->only(['year', 'is_historical', 'legal_name']));

            return $year->load('months.entries');
        });
    }

    public function snapshot(FinancialMonthlyClosing $month, User $actor): FinancialMonthlyClosing
    {
        $this->assertEditable($month);
        $year = $month->financialYear;
        $start = Carbon::create($year->year, $month->month, 1, 0, 0, 0, 'Asia/Jakarta')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $pos = PosSale::query()->whereIn('status', ['completed', 'returned'])->whereBetween('completed_at', [$start, $end]);
        $b2b = B2bOrder::query()->where('status', 'completed')->whereBetween('completed_at', [$start, $end]);
        $b2bItems = (clone $b2b)->with('items')->get()->flatMap->items;
        $b2bCogs = '0.00';
        $missing = 0;
        foreach ($b2bItems as $item) {
            $hpp = $item->price_snapshot['hpp_snapshot'] ?? null;
            if ($hpp === null) {
                $missing++;

                continue;
            }
            $b2bCogs = Decimal::add($b2bCogs, Decimal::mul((string) $item->base_quantity, (string) $hpp, 4, 2, 2), 2);
        }
        $posSaleCogs = DB::table('pos_sale_items')->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->whereIn('pos_sales.status', ['completed', 'returned'])->whereBetween('pos_sales.completed_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(pos_sale_items.base_quantity * pos_sale_items.hpp_snapshot),0) total')->value('total');
        $returnedCogs = DB::table('pos_return_items')->join('pos_returns', 'pos_returns.id', '=', 'pos_return_items.pos_return_id')
            ->where('pos_returns.status', 'completed')->whereBetween('pos_returns.completed_at', [$start, $end])
            ->sum('pos_return_items.reversed_cogs_amount');
        $posCogs = Decimal::sub((string) ($posSaleCogs ?? 0), (string) $returnedCogs, 2);
        $posReturns = PosReturn::query()->where('status', 'completed')->whereBetween('completed_at', [$start, $end])->sum('refund_amount');
        $b2bReturns = DB::table('return_settlements')->join('returns', 'returns.id', '=', 'return_settlements.return_id')
            ->where('returns.reference_type', 'b2b_order')->where('returns.status', 'settled')
            ->whereIn('return_settlements.resolution', ['refund', 'reduce_receivable', 'credit_note'])
            ->whereBetween('return_settlements.settled_at', [$start, $end])->sum('return_settlements.amount');

        $month->update([
            'auto_pos_sales_amount' => (clone $pos)->sum('grand_total_amount'),
            'auto_b2b_sales_amount' => (clone $b2b)->sum('grand_total_amount'),
            'auto_returns_amount' => Decimal::add((string) $posReturns, (string) $b2bReturns, 2),
            'auto_pos_cogs_amount' => $posCogs, 'auto_b2b_cogs_amount' => $b2bCogs,
            'auto_shift_expense_amount' => ShiftExpense::query()->whereBetween('spent_at', [$start, $end])->sum('amount'),
            'auto_receivable_balance' => Receivable::query()->whereNotIn('status', ['cancelled', 'written_off'])->sum('outstanding_amount'),
            'auto_inventory_balance' => Stock::query()->join('products', 'products.id', '=', 'stocks.product_id')->selectRaw('COALESCE(SUM('.Stock::inventoryValueSql().'),0) total')->value('total') ?? 0,
            'missing_hpp_count' => $missing,
            'source_snapshot' => ['captured_at' => now('Asia/Jakarta')->toIso8601String(), 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(), 'prepared_by' => $actor->id],
            'prepared_by' => $actor->id,
        ]);
        $this->recalculate($year);
        $this->audit->record('finance.month.snapshotted', 'finance_annual', $actor, $month, [], $month->only(['month', 'source_snapshot', 'missing_hpp_count']));

        return $month->fresh('entries');
    }

    /** @param array<string, mixed> $data */
    public function addEntry(FinancialMonthlyClosing $month, array $data, User $actor): FinancialMonthlyEntry
    {
        $this->assertEditable($month);
        if (! isset(self::ACCOUNTS[$data['account_code']])) {
            throw ValidationException::withMessages(['account_code' => 'Akun laporan tidak dikenali.']);
        }
        $entry = $month->entries()->create([
            'account_code' => $data['account_code'], 'label' => self::ACCOUNTS[$data['account_code']],
            'entry_type' => 'manual', 'amount' => $data['amount'], 'reason' => $data['reason'],
            'proof_path' => $data['proof_path'] ?? null, 'created_by' => $actor->id,
        ]);
        $this->recalculate($month->financialYear);
        $this->audit->record('finance.entry.created', 'finance_annual', $actor, $entry, [], $entry->only(['account_code', 'amount', 'reason']));

        return $entry;
    }

    public function monthTransition(FinancialMonthlyClosing $month, string $action, User $actor, ?string $reason = null): void
    {
        $before = $month->status->value;
        if ($action === 'submit' && $month->status === FinancialMonthStatus::DRAFT) {
            $month->update(['status' => FinancialMonthStatus::SUBMITTED, 'submitted_by' => $actor->id, 'submitted_at' => now()]);
            $this->auditTransition('month', $month, $actor, $before, $reason);

            return;
        }
        if ($action === 'lock' && $month->status === FinancialMonthStatus::SUBMITTED) {
            if ($month->missing_hpp_count > 0) {
                throw ValidationException::withMessages(['month' => 'Bulan belum dapat dikunci karena HPP belum lengkap.']);
            }
            $month->update(['status' => FinancialMonthStatus::LOCKED, 'locked_by' => $actor->id, 'locked_at' => now()]);
            $this->auditTransition('month', $month, $actor, $before, $reason);

            return;
        }
        if ($action === 'reopen' && $month->status === FinancialMonthStatus::LOCKED && filled($reason)) {
            $month->update(['status' => FinancialMonthStatus::DRAFT, 'reopened_by' => $actor->id, 'reopened_at' => now(), 'notes' => $reason]);
            $this->auditTransition('month', $month, $actor, $before, $reason);

            return;
        }
        throw ValidationException::withMessages(['action' => 'Perubahan status bulan tidak sah atau alasan pembukaan belum diisi.']);
    }

    /** @param array<string, mixed> $data */
    public function updateYear(FinancialYear $year, array $data, User $actor): FinancialYear
    {
        if ($year->status === FinancialYearStatus::LOCKED) {
            throw ValidationException::withMessages(['year' => 'Laporan tahunan telah dikunci.']);
        }
        $before = $year->only(array_keys($data));
        $year->update($data);
        $this->audit->record('finance.year.updated', 'finance_annual', $actor, $year, $before, $year->only(array_keys($data)));

        return $this->recalculate($year);
    }

    public function yearTransition(FinancialYear $year, string $action, User $actor, ?string $reason = null): void
    {
        $this->recalculate($year);
        $before = $year->status->value;
        if ($action === 'review' && $year->status === FinancialYearStatus::DRAFT) {
            $year->update(['status' => FinancialYearStatus::REVIEWED, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
            $this->auditTransition('year', $year, $actor, $before, $reason);

            return;
        }
        if ($action === 'approve' && $year->status === FinancialYearStatus::REVIEWED) {
            $this->validateAnnualClosing($year);
            $year->update(['status' => FinancialYearStatus::APPROVED, 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->auditTransition('year', $year, $actor, $before, $reason);

            return;
        }
        if ($action === 'lock' && $year->status === FinancialYearStatus::APPROVED) {
            $this->validateAnnualClosing($year);
            $year->update(['status' => FinancialYearStatus::LOCKED, 'locked_by' => $actor->id, 'locked_at' => now()]);
            $this->auditTransition('year', $year, $actor, $before, $reason);

            return;
        }
        if ($action === 'reopen' && in_array($year->status, [FinancialYearStatus::APPROVED, FinancialYearStatus::LOCKED], true) && filled($reason)) {
            $year->update(['status' => FinancialYearStatus::DRAFT, 'reopened_by' => $actor->id, 'reopened_at' => now(), 'notes' => $reason]);
            $this->auditTransition('year', $year, $actor, $before, $reason);

            return;
        }
        throw ValidationException::withMessages(['action' => 'Perubahan status tahunan tidak sah atau alasan pembukaan belum diisi.']);
    }

    /** @return array<string, mixed> */
    public function report(FinancialYear $year): array
    {
        $year->loadMissing('months.entries');
        $months = $year->months->map(fn ($month) => $this->monthTotals($month));
        $sum = fn (string $key): string => $months->reduce(fn (string $total, array $row): string => Decimal::add($total, (string) $row[$key], 2), '0.00');
        $sales = $sum('net_sales');
        $cogs = $sum('cogs');
        $expenses = $sum('expenses');
        $profit = Decimal::sub(Decimal::sub($sales, $cogs, 2), $expenses, 2);
        $taxable = Decimal::sub(Decimal::add($profit, $year->fiscal_positive_adjustment, 2), $year->fiscal_negative_adjustment, 2);
        if (Decimal::compare($taxable, 0, 2) < 0) {
            $taxable = '0.00';
        }
        $tax = $this->incomeTax($year, $sales, $taxable);
        $taxPayable = Decimal::sub(Decimal::sub(Decimal::sub($tax, $year->tax_credit_amount, 2), $year->installment_amount, 2), $year->prior_payment_amount, 2);
        $latest = $year->months->sortByDesc('month')->first();
        $entries = $year->months->flatMap(fn (FinancialMonthlyClosing $month) => $month->entries);
        $account = fn (string $code): string => $entries->where('account_code', $code)->reduce(fn (string $total, FinancialMonthlyEntry $entry): string => Decimal::add($total, $entry->amount, 2), '0.00');
        $assets = Decimal::add(Decimal::add(Decimal::add($account('cash_bank'), Decimal::add((string) $latest->auto_receivable_balance, $account('receivable_adjustment'), 2), 2), Decimal::add((string) $latest->auto_inventory_balance, $account('inventory_adjustment'), 2), 2), Decimal::add($account('fixed_assets'), $account('other_assets'), 2), 2);
        $liabilities = Decimal::add(Decimal::add($account('accounts_payable'), $account('tax_payable'), 2), $account('other_liabilities'), 2);
        $netProfit = Decimal::sub($profit, $tax, 2);
        $equity = Decimal::add(Decimal::add($account('paid_in_capital'), $account('retained_earnings'), 2), $netProfit, 2);

        return compact('months', 'sales', 'cogs', 'expenses', 'profit', 'taxable', 'tax', 'taxPayable', 'assets', 'liabilities', 'equity', 'netProfit') + [
            'gross_profit' => Decimal::sub($sales, $cogs, 2), 'balance_difference' => Decimal::sub($assets, Decimal::add($liabilities, $equity, 2), 2),
            'accounts' => collect(array_keys(self::ACCOUNTS))->mapWithKeys(fn ($code) => [$code => $account($code)])->all(),
        ];
    }

    public function recalculate(FinancialYear $year): FinancialYear
    {
        $r = $this->report($year);
        $year->update(['commercial_profit_amount' => $r['profit'], 'taxable_income_amount' => $r['taxable'], 'income_tax_amount' => $r['tax'], 'tax_payable_amount' => $r['taxPayable'], 'balance_difference_amount' => $r['balance_difference']]);

        return $year->fresh('months.entries');
    }

    /** @return array<string, mixed> */
    private function monthTotals(FinancialMonthlyClosing $month): array
    {
        $sum = fn (string $code): string => $month->entries->where('account_code', $code)->reduce(fn (string $total, FinancialMonthlyEntry $entry): string => Decimal::add($total, $entry->amount, 2), '0.00');
        $sales = Decimal::add(Decimal::sub(Decimal::add($month->auto_pos_sales_amount, $month->auto_b2b_sales_amount, 2), $month->auto_returns_amount, 2), $sum('sales_adjustment'), 2);
        $cogs = Decimal::add(Decimal::add($month->auto_pos_cogs_amount, $month->auto_b2b_cogs_amount, 2), $sum('cogs_adjustment'), 2);
        $expenses = (string) $month->auto_shift_expense_amount;
        foreach (['salary_expense', 'rent_expense', 'utilities_expense', 'other_expense'] as $code) {
            $expenses = Decimal::add($expenses, $sum($code), 2);
        }

        return ['model' => $month, 'month' => $month->month, 'net_sales' => $sales, 'cogs' => $cogs, 'expenses' => $expenses, 'profit' => Decimal::sub(Decimal::sub($sales, $cogs, 2), $expenses, 2)];
    }

    private function incomeTax(FinancialYear $year, string $turnover, string $taxable): string
    {
        if ($year->tax_scheme === IncomeTaxScheme::MANUAL) {
            return Decimal::normalize($year->manual_tax_amount ?? 0, 2);
        }
        if ($year->tax_scheme === IncomeTaxScheme::FINAL_05) {
            return $this->percent($turnover, (string) $year->final_rate);
        }
        if ($year->tax_scheme === IncomeTaxScheme::GENERAL || Decimal::compare($turnover, $year->facility_turnover_limit, 2) > 0) {
            return $this->percent($taxable, (string) $year->general_rate);
        }
        if (Decimal::compare($turnover, $year->small_turnover_limit, 2) <= 0) {
            return $this->percent($taxable, (string) $year->facility_rate);
        }
        $facilityShare = bcdiv((string) $year->small_turnover_limit, $turnover, 12);
        $facilityIncome = bcmul($taxable, $facilityShare, 2);

        return Decimal::add($this->percent($facilityIncome, (string) $year->facility_rate), $this->percent(Decimal::sub($taxable, $facilityIncome, 2), (string) $year->general_rate), 2);
    }

    private function percent(string $amount, string $rate): string
    {
        return bcmul($amount, bcdiv($rate, '100', 8), 2);
    }

    private function validateAnnualClosing(FinancialYear $year): void
    {
        $errors = [];
        if ($year->months()->where('status', '!=', FinancialMonthStatus::LOCKED->value)->exists() || $year->months()->count() !== 12) {
            $errors['months'] = 'Seluruh 12 bulan harus dikunci.';
        }
        if ($year->months()->where('missing_hpp_count', '>', 0)->exists()) {
            $errors['hpp'] = 'Masih ada transaksi tanpa HPP.';
        }
        if (! $year->tax_scheme_confirmed) {
            $errors['tax_scheme'] = 'Skema pajak belum dikonfirmasi Kepala Keuangan.';
        }
        if (! Decimal::isZero($year->balance_difference_amount, 2)) {
            $errors['balance'] = 'Neraca belum seimbang.';
        }
        if (blank($year->commissioner_name) || blank($year->director_name) || blank($year->report_city)) {
            $errors['signatories'] = 'Kota laporan, Komisaris, dan Direktur wajib diisi.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function assertEditable(FinancialMonthlyClosing $month): void
    {
        if ($month->status === FinancialMonthStatus::LOCKED || $month->financialYear->status === FinancialYearStatus::LOCKED) {
            throw ValidationException::withMessages(['month' => 'Periode telah dikunci.']);
        }
    }

    private function auditTransition(string $scope, FinancialMonthlyClosing|FinancialYear $subject, User $actor, string $before, ?string $reason): void
    {
        $this->audit->record("finance.{$scope}.status_changed", 'finance_annual', $actor, $subject, ['status' => $before], ['status' => $subject->status->value], $reason);
    }
}
