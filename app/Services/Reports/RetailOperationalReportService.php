<?php

namespace App\Services\Reports;

use App\Enums\AttendanceStatus;
use App\Enums\CashShiftStatus;
use App\Enums\InventoryLossStatus;
use App\Enums\PosReturnStatus;
use App\Enums\PosSaleStatus;
use App\Enums\ReceivableStatus;
use App\Support\Decimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type ReportFilters array<string, mixed>
 * @phpstan-type ReportRow array<string, mixed>
 */
class RetailOperationalReportService
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $retailReport
     * @return array<string, mixed>
     */
    public function build(array $filters, array $retailReport, bool $canViewSensitive): array
    {
        $locationIds = array_map('intval', $filters['location_ids']);
        $current = $this->salesSummary($locationIds, $filters['start'], $filters['end']);
        $previous = $this->salesSummary($locationIds, $filters['previous_start'], $filters['previous_end']);
        $cogs = $this->actualCogs($locationIds, $filters['start'], $filters['end']);
        $margin = Decimal::sub($current['revenue'], $cogs, 2);
        $expenses = $this->money(DB::table('shift_expenses')
            ->whereIn('work_location_id', $locationIds)
            ->whereBetween('spent_at', [$filters['start'], $filters['end']])
            ->sum('amount'));
        $stock = $this->stockSummary($locationIds);
        if (! $canViewSensitive) {
            $stock['stock_value'] = null;
        }
        $returns = $this->returnSummary($locationIds, $filters);
        $receivables = $this->receivableSummary($locationIds);
        $team = $this->teamSummary($locationIds, $filters);

        $summary = [
            'revenue' => $current['revenue'],
            'transaction_count' => $current['transaction_count'],
            'revenue_change' => $this->change($current['revenue'], $previous['revenue']),
            'transaction_change' => $this->change((string) $current['transaction_count'], (string) $previous['transaction_count']),
        ];

        $profitability = ['sensitive' => $canViewSensitive];
        if ($canViewSensitive) {
            $marginPercent = $this->percentage($margin, $current['revenue']);
            $summary += [
                'margin' => $margin,
                'margin_percent' => $marginPercent,
                'margin_change' => $this->change($margin, Decimal::sub($previous['revenue'], $previous['cogs'], 2)),
            ];
            $profitability += [
                'hpp' => $cogs,
                'margin' => $margin,
                'margin_percent' => $marginPercent,
                'expenses' => $expenses,
                'hpp_percent' => $this->percentage($cogs, $current['revenue']),
                'expense_percent' => $this->percentage($expenses, $current['revenue']),
            ];
        }

        return [
            'summary' => $summary,
            'sales' => [
                'revenue' => $current['revenue'],
                'margin' => $canViewSensitive ? $margin : null,
                'average_ticket' => $this->average($current['revenue'], $current['transaction_count']),
                'transaction_count' => $current['transaction_count'],
                'revenue_change' => $summary['revenue_change'],
                'margin_change' => $canViewSensitive ? $summary['margin_change'] : null,
                'transaction_change' => $summary['transaction_change'],
                'chart' => $this->salesChart($retailReport['rows'] ?? []),
            ],
            'profitability' => $profitability,
            'shifts' => $this->shiftSummary($locationIds, $filters, $current, $retailReport),
            'inventory' => $stock,
            'exceptions' => $returns,
            'receivables' => $receivables,
            'team' => $team,
            'rows' => $this->buildExportRows($current, $summary, $cogs, $margin, $expenses, $profitability, $locationIds, $filters, $retailReport, $stock, $returns, $receivables, $team, $canViewSensitive),
            'last_updated_at' => $filters['last_updated_at'],
        ];
    }

    /**
     * @param  list<int>  $locationIds
     * @return array{revenue: string, cogs: string, transaction_count: int}
     */
    private function salesSummary(array $locationIds, mixed $start, mixed $end): array
    {
        $query = DB::table('pos_sales')
            ->whereIn('work_location_id', $locationIds)
            ->whereBetween('completed_at', [$start, $end])
            ->whereIn('status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value]);

        $grossRevenue = (string) (clone $query)->sum('grand_total_amount');
        $returnsAmount = (string) DB::table('pos_returns')
            ->whereIn('work_location_id', $locationIds)
            ->where('status', PosReturnStatus::COMPLETED->value)
            ->whereBetween('completed_at', [$start, $end])
            ->sum('refund_amount');

        $netRevenue = Decimal::sub($grossRevenue, $returnsAmount, 2);
        if (Decimal::compare($netRevenue, '0', 2) < 0) {
            $netRevenue = '0.00';
        }

        $grossCogs = (string) DB::table('pos_sale_items')
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->whereIn('pos_sales.work_location_id', $locationIds)
            ->whereBetween('pos_sales.completed_at', [$start, $end])
            ->whereIn('pos_sales.status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value])
            ->sum(DB::raw('pos_sale_items.hpp_snapshot * pos_sale_items.base_quantity'));

        $returnedCogs = (string) DB::table('pos_return_items')
            ->join('pos_returns', 'pos_returns.id', '=', 'pos_return_items.pos_return_id')
            ->whereIn('pos_returns.work_location_id', $locationIds)
            ->where('pos_returns.status', PosReturnStatus::COMPLETED->value)
            ->whereBetween('pos_returns.completed_at', [$start, $end])
            ->sum('pos_return_items.reversed_cogs_amount');

        $netCogs = Decimal::sub($grossCogs, $returnedCogs, 2);
        if (Decimal::compare($netCogs, '0', 2) < 0) {
            $netCogs = '0.00';
        }

        return [
            'revenue' => $this->money($netRevenue),
            'cogs' => $this->money($netCogs),
            'transaction_count' => (clone $query)->count(),
        ];
    }

    /** @param list<int> $locationIds */
    private function actualCogs(array $locationIds, mixed $start, mixed $end): string
    {
        $layerCogs = DB::table('stock_layer_allocations as allocations')
            ->join('pos_sale_items', function ($join): void {
                $join->on('pos_sale_items.id', '=', 'allocations.reference_id')
                    ->where('allocations.reference_type', '=', 'pos_sale_item')
                    ->where('allocations.event_type', '=', 'sale');
            })
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->whereIn('pos_sales.work_location_id', $locationIds)
            ->whereBetween('pos_sales.completed_at', [$start, $end])
            ->whereIn('pos_sales.status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value])
            ->sum(DB::raw('allocations.quantity * allocations.unit_cost'));

        $legacyCogs = DB::table('pos_sale_items')
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->whereIn('pos_sales.work_location_id', $locationIds)
            ->whereBetween('pos_sales.completed_at', [$start, $end])
            ->whereIn('pos_sales.status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value])
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from('stock_layer_allocations')
                    ->whereColumn('stock_layer_allocations.reference_id', 'pos_sale_items.id')
                    ->where('stock_layer_allocations.reference_type', 'pos_sale_item')
                    ->where('stock_layer_allocations.event_type', 'sale');
            })
            ->sum(DB::raw('pos_sale_items.hpp_snapshot * pos_sale_items.base_quantity'));

        $totalCogs = Decimal::add($this->money($layerCogs), $this->money($legacyCogs), 2);

        $returnedCogs = (string) DB::table('pos_return_items')
            ->join('pos_returns', 'pos_returns.id', '=', 'pos_return_items.pos_return_id')
            ->whereIn('pos_returns.work_location_id', $locationIds)
            ->where('pos_returns.status', PosReturnStatus::COMPLETED->value)
            ->whereBetween('pos_returns.completed_at', [$start, $end])
            ->sum('pos_return_items.reversed_cogs_amount');

        $netActualCogs = Decimal::sub($totalCogs, $returnedCogs, 2);
        if (Decimal::compare($netActualCogs, '0', 2) < 0) {
            $netActualCogs = '0.00';
        }

        return $netActualCogs;
    }

    /**
     * @param  list<int>  $locationIds
     * @param  ReportFilters  $filters
     * @param  array{revenue: string, cogs: string, transaction_count: int}  $sales
     * @param  array<string, mixed>  $retailReport
     * @return array<string, mixed>
     */
    private function shiftSummary(array $locationIds, array $filters, array $sales, array $retailReport): array
    {
        $shifts = DB::table('cash_shifts')
            ->whereIn('work_location_id', $locationIds)
            ->where(function (Builder $query) use ($filters): void {
                $query->whereBetween('opened_at', [$filters['start'], $filters['end']])
                    ->orWhereBetween('closing_submitted_at', [$filters['start'], $filters['end']]);
            });

        $rows = array_slice($retailReport['rows'], 0, 4);

        return [
            'transaction_count' => $sales['transaction_count'],
            'shift_count' => (clone $shifts)->count(),
            'cash_difference' => $this->money((clone $shifts)->sum('difference_amount')),
            'normal' => (clone $shifts)->whereIn('status', [CashShiftStatus::APPROVED->value, CashShiftStatus::CLOSED->value])->count(),
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<int>  $locationIds
     * @return array<string, mixed>
     */
    private function stockSummary(array $locationIds): array
    {
        $stock = DB::table('stocks')
            ->whereIn('work_location_id', $locationIds)
            ->selectRaw('COUNT(DISTINCT product_id) as total_products, COALESCE(SUM(quantity_on_hand - quantity_reserved - quantity_damaged), 0) as available')
            ->first();
        $value = DB::table('stock_batches')
            ->whereIn('work_location_id', $locationIds)
            ->where('status', 'active')
            ->sum(DB::raw('quantity_on_hand * cost_price'));
        $alerts = DB::table('stocks')
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->whereIn('stocks.work_location_id', $locationIds)
            ->selectRaw('products.sku, products.name as product, products.minimum_stock, SUM(stocks.quantity_on_hand - stocks.quantity_reserved - stocks.quantity_damaged) as available')
            ->groupBy('products.id', 'products.sku', 'products.name', 'products.minimum_stock')
            ->havingRaw('SUM(stocks.quantity_on_hand - stocks.quantity_reserved - stocks.quantity_damaged) <= products.minimum_stock')
            ->orderBy('available')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'sku' => $row->sku,
                'product' => $row->product,
                'available' => $this->quantity($row->available),
                'minimum_stock' => $this->quantity($row->minimum_stock),
            ])->all();
        $criticalCount = count($alerts);

        return [
            'total_products' => (int) data_get($stock, 'total_products', 0),
            'available_stock' => $this->quantity(data_get($stock, 'available', 0)),
            'stock_value' => $this->money($value),
            'critical_count' => $criticalCount,
            'restock_count' => collect($alerts)->filter(fn (array $row): bool => Decimal::compare($row['available'], '0', 4) > 0)->count(),
            'alerts' => $alerts,
        ];
    }

    /**
     * @param  list<int>  $locationIds
     * @param  ReportFilters  $filters
     * @return array<string, mixed>
     */
    private function returnSummary(array $locationIds, array $filters): array
    {
        $returns = DB::table('pos_returns')
            ->whereIn('work_location_id', $locationIds)
            ->where('status', PosReturnStatus::COMPLETED->value)
            ->whereBetween('completed_at', [$filters['start'], $filters['end']]);
        $emergency = DB::table('emergency_purchases')
            ->whereIn('work_location_id', $locationIds)
            ->whereBetween('purchased_at', [$filters['start'], $filters['end']])
            ->whereIn('status', ['purchased', 'unallocated', 'completed', 'warehouse_pending', 'regularized']);
        $losses = DB::table('inventory_losses')
            ->whereIn('work_location_id', $locationIds)
            ->where('status', InventoryLossStatus::APPROVED->value)
            ->whereBetween('approved_at', [$filters['start'], $filters['end']]);

        return [
            'return_count' => (clone $returns)->count(),
            'return_value' => $this->money((clone $returns)->sum('refund_amount')),
            'emergency_value' => $this->money((clone $emergency)->sum('total_cost')),
            'loss_value' => $this->money((clone $losses)->sum('loss_value')),
            'rows' => [
                ['label' => 'Retur', 'transaction_count' => (clone $returns)->count(), 'value' => $this->money((clone $returns)->sum('refund_amount'))],
                ['label' => 'Pembelian Darurat', 'transaction_count' => (clone $emergency)->count(), 'value' => $this->money((clone $emergency)->sum('total_cost'))],
                ['label' => 'Loss', 'transaction_count' => (clone $losses)->count(), 'value' => $this->money((clone $losses)->sum('loss_value'))],
            ],
        ];
    }

    /**
     * @param  list<int>  $locationIds
     * @return array<string, mixed>
     */
    private function receivableSummary(array $locationIds): array
    {
        $active = DB::table('receivables')
            ->whereIn('work_location_id', $locationIds)
            ->where('channel', 'retail')
            ->whereNotIn('status', [ReceivableStatus::PAID->value, ReceivableStatus::CANCELLED->value, ReceivableStatus::WRITTEN_OFF->value]);
        $overdue = (clone $active)->where(function (Builder $query): void {
            $query->where('status', ReceivableStatus::OVERDUE->value)
                ->orWhereDate('due_date', '<', now('Asia/Jakarta')->toDateString());
        });
        $rows = (clone $active)
            ->leftJoin('customers', 'customers.id', '=', 'receivables.customer_id')
            ->select('receivables.number', 'customers.business_name as customer', 'receivables.due_date', 'receivables.outstanding_amount')
            ->orderBy('receivables.due_date')->limit(4)->get()->map(fn ($row): array => (array) $row)->all();

        return [
            'outstanding' => $this->money((clone $active)->sum('outstanding_amount')),
            'overdue' => $this->money((clone $overdue)->sum('outstanding_amount')),
            'account_count' => (clone $active)->count(),
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<int>  $locationIds
     * @param  ReportFilters  $filters
     * @return array<string, mixed>
     */
    private function teamSummary(array $locationIds, array $filters): array
    {
        $employees = DB::table('employees')
            ->whereIn('employees.work_location_id', $locationIds)
            ->where('employees.is_active', true)
            ->whereNull('employees.deleted_at');
        $counts = DB::table('attendances')
            ->whereIn('work_location_id', $locationIds)
            ->whereBetween('attendance_date', [$filters['start_date'], $filters['end_date']])
            ->selectRaw('status, COUNT(DISTINCT employee_id) as total')->groupBy('status')->pluck('total', 'status');
        $salesByUser = DB::table('pos_sales')
            ->whereIn('work_location_id', $locationIds)
            ->whereBetween('completed_at', [$filters['start'], $filters['end']])
            ->whereIn('status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value])
            ->selectRaw('cashier_user_id, COUNT(*) as total')->groupBy('cashier_user_id')->pluck('total', 'cashier_user_id');
        $rows = (clone $employees)->select('employees.id', 'employees.user_id', 'employees.name', 'employees.position')
            ->orderBy('employees.name')->limit(8)->get()->map(function ($employee) use ($filters, $salesByUser): array {
                $attendance = DB::table('attendances')->leftJoin('work_shifts', 'work_shifts.id', '=', 'attendances.work_shift_id')
                    ->where('attendances.employee_id', $employee->id)
                    ->whereBetween('attendances.attendance_date', [$filters['start_date'], $filters['end_date']])
                    ->orderByDesc('attendances.attendance_date')
                    ->select('attendances.status', 'work_shifts.name as shift_name', 'work_shifts.start_time', 'work_shifts.end_time')->first();

                return [
                    'name' => $employee->name,
                    'position' => $employee->position ?: 'Staf',
                    'shift' => $attendance?->shift_name ?: '-',
                    'status' => $attendance?->status ?: '-',
                    'transactions' => (int) ($salesByUser[$employee->user_id] ?? 0),
                ];
            })->all();

        return [
            'total' => (clone $employees)->count(),
            'present' => (int) ($counts[AttendanceStatus::PRESENT->value] ?? 0),
            'late' => (int) ($counts[AttendanceStatus::LATE->value] ?? 0),
            'permission' => (int) ($counts[AttendanceStatus::PERMISSION->value] ?? 0),
            'sick' => (int) ($counts[AttendanceStatus::SICK->value] ?? 0),
            'rows' => $rows,
        ];
    }

    /** @return array{value: string|null, direction: string} */
    private function change(string $current, string $previous): array
    {
        if (Decimal::isZero($previous, 2)) {
            return ['value' => null, 'direction' => 'neutral'];
        }

        $ratio = Decimal::div(Decimal::sub($current, $previous, 2), $previous, 2, 2, 4);
        $value = Decimal::mul($ratio, '100', 4, 0, 1);

        return ['value' => $value, 'direction' => Decimal::compare($value, '0', 1) >= 0 ? 'up' : 'down'];
    }

    private function percentage(string $value, string $base): string
    {
        if (Decimal::isZero($base, 2)) {
            return '0.0';
        }

        return Decimal::mul(Decimal::div($value, $base, 2, 2, 4), '100', 4, 0, 1);
    }

    private function average(string $value, int $count): string
    {
        return $count === 0 ? '0.00' : Decimal::div($value, (string) $count, 2, 0, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{date: string, revenue: string, margin: string}>
     */
    private function salesChart(array $rows): array
    {
        return collect($rows)->groupBy('date')->map(function ($items, string $date): array {
            return [
                'date' => $date,
                'revenue' => $items->reduce(fn (string $total, array $row): string => Decimal::add($total, (string) ($row['revenue'] ?? 0), 2), '0.00'),
                'margin' => $items->reduce(fn (string $total, array $row): string => Decimal::add($total, (string) ($row['margin'] ?? 0), 2), '0.00'),
            ];
        })->sortBy('date')->take(-7)->values()->all();
    }

    private function money(mixed $value): string
    {
        return Decimal::normalize((string) ($value ?? 0), 2);
    }

    private function quantity(mixed $value): string
    {
        return Decimal::normalize((string) ($value ?? 0), 4);
    }

    /**
     * @param  array{revenue: string, cogs: string, transaction_count: int}  $current
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $profitability
     * @param  list<int>  $locationIds
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $retailReport
     * @param  array<string, mixed>  $stock
     * @param  array<string, mixed>  $returns
     * @param  array<string, mixed>  $receivables
     * @param  array<string, mixed>  $team
     * @return list<array<string, mixed>>
     */
    private function buildExportRows(
        array $current,
        array $summary,
        string $cogs,
        string $margin,
        string $expenses,
        array $profitability,
        array $locationIds,
        array $filters,
        array $retailReport,
        array $stock,
        array $returns,
        array $receivables,
        array $team,
        bool $canViewSensitive,
    ): array {
        $rows = [];

        $revChange = isset($summary['revenue_change']['value']) ? $summary['revenue_change']['value'].'%' : '-';
        $marginChange = isset($summary['margin_change']['value']) ? $summary['margin_change']['value'].'%' : '-';

        // 1. Kinerja Penjualan & Margin
        $rows[] = [
            'Seksi' => 'Ringkasan Penjualan',
            'Item / Keterangan' => 'Total Penjualan Bersih (Net Revenue)',
            'Jumlah / Qty' => $current['transaction_count'].' Transaksi',
            'Nominal / Nilai' => 'Rp '.number_format((float) $current['revenue'], 2, ',', '.'),
            'Status / Catatan' => 'Perubahan vs periode sebelumnya: '.$revChange,
        ];
        $rows[] = [
            'Seksi' => 'Ringkasan Penjualan',
            'Item / Keterangan' => 'Rata-rata Nilai Transaksi (Average Ticket)',
            'Jumlah / Qty' => '-',
            'Nominal / Nilai' => 'Rp '.number_format((float) $this->average($current['revenue'], $current['transaction_count']), 2, ',', '.'),
            'Status / Catatan' => '-',
        ];

        if ($canViewSensitive) {
            $rows[] = [
                'Seksi' => 'Profitabilitas',
                'Item / Keterangan' => 'Harga Pokok Penjualan (HPP)',
                'Jumlah / Qty' => '-',
                'Nominal / Nilai' => 'Rp '.number_format((float) $cogs, 2, ',', '.'),
                'Status / Catatan' => 'Rasio HPP: '.($profitability['hpp_percent'] ?? '0.00').'%',
            ];
            $rows[] = [
                'Seksi' => 'Profitabilitas',
                'Item / Keterangan' => 'Margin Kotor (Gross Margin)',
                'Jumlah / Qty' => '-',
                'Nominal / Nilai' => 'Rp '.number_format((float) $margin, 2, ',', '.'),
                'Status / Catatan' => 'Margin: '.($profitability['margin_percent'] ?? '0.00').'% | Perubahan: '.$marginChange,
            ];
            $rows[] = [
                'Seksi' => 'Profitabilitas',
                'Item / Keterangan' => 'Beban Operasional Shift',
                'Jumlah / Qty' => '-',
                'Nominal / Nilai' => 'Rp '.number_format((float) $expenses, 2, ',', '.'),
                'Status / Catatan' => 'Rasio Beban: '.($profitability['expense_percent'] ?? '0.00').'%',
            ];
        }

        // 2. Transaksi Shift Kasir
        $shiftsData = $this->shiftSummary($locationIds, $filters, $current, $retailReport);
        foreach ($shiftsData['rows'] ?? [] as $shiftRow) {
            $rows[] = [
                'Seksi' => 'Shift & Kasir',
                'Item / Keterangan' => 'Kasir: '.($shiftRow['cashier'] ?? '-'),
                'Jumlah / Qty' => ($shiftRow['transaction_count'] ?? 0).' Transaksi',
                'Nominal / Nilai' => 'Rp '.number_format((float) ($shiftRow['revenue'] ?? 0), 2, ',', '.'),
                'Status / Catatan' => 'Tanggal: '.($shiftRow['date'] ?? '-'),
            ];
        }

        // 3. Stok Barang & Kritis
        $rows[] = [
            'Seksi' => 'Persediaan & Stok',
            'Item / Keterangan' => 'Total Produk Terdaftar',
            'Jumlah / Qty' => ($stock['total_products'] ?? 0).' SKU',
            'Nominal / Nilai' => $canViewSensitive && isset($stock['stock_value']) ? 'Rp '.number_format((float) $stock['stock_value'], 2, ',', '.') : 'Akses terbatas',
            'Status / Catatan' => 'Stok Tersedia: '.number_format((float) ($stock['available_stock'] ?? 0), 2, ',', '.'),
        ];
        $rows[] = [
            'Seksi' => 'Persediaan & Stok',
            'Item / Keterangan' => 'Stok Kritis & Perlu Restock',
            'Jumlah / Qty' => ($stock['critical_count'] ?? 0).' SKU Kritis',
            'Nominal / Nilai' => '-',
            'Status / Catatan' => ($stock['restock_count'] ?? 0).' SKU Perlu Restock',
        ];
        foreach ($stock['alerts'] ?? [] as $alert) {
            $rows[] = [
                'Seksi' => 'Alert Stok Kritis',
                'Item / Keterangan' => ($alert['sku'] ?? '').' - '.($alert['product'] ?? ''),
                'Jumlah / Qty' => ($alert['available'] ?? 0).' Tersedia',
                'Nominal / Nilai' => '-',
                'Status / Catatan' => 'Status: Stok Kritis',
            ];
        }

        // 4. Retur & Pengecualian
        $rows[] = [
            'Seksi' => 'Retur & Pengecualian',
            'Item / Keterangan' => 'Total Retur Penjualan',
            'Jumlah / Qty' => ($returns['return_count'] ?? 0).' Kali',
            'Nominal / Nilai' => 'Rp '.number_format((float) ($returns['return_value'] ?? 0), 2, ',', '.'),
            'Status / Catatan' => 'Nilai Kerugian / Darurat: Rp '.number_format((float) ($returns['loss_value'] ?? 0), 2, ',', '.'),
        ];
        foreach ($returns['rows'] ?? [] as $excRow) {
            $rows[] = [
                'Seksi' => 'Detail Pengecualian',
                'Item / Keterangan' => $excRow['label'] ?? '-',
                'Jumlah / Qty' => ($excRow['transaction_count'] ?? 0).' Transaksi',
                'Nominal / Nilai' => 'Rp '.number_format((float) ($excRow['value'] ?? 0), 2, ',', '.'),
                'Status / Catatan' => '-',
            ];
        }

        // 5. Piutang Retail
        $rows[] = [
            'Seksi' => 'Piutang Retail',
            'Item / Keterangan' => 'Total Piutang Berjalan (Outstanding)',
            'Jumlah / Qty' => ($receivables['account_count'] ?? 0).' Akun',
            'Nominal / Nilai' => 'Rp '.number_format((float) ($receivables['outstanding'] ?? 0), 2, ',', '.'),
            'Status / Catatan' => 'Lewat Jatuh Tempo: Rp '.number_format((float) ($receivables['overdue'] ?? 0), 2, ',', '.'),
        ];
        foreach ($receivables['rows'] ?? [] as $recRow) {
            $rows[] = [
                'Seksi' => 'Detail Piutang',
                'Item / Keterangan' => ($recRow['number'] ?? '-').' - '.($recRow['customer'] ?? '-'),
                'Jumlah / Qty' => '-',
                'Nominal / Nilai' => 'Rp '.number_format((float) ($recRow['outstanding_amount'] ?? 0), 2, ',', '.'),
                'Status / Catatan' => 'Jatuh Tempo: '.($recRow['due_date'] ?? '-'),
            ];
        }

        // 6. Tim Toko
        $rows[] = [
            'Seksi' => 'Tim Toko',
            'Item / Keterangan' => 'Kehadiran Karyawan Toko',
            'Jumlah / Qty' => ($team['present'] ?? 0).' Hadir dari '.($team['total'] ?? 0).' Tim',
            'Nominal / Nilai' => '-',
            'Status / Catatan' => 'Terlambat: '.($team['late'] ?? 0).' | Sakit: '.($team['sick'] ?? 0).' | Izin: '.($team['permission'] ?? 0),
        ];
        foreach ($team['rows'] ?? [] as $tRow) {
            $rows[] = [
                'Seksi' => 'Anggota Tim',
                'Item / Keterangan' => ($tRow['name'] ?? '-').' ('.($tRow['position'] ?? '-').')',
                'Jumlah / Qty' => ($tRow['transactions'] ?? 0).' Transaksi',
                'Nominal / Nilai' => '-',
                'Status / Catatan' => 'Shift: '.($tRow['shift'] ?? '-').' | Status: '.($tRow['status'] ?? '-'),
            ];
        }

        return $rows;
    }
}
