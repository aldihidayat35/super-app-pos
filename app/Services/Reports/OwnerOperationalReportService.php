<?php

namespace App\Services\Reports;

use App\Support\Decimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class OwnerOperationalReportService
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public function build(array $filters, array $base, bool $canViewSensitive): array
    {
        $locationIds = array_map('intval', $filters['location_ids']);
        $summary = is_array($base['summary'] ?? null) ? $base['summary'] : [];
        $sections = is_array($base['sections'] ?? null) ? $base['sections'] : [];
        $charts = is_array($base['charts'] ?? null) ? $base['charts'] : [];
        $revenueRows = is_array($charts['revenue'] ?? null) ? $charts['revenue'] : [];
        $channelRows = is_array($charts['channel_mix'] ?? null) ? $charts['channel_mix'] : [];

        if ($filters['work_location_id'] !== null) {
            $retailRevenue = (string) DB::table('pos_sales')->whereIn('work_location_id', $locationIds)
                ->whereBetween('completed_at', [$filters['start'], $filters['end']])
                ->whereIn('status', ['completed', 'returned'])->sum('grand_total_amount');
            $b2bQuery = $this->scopedB2bOrders($locationIds)
                ->whereBetween('b2b_orders.submitted_at', [$filters['start'], $filters['end']])
                ->whereNotIn('b2b_orders.status', ['cancelled', 'rejected']);
            $b2bRevenue = (string) (clone $b2bQuery)->sum('b2b_orders.grand_total_amount');
            $summary['revenue'] = Decimal::add($retailRevenue, $b2bRevenue, 2);
            $channelRows = [['label' => 'Retail POS', 'value' => $retailRevenue], ['label' => 'B2B', 'value' => $b2bRevenue]];
            $revenueRows = $this->replaceB2bRevenue($revenueRows, $b2bQuery, (string) $filters['range']);
        }

        $stock = DB::table('stocks')->whereIn('work_location_id', $locationIds)
            ->selectRaw('COUNT(DISTINCT product_id) as products, COALESCE(SUM(quantity_on_hand),0) as on_hand, COALESCE(SUM(quantity_reserved),0) as reserved, COALESCE(SUM(quantity_damaged),0) as damaged')->first();
        $stockValue = (string) DB::table('stock_batches')->whereIn('work_location_id', $locationIds)
            ->where('status', 'active')->selectRaw('COALESCE(SUM(quantity_on_hand * cost_price),0) as value')->value('value');
        $stockValueByLocation = DB::table('stock_batches')->whereIn('work_location_id', $locationIds)
            ->where('status', 'active')->selectRaw('work_location_id, COALESCE(SUM(quantity_on_hand * cost_price),0) as value')
            ->groupBy('work_location_id')->pluck('value', 'work_location_id');

        $receivableQuery = DB::table('receivables')->whereIn('receivables.work_location_id', $locationIds)
            ->whereNotIn('receivables.status', ['cancelled', 'written_off']);
        $openAccounts = (clone $receivableQuery)->where('outstanding_amount', '>', 0)->count();
        $overdueAccounts = (clone $receivableQuery)->where('outstanding_amount', '>', 0)
            ->whereDate('due_date', '<', now('Asia/Jakarta')->toDateString())->count();
        $latestReceivables = (clone $receivableQuery)->leftJoin('customers', 'customers.id', '=', 'receivables.customer_id')
            ->where('outstanding_amount', '>', 0)
            ->select('receivables.number', 'receivables.due_date', 'receivables.outstanding_amount', 'receivables.aging_bucket', 'customers.business_name as customer')
            ->orderByDesc('receivables.issue_date')->limit(4)->get()->map(fn ($row): array => [
                'number' => (string) $row->number, 'customer' => (string) ($row->customer ?: '-'),
                'due_date' => (string) $row->due_date, 'outstanding' => (string) $row->outstanding_amount,
                'aging_bucket' => (string) $row->aging_bucket,
            ])->all();

        $purchaseQuery = DB::table('purchase_orders')->whereIn('destination_work_location_id', $locationIds)
            ->whereBetween('order_date', [$filters['start_date'], $filters['end_date']])->where('status', '!=', 'cancelled');
        $purchaseValue = (string) (clone $purchaseQuery)->sum('grand_total');
        $supplierRows = (clone $purchaseQuery)->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->selectRaw("COALESCE(suppliers.name, 'Tanpa Supplier') as supplier, COUNT(*) as po_count, COALESCE(SUM(purchase_orders.grand_total),0) as value, MAX(purchase_orders.order_date) as last_order")
            ->groupBy('suppliers.id', 'suppliers.name')->orderByDesc('value')->limit(4)->get()->map(fn ($row): array => [
                'supplier' => (string) $row->supplier, 'po_count' => (int) $row->po_count,
                'value' => (string) $row->value, 'last_order' => (string) $row->last_order,
            ])->all();
        $supplierIds = (clone $purchaseQuery)->distinct()->pluck('supplier_id');
        $supplierScore = Decimal::normalize((string) (DB::table('supplier_scores')->whereIn('supplier_id', $supplierIds)->avg('total_score') ?? 0), 2);

        $attendance = DB::table('attendances')->whereIn('work_location_id', $locationIds)
            ->whereBetween('attendance_date', [$filters['start_date'], $filters['end_date']]);
        $workforce = [
            'present' => (clone $attendance)->where('status', 'present')->count(),
            'late' => (clone $attendance)->where(function (Builder $query): void {
                $query->where('status', 'late')->orWhere('late_minutes', '>', 0);
            })->count(),
            'permission' => (clone $attendance)->whereIn('status', ['permission', 'sick'])->count(),
            'overtime_minutes' => (int) (clone $attendance)->sum('overtime_minutes'),
        ];

        $locations = collect(array_merge(
            is_array($sections['stores'] ?? null) ? $sections['stores'] : [],
            is_array($sections['warehouses'] ?? null) ? $sections['warehouses'] : [],
        ))->map(function (array $row) use ($stockValueByLocation, $canViewSensitive): array {
            $id = (int) ($row['id'] ?? 0);

            return [
                'id' => $id, 'name' => (string) ($row['name'] ?? '-'),
                'revenue' => (string) ($row['revenue'] ?? '0.00'),
                'margin' => $canViewSensitive ? (string) ($row['margin'] ?? '0.00') : null,
                'stock_value' => $canViewSensitive ? (string) ($stockValueByLocation->get($id, '0.00')) : null,
                'critical_count' => (int) ($row['critical_count'] ?? 0),
            ];
        })->sortByDesc('revenue')->values()->all();

        $marginTrend = $canViewSensitive ? $this->marginTrend($filters) : [];
        $receivableByLocation = (clone $receivableQuery)
            ->selectRaw('receivables.work_location_id, COALESCE(SUM(receivables.outstanding_amount),0) as value')
            ->groupBy('receivables.work_location_id')->orderBy('receivables.work_location_id')->pluck('value')->map(fn ($value): string => (string) $value)->all();
        $openAccountsByLocation = (clone $receivableQuery)->where('outstanding_amount', '>', 0)
            ->selectRaw('receivables.work_location_id, COUNT(*) as value')
            ->groupBy('receivables.work_location_id')->orderBy('receivables.work_location_id')->pluck('value')->map(fn ($value): string => (string) $value)->all();

        $critical = (int) ($summary['critical_stock_count'] ?? 0);
        $productCount = (int) ($stock->products ?? 0);
        $availableStock = Decimal::sub(
            Decimal::sub((string) ($stock->on_hand ?? '0'), (string) ($stock->reserved ?? '0')),
            (string) ($stock->damaged ?? '0'),
        );
        $revenue = (string) ($summary['revenue'] ?? '0.00');
        $margin = $canViewSensitive ? (string) ($summary['gross_margin'] ?? '0.00') : null;
        $marginPercent = $canViewSensitive ? (string) ($summary['margin_percent'] ?? '0.00') : null;
        $outstanding = (string) ($summary['receivable_outstanding'] ?? '0.00');
        $overdue = (string) ($summary['overdue_receivable'] ?? '0.00');
        $pendingApproval = $this->scopedControlCount('approval_requests', 'current_status', 'pending', $locationIds);
        $openAnomalies = $this->scopedControlCount('anomaly_alerts', 'status', 'open', $locationIds);

        $health = [
            'profitability' => $marginPercent === null ? null : min(100, max(0, (int) Decimal::mul($marginPercent, '5', 2, 0, 0))),
            'liquidity' => $this->inverseRatioScore($overdue, $outstanding),
            'inventory' => $this->inverseCountScore($critical, $productCount),
            'collection' => $this->inverseCountScore($overdueAccounts, $openAccounts),
            'operations' => max(0, 100 - min(100, ($pendingApproval + $openAnomalies) * 10)),
        ];
        $availableScores = array_values(array_filter($health, fn (?int $score): bool => $score !== null));
        $health['overall'] = intdiv(array_sum($availableScores), count($availableScores));

        return [
            'summary' => [
                'revenue' => $revenue, 'gross_margin' => $margin, 'margin_percent' => $marginPercent,
                'stock_value' => $canViewSensitive ? $stockValue : null,
                'critical_stock_count' => $critical, 'receivable_outstanding' => $outstanding,
                'overdue_receivable' => $overdue, 'open_accounts' => $openAccounts,
                'returns_value' => (string) ($summary['returns_value'] ?? '0.00'),
                'cash_difference' => (string) ($summary['cash_difference'] ?? '0.00'),
                'pending_approval' => $pendingApproval, 'anomaly_open' => $openAnomalies,
            ],
            'health' => $health,
            'inventory' => [
                'products' => $productCount, 'on_hand' => (string) ($stock->on_hand ?? '0'),
                'reserved' => (string) ($stock->reserved ?? '0'), 'damaged' => (string) ($stock->damaged ?? '0'),
                'available' => $availableStock,
                'alerts' => array_slice(is_array($sections['stock_alerts'] ?? null) ? $sections['stock_alerts'] : [], 0, 3),
                'fast_moving' => (int) ($summary['fast_moving_count'] ?? 0), 'slow_moving' => (int) ($summary['slow_moving_count'] ?? 0),
            ],
            'charts' => [
                'revenue' => $revenueRows,
                'channel_mix' => $channelRows,
                'aging' => is_array($sections['aging'] ?? null) ? $sections['aging'] : [],
            ],
            'sparklines' => [
                'revenue' => collect($revenueRows)->map(fn (array $row): string => (string) ($row['total'] ?? $row['value'] ?? $row['revenue'] ?? '0'))->values()->all(),
                'gross_margin' => $marginTrend,
                'stock_value' => $canViewSensitive ? collect($locations)->map(fn (array $row): string => (string) ($row['stock_value'] ?? '0'))->values()->all() : [],
                'receivable' => array_values($receivableByLocation),
                'critical_stock' => collect($locations)->map(fn (array $row): string => (string) $row['critical_count'])->values()->all(),
                'open_accounts' => array_values($openAccountsByLocation),
            ],
            'locations' => $locations,
            'receivables' => $latestReceivables,
            'purchasing' => ['value' => $canViewSensitive ? $purchaseValue : null, 'supplier_score' => $supplierScore, 'suppliers' => $supplierRows],
            'workforce' => $workforce,
        ];
    }

    /** @param list<int> $locationIds */
    private function scopedControlCount(string $table, string $statusColumn, string $status, array $locationIds): int
    {
        return DB::table($table)->where($statusColumn, $status)->where(function (Builder $query) use ($locationIds): void {
            $query->whereNull('work_location_id')->orWhereIn('work_location_id', $locationIds);
        })->count();
    }

    private function inverseRatioScore(string $bad, string $total): int
    {
        if (Decimal::compare($total, '0', 2) <= 0) {
            return 100;
        }

        $ratio = Decimal::div($bad, $total, 2, 2, 2);
        $penalty = (int) Decimal::mul($ratio, '100', 2, 0, 0);

        return max(0, 100 - min(100, $penalty));
    }

    private function inverseCountScore(int $bad, int $total): int
    {
        if ($total <= 0) {
            return 100;
        }

        return max(0, 100 - min(100, intdiv($bad * 100, $total)));
    }

    /** @param list<int> $locationIds */
    private function scopedB2bOrders(array $locationIds): Builder
    {
        $reservations = DB::table('stock_reservations')->select('b2b_order_id')
            ->whereIn('work_location_id', $locationIds)->distinct();

        return DB::table('b2b_orders')->joinSub($reservations, 'scoped_reservations', function ($join): void {
            $join->on('scoped_reservations.b2b_order_id', '=', 'b2b_orders.id');
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function replaceB2bRevenue(array $rows, Builder $query, string $range): array
    {
        $driver = DB::connection()->getDriverName();
        $dateSql = match ([$driver, $range]) {
            ['mysql', 'monthly'] => "DATE_FORMAT(b2b_orders.submitted_at, '%Y-%m')",
            ['mysql', 'yearly'] => "DATE_FORMAT(b2b_orders.submitted_at, '%Y')",
            ['sqlite', 'monthly'] => "strftime('%Y-%m', b2b_orders.submitted_at)",
            ['sqlite', 'yearly'] => "strftime('%Y', b2b_orders.submitted_at)",
            default => 'DATE(b2b_orders.submitted_at)',
        };
        $b2b = (clone $query)->selectRaw($dateSql.' as period, COALESCE(SUM(b2b_orders.grand_total_amount),0) as value')
            ->groupBy('period')->pluck('value', 'period');

        return collect($rows)->map(function (array $row) use ($b2b): array {
            $date = (string) ($row['date'] ?? '');
            $retail = (string) ($row['retail'] ?? '0.00');
            $b2bValue = (string) $b2b->get($date, '0.00');
            $row['b2b'] = $b2bValue;
            $row['total'] = Decimal::add($retail, $b2bValue, 2);

            return $row;
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    private function marginTrend(array $filters): array
    {
        $driver = DB::connection()->getDriverName();
        $dateSql = match ([$driver, (string) $filters['range']]) {
            ['mysql', 'monthly'] => "DATE_FORMAT(completed_at, '%Y-%m')",
            ['mysql', 'yearly'] => "DATE_FORMAT(completed_at, '%Y')",
            ['sqlite', 'monthly'] => "strftime('%Y-%m', completed_at)",
            ['sqlite', 'yearly'] => "strftime('%Y', completed_at)",
            default => 'DATE(completed_at)',
        };

        return DB::table('pos_sales')->whereIn('work_location_id', $filters['location_ids'])
            ->whereBetween('completed_at', [$filters['start'], $filters['end']])
            ->whereIn('status', ['completed', 'returned'])
            ->selectRaw($dateSql.' as period, COALESCE(SUM(total_margin_amount),0) as value')
            ->groupBy('period')->orderBy('period')->pluck('value')->map(fn ($value): string => (string) $value)->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public function buildIncomeStatement(array $filters, array $base, bool $canViewSensitive): array
    {
        $locationIds = array_map('intval', $filters['location_ids']);

        // 1. Retail POS Queries
        $posQuery = DB::table('pos_sales')
            ->whereIn('work_location_id', $locationIds)
            ->whereBetween('completed_at', [$filters['start'], $filters['end']])
            ->whereIn('status', ['completed', 'returned']);

        $posRevenue = (string) (clone $posQuery)->sum('grand_total_amount');
        $posMargin = (string) (clone $posQuery)->sum('total_margin_amount');
        $posDiscount = (string) (clone $posQuery)->sum('discount_amount');
        $posTax = (string) (clone $posQuery)->sum('tax_amount');
        $posSubtotal = (string) (clone $posQuery)->sum('subtotal_amount');
        $posCogs = Decimal::sub($posRevenue, $posMargin, 2);
        if (Decimal::compare($posCogs, '0', 2) < 0) {
            $posCogs = '0.00';
        }

        // 2. B2B Orders Queries
        if ($filters['work_location_id'] !== null) {
            $b2bQuery = $this->scopedB2bOrders($locationIds)
                ->whereBetween('b2b_orders.submitted_at', [$filters['start'], $filters['end']])
                ->whereNotIn('b2b_orders.status', ['cancelled', 'rejected']);
        } else {
            $b2bQuery = DB::table('b2b_orders')
                ->whereBetween('b2b_orders.submitted_at', [$filters['start'], $filters['end']])
                ->whereNotIn('b2b_orders.status', ['cancelled', 'rejected']);
        }

        $b2bRevenue = (string) (clone $b2bQuery)->sum('b2b_orders.grand_total_amount');
        $b2bTax = (string) (clone $b2bQuery)->sum('b2b_orders.tax_amount');
        $b2bDiscount = (string) (clone $b2bQuery)->sum('b2b_orders.discount_amount');
        $b2bSubtotal = (string) (clone $b2bQuery)->sum('b2b_orders.subtotal_amount');
        $b2bCogs = $b2bRevenue;

        // 3. Deductions: Returns and Discounts
        $returnsAmount = $this->calculateReturnsValue($filters);
        $discountsAmount = Decimal::add($posDiscount, $b2bDiscount, 2);
        $grossRevenue = Decimal::add($posRevenue, $b2bRevenue, 2);
        $netRevenue = Decimal::sub($grossRevenue, $returnsAmount, 2);
        if (Decimal::compare($netRevenue, '0', 2) < 0) {
            $netRevenue = '0.00';
        }

        // 4. COGS & Gross Profit
        $cogs = Decimal::add($posCogs, $b2bCogs, 2);
        $grossProfit = Decimal::sub($netRevenue, $cogs, 2);
        $grossMarginPercent = $this->percent($grossProfit, $netRevenue);

        // 5. Operational Shift Expenses
        $shiftQuery = DB::table('cash_shifts')
            ->whereIn('work_location_id', $locationIds)
            ->whereBetween('opened_at', [$filters['start'], $filters['end']]);

        $shiftExpensesTotal = (string) (clone $shiftQuery)->sum('expense_amount');
        $cashShortage = (string) (clone $shiftQuery)->where('difference_amount', '<', 0)->sum(DB::raw('ABS(difference_amount)'));
        $cashSurplus = (string) (clone $shiftQuery)->where('difference_amount', '>', 0)->sum('difference_amount');
        $totalOperationalExpenses = Decimal::add($shiftExpensesTotal, $cashShortage, 2);

        // 6. Net Operating Profit
        $netOperatingProfit = Decimal::sub($grossProfit, $totalOperationalExpenses, 2);
        $netMarginPercent = $this->percent($netOperatingProfit, $netRevenue);

        // 7. Taxes
        $totalTaxCollected = Decimal::add($posTax, $b2bTax, 2);
        $pphFinalEstimate = Decimal::mul($netRevenue, '0.005', 2);
        $taxSummary = [
            'ppn_collected' => $totalTaxCollected,
            'pph_final_estimate' => $pphFinalEstimate,
            'tax_scheme' => 'PP 55/2022 (PPh Final UMKM 0,5%) & PPN',
            'status' => 'Tertib Terdata',
        ];

        // 8. Multi-step P&L Statement Line Items
        $pnlStatement = [
            [
                'section' => 'REVENUE',
                'code' => '4-1000',
                'name' => 'Penjualan Retail (POS)',
                'notes' => 'Total transaksi penjualan kasir selesai',
                'amount' => $posRevenue,
                'is_deduction' => false,
                'is_total' => false,
                'is_highlight' => false,
            ],
            [
                'section' => 'REVENUE',
                'code' => '4-2000',
                'name' => 'Penjualan Grosir & B2B',
                'notes' => 'Pesanan langganan/B2B disetujui',
                'amount' => $b2bRevenue,
                'is_deduction' => false,
                'is_total' => false,
                'is_highlight' => false,
            ],
            [
                'section' => 'REVENUE',
                'code' => '4-SUB',
                'name' => 'Subtotal Penjualan Kotor',
                'notes' => 'POS + B2B sebelum retur',
                'amount' => $grossRevenue,
                'is_deduction' => false,
                'is_total' => false,
                'is_highlight' => false,
            ],
            [
                'section' => 'REVENUE',
                'code' => '4-3000',
                'name' => 'Potongan Retur Penjualan',
                'notes' => 'Retur konsumen / refund shift kasir',
                'amount' => $returnsAmount,
                'is_deduction' => true,
                'is_total' => false,
                'is_highlight' => false,
            ],
            [
                'section' => 'REVENUE',
                'code' => '4-4000',
                'name' => 'Potongan Diskon Penjualan',
                'notes' => 'Diskon nota dan promosi kasir',
                'amount' => $discountsAmount,
                'is_deduction' => true,
                'is_total' => false,
                'is_highlight' => false,
            ],
            [
                'section' => 'REVENUE',
                'code' => 'REV-NET',
                'name' => 'TOTAL PENDAPATAN BERSIH',
                'notes' => 'Net Revenue Periode Terpilih',
                'amount' => $netRevenue,
                'is_deduction' => false,
                'is_total' => true,
                'is_highlight' => false,
            ],
            [
                'section' => 'COGS',
                'code' => '5-1000',
                'name' => 'Beban Pokok Penjualan Retail (HPP POS)',
                'notes' => 'Berdasarkan lapisan batch aktif terjual',
                'amount' => $canViewSensitive ? $posCogs : null,
                'is_deduction' => true,
                'is_total' => false,
                'is_highlight' => false,
            ],
            [
                'section' => 'COGS',
                'code' => '5-2000',
                'name' => 'Beban Pokok Penjualan B2B (HPP B2B)',
                'notes' => 'HPP barang pesanan langganan',
                'amount' => $canViewSensitive ? $b2bCogs : null,
                'is_deduction' => true,
                'is_total' => false,
                'is_highlight' => false,
            ],
            [
                'section' => 'COGS',
                'code' => 'COGS-TOT',
                'name' => 'TOTAL BEBAN POKOK PENJUALAN (HPP)',
                'notes' => 'Total pengeluaran pokok persediaan',
                'amount' => $canViewSensitive ? $cogs : null,
                'is_deduction' => true,
                'is_total' => true,
                'is_highlight' => false,
            ],
            [
                'section' => 'GROSS_PROFIT',
                'code' => 'GP-TOT',
                'name' => 'LABA KOTOR USAHA (GROSS PROFIT)',
                'notes' => 'Gross Profit Margin',
                'amount' => $canViewSensitive ? $grossProfit : null,
                'ratio' => $canViewSensitive ? $grossMarginPercent.'%' : null,
                'is_deduction' => false,
                'is_total' => true,
                'is_highlight' => true,
            ],
            [
                'section' => 'EXPENSES',
                'code' => '6-1000',
                'name' => 'Beban Operasional Kasir (Shift Expenses)',
                'notes' => 'Pengeluaran kasir selama shift kerja',
                'amount' => $shiftExpensesTotal,
                'is_deduction' => true,
                'is_total' => false,
                'is_highlight' => false,
            ],
            [
                'section' => 'EXPENSES',
                'code' => '6-2000',
                'name' => 'Beban Selisih Kas Kurang (Cash Shortage)',
                'notes' => 'Selisih kas fisik minus saat closing',
                'amount' => $cashShortage,
                'is_deduction' => true,
                'is_total' => false,
                'is_highlight' => false,
            ],
            [
                'section' => 'EXPENSES',
                'code' => 'OPEX-TOT',
                'name' => 'TOTAL BEBAN OPERASIONAL CABANG',
                'notes' => 'Beban langsung unit kerja',
                'amount' => $totalOperationalExpenses,
                'is_deduction' => true,
                'is_total' => true,
                'is_highlight' => false,
            ],
            [
                'section' => 'NET_PROFIT',
                'code' => 'NP-TOT',
                'name' => 'LABA OPERASIONAL BERSIH (NET OPERATING PROFIT)',
                'notes' => 'Laba setelah beban operasional cabang',
                'amount' => $canViewSensitive ? $netOperatingProfit : null,
                'ratio' => $canViewSensitive ? $netMarginPercent.'%' : null,
                'is_deduction' => false,
                'is_total' => true,
                'is_highlight' => true,
            ],
        ];

        // 9. Branch Profitability
        $locationRows = DB::table('work_locations')
            ->whereIn('id', $locationIds)
            ->get();

        $branchProfitability = [];
        foreach ($locationRows as $loc) {
            $locId = (int) $loc->id;
            $locPos = DB::table('pos_sales')
                ->where('work_location_id', $locId)
                ->whereBetween('completed_at', [$filters['start'], $filters['end']])
                ->whereIn('status', ['completed', 'returned']);
            $locPosRev = (string) (clone $locPos)->sum('grand_total_amount');
            $locPosMargin = (string) (clone $locPos)->sum('total_margin_amount');
            $locPosCogs = Decimal::sub($locPosRev, $locPosMargin, 2);
            if (Decimal::compare($locPosCogs, '0', 2) < 0) {
                $locPosCogs = '0.00';
            }

            $locB2bQuery = $this->scopedB2bOrders([$locId])
                ->whereBetween('b2b_orders.submitted_at', [$filters['start'], $filters['end']])
                ->whereNotIn('b2b_orders.status', ['cancelled', 'rejected']);
            $locB2bRev = (string) (clone $locB2bQuery)->sum('b2b_orders.grand_total_amount');
            $locB2bCogs = $locB2bRev;

            $locTotalRev = Decimal::add($locPosRev, $locB2bRev, 2);
            $locTotalCogs = Decimal::add($locPosCogs, $locB2bCogs, 2);
            $locGrossProfit = Decimal::sub($locTotalRev, $locTotalCogs, 2);
            $locGrossMarginPct = $this->percent($locGrossProfit, $locTotalRev);

            $locShiftExpenses = (string) DB::table('cash_shifts')
                ->where('work_location_id', $locId)
                ->whereBetween('opened_at', [$filters['start'], $filters['end']])
                ->sum('expense_amount');
            $locShortage = (string) DB::table('cash_shifts')
                ->where('work_location_id', $locId)
                ->whereBetween('opened_at', [$filters['start'], $filters['end']])
                ->where('difference_amount', '<', 0)
                ->sum(DB::raw('ABS(difference_amount)'));
            $locTotalExpenses = Decimal::add($locShiftExpenses, $locShortage, 2);

            $locNetProfit = Decimal::sub($locGrossProfit, $locTotalExpenses, 2);
            $locNetMarginPct = $this->percent($locNetProfit, $locTotalRev);

            $branchProfitability[] = [
                'id' => $locId,
                'name' => (string) $loc->name,
                'type' => (string) $loc->type,
                'pos_revenue' => $locPosRev,
                'b2b_revenue' => $locB2bRev,
                'net_revenue' => $locTotalRev,
                'cogs' => $canViewSensitive ? $locTotalCogs : null,
                'gross_profit' => $canViewSensitive ? $locGrossProfit : null,
                'gross_margin_percent' => $canViewSensitive ? $locGrossMarginPct : null,
                'expenses' => $locTotalExpenses,
                'net_profit' => $canViewSensitive ? $locNetProfit : null,
                'net_margin_percent' => $canViewSensitive ? $locNetMarginPct : null,
            ];
        }

        usort($branchProfitability, fn ($a, $b) => Decimal::compare($b['net_revenue'], $a['net_revenue'], 2));

        $positiveTotalNet = '0.00';
        foreach ($branchProfitability as $b) {
            if ($b['net_profit'] !== null && Decimal::compare($b['net_profit'], '0', 2) > 0) {
                $positiveTotalNet = Decimal::add($positiveTotalNet, $b['net_profit'], 2);
            }
        }
        foreach ($branchProfitability as &$b) {
            $b['contribution_percent'] = ($canViewSensitive && $b['net_profit'] !== null)
                ? $this->percent($b['net_profit'], $positiveTotalNet)
                : null;
        }
        unset($b);

        // 10. Shift Expenses Breakdown
        $expenseCategories = DB::table('shift_expenses')
            ->join('cash_shifts', 'cash_shifts.id', '=', 'shift_expenses.cash_shift_id')
            ->whereIn('cash_shifts.work_location_id', $locationIds)
            ->whereBetween('shift_expenses.spent_at', [$filters['start'], $filters['end']])
            ->selectRaw("COALESCE(shift_expenses.category, 'Operasional Umum') as category, COUNT(*) as count, COALESCE(SUM(shift_expenses.amount), 0) as total")
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r): array => [
                'category' => (string) $r->category,
                'count' => (int) $r->count,
                'total' => (string) $r->total,
                'percent' => $this->percent((string) $r->total, $shiftExpensesTotal ?: '1.00'),
            ])
            ->all();

        $recentExpenses = DB::table('shift_expenses')
            ->join('cash_shifts', 'cash_shifts.id', '=', 'shift_expenses.cash_shift_id')
            ->leftJoin('work_locations', 'work_locations.id', '=', 'cash_shifts.work_location_id')
            ->leftJoin('users', 'users.id', '=', 'shift_expenses.created_by')
            ->whereIn('cash_shifts.work_location_id', $locationIds)
            ->whereBetween('shift_expenses.spent_at', [$filters['start'], $filters['end']])
            ->select([
                'shift_expenses.id',
                'shift_expenses.spent_at',
                'shift_expenses.category',
                'shift_expenses.amount',
                'shift_expenses.notes',
                'work_locations.name as location_name',
                'users.name as creator_name',
            ])
            ->orderByDesc('shift_expenses.spent_at')
            ->limit(10)
            ->get()
            ->map(fn ($r): array => [
                'id' => (int) $r->id,
                'spent_at' => (string) $r->spent_at,
                'category' => (string) ($r->category ?: 'Operasional Umum'),
                'amount' => (string) $r->amount,
                'notes' => (string) ($r->notes ?: '-'),
                'location_name' => (string) ($r->location_name ?: '-'),
                'creator_name' => (string) ($r->creator_name ?: '-'),
            ])
            ->all();

        // 11. Structured Rows for Export (PDF, Excel, CSV)
        $exportRows = [];
        foreach ($pnlStatement as $item) {
            $amountDisplay = $item['amount'] === null
                ? 'Akses terbatas'
                : 'Rp '.number_format((float) $item['amount'], 2, ',', '.');
            $exportRows[] = [
                'Kode' => $item['code'],
                'Komponen Laba Rugi' => $item['name'],
                'Keterangan' => $item['notes'],
                'Nominal' => $amountDisplay,
                'Rasio / Margin' => $item['ratio'] ?? '-',
            ];
        }

        return [
            'summary' => [
                'gross_revenue' => $grossRevenue,
                'returns_amount' => $returnsAmount,
                'discounts_amount' => $discountsAmount,
                'revenue' => $netRevenue,
                'net_revenue' => $netRevenue,
                'cogs' => $canViewSensitive ? $cogs : null,
                'gross_margin' => $canViewSensitive ? $grossProfit : null,
                'margin_percent' => $canViewSensitive ? $grossMarginPercent : null,
                'shift_expenses' => $shiftExpensesTotal,
                'cash_shortage' => $cashShortage,
                'cash_surplus' => $cashSurplus,
                'total_expenses' => $totalOperationalExpenses,
                'net_operating_profit' => $canViewSensitive ? $netOperatingProfit : null,
                'net_margin_percent' => $canViewSensitive ? $netMarginPercent : null,
            ],
            'pnl_statement' => $pnlStatement,
            'branch_profitability' => $branchProfitability,
            'shift_expenses_breakdown' => $expenseCategories,
            'recent_shift_expenses' => $recentExpenses,
            'tax_summary' => $taxSummary,
            'rows' => $exportRows,
            'can_view_sensitive' => $canViewSensitive,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function calculateReturnsValue(array $filters): string
    {
        $posReturns = (string) DB::table('pos_returns')
            ->whereIn('work_location_id', $filters['location_ids'])
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$filters['start'], $filters['end']])
            ->sum('refund_amount');

        $whReturns = (string) DB::table('returns')
            ->whereIn('work_location_id', $filters['location_ids'])
            ->whereIn('status', ['approved', 'settled'])
            ->whereBetween('return_date', [$filters['start_date'], $filters['end_date']])
            ->sum('total_value');

        return Decimal::add($posReturns, $whReturns, 2);
    }

    private function percent(string $part, string $total): string
    {
        if (Decimal::compare($total, '0', 2) <= 0) {
            return '0.00';
        }

        return Decimal::mul(Decimal::div($part, $total, 4, 4, 4), '100', 2, 2, 2);
    }
}
