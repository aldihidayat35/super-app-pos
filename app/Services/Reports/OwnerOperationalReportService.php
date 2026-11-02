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
}
