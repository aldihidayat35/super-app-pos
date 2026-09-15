<?php

namespace App\Exports;

use App\Models\FinancialYear;
use App\Support\Decimal;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class AnnualFinanceExport implements WithMultipleSheets
{
    /** @param array<string, mixed> $report */
    public function __construct(private readonly FinancialYear $year, private readonly array $report) {}

    /** @return array<int, AnnualFinanceArraySheet> */
    public function sheets(): array
    {
        $summary = [
            ['LAPORAN KEUANGAN DAN PAJAK TAHUNAN'], ['Perusahaan', $this->year->legal_name], ['Tahun', $this->year->year],
            ['Penjualan bersih', $this->report['sales']], ['HPP', $this->report['cogs']], ['Laba kotor', $this->report['gross_profit']],
            ['Beban', $this->report['expenses']], ['Laba sebelum pajak', $this->report['profit']], ['PPh Badan', $this->report['tax']],
            ['Laba setelah pajak', $this->report['netProfit']], ['Total aset', $this->report['assets']],
            ['Kewajiban', $this->report['liabilities']], ['Ekuitas', $this->report['equity']], ['Selisih neraca', $this->report['balance_difference']],
        ];
        $monthly = [['Bulan', 'Penjualan Bersih', 'HPP', 'Beban', 'Laba/Rugi', 'Status']];
        foreach ($this->report['months'] as $row) {
            $monthly[] = [$row['month'], $row['net_sales'], $row['cogs'], $row['expenses'], $row['profit'], $row['model']->status->label()];
        }
        $sources = [['Bulan', 'POS', 'B2B', 'Retur', 'HPP POS', 'HPP B2B', 'Pengeluaran Shift', 'Piutang', 'Persediaan', 'HPP Kosong']];
        foreach ($this->year->months as $m) {
            $sources[] = [$m->month, $m->auto_pos_sales_amount, $m->auto_b2b_sales_amount, $m->auto_returns_amount, $m->auto_pos_cogs_amount, $m->auto_b2b_cogs_amount, $m->auto_shift_expense_amount, $m->auto_receivable_balance, $m->auto_inventory_balance, $m->missing_hpp_count];
        }
        $entries = [['Bulan', 'Akun', 'Nama', 'Nominal', 'Alasan']];
        foreach ($this->year->months as $m) {
            foreach ($m->entries as $e) {
                $entries[] = [$m->month, $e->account_code, $e->label, $e->amount, $e->reason];
            }
        }
        $credits = Decimal::add(Decimal::add($this->year->tax_credit_amount, $this->year->installment_amount, 2), $this->year->prior_payment_amount, 2);
        $balance = [['NERACA'], ['Kas dan bank', $this->report['accounts']['cash_bank']], ['Piutang dan penyesuaian', Decimal::add($this->year->months->last()->auto_receivable_balance, $this->report['accounts']['receivable_adjustment'], 2)], ['Persediaan dan penyesuaian', Decimal::add($this->year->months->last()->auto_inventory_balance, $this->report['accounts']['inventory_adjustment'], 2)], ['Aset tetap', $this->report['accounts']['fixed_assets']], ['Aset lain', $this->report['accounts']['other_assets']], ['Total aset', $this->report['assets']], ['Total kewajiban', $this->report['liabilities']], ['Total ekuitas', $this->report['equity']], ['Selisih', $this->report['balance_difference']]];
        $fiscal = [['Skema', $this->year->tax_scheme->label()], ['Laba komersial', $this->report['profit']], ['Koreksi positif', $this->year->fiscal_positive_adjustment], ['Koreksi negatif', $this->year->fiscal_negative_adjustment], ['Penghasilan kena pajak', $this->report['taxable']], ['PPh', $this->report['tax']], ['Kredit/angsuran/pembayaran', $credits], ['Kurang/lebih bayar', $this->report['taxPayable']]];

        return [new AnnualFinanceArraySheet('Ringkasan', $summary), new AnnualFinanceArraySheet('Rekap Bulanan', $monthly), new AnnualFinanceArraySheet('Sumber Otomatis', $sources), new AnnualFinanceArraySheet('Koreksi Manual', $entries), new AnnualFinanceArraySheet('Neraca', $balance), new AnnualFinanceArraySheet('Rekonsiliasi Fiskal', $fiscal)];
    }
}

class AnnualFinanceArraySheet implements FromArray, WithTitle
{
    /** @param array<int, array<int, mixed>> $rows */
    public function __construct(private readonly string $title, private readonly array $rows) {}

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->title;
    }
}
