<?php

namespace App\Services\Reports;

use App\Enums\AnomalyStatus;
use App\Enums\ApprovalRequestStatus;
use App\Enums\AttendanceStatus;
use App\Enums\B2bOrderStatus;
use App\Enums\CashShiftStatus;
use App\Enums\GoodsReceiptStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PosReturnStatus;
use App\Enums\PosSaleStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ReceivableStatus;
use App\Enums\ReturnStatus;
use App\Enums\ShipmentStatus;
use App\Enums\StockMutationType;
use App\Enums\StockOpnameStatus;
use App\Enums\StockTransferStatus;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type ReportFilters array<string, mixed>
 * @phpstan-type ReportRow array<string, mixed>
 * @phpstan-type ReportPayload array{type?: string, filters?: ReportFilters, definitions?: list<string>, summary: array<string, mixed>, rows: list<ReportRow>, last_updated_at?: mixed}
 */
class ReportMetricService
{
    /**
     * @param  array<string, mixed>  $input
     * @return ReportFilters
     */
    public function filters(User $user, array $input = []): array
    {
        $start = Carbon::parse($input['start_date'] ?? now('Asia/Jakarta')->startOfMonth()->toDateString(), 'Asia/Jakarta')->startOfDay();
        $end = Carbon::parse($input['end_date'] ?? now('Asia/Jakarta')->toDateString(), 'Asia/Jakarta')->endOfDay();
        $permitted = $user->permittedWorkLocationIds();
        $requestedLocation = isset($input['work_location_id']) && $input['work_location_id'] !== '' ? (int) $input['work_location_id'] : null;
        $locationIds = $requestedLocation !== null ? array_values(array_intersect($permitted, [$requestedLocation])) : $permitted;

        return [
            'start' => $start,
            'end' => $end,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'previous_start' => $start->copy()->subDays($start->diffInDays($end) + 1)->startOfDay(),
            'previous_end' => $start->copy()->subSecond(),
            'work_location_id' => $requestedLocation,
            'location_ids' => $locationIds,
            'channel' => $input['channel'] ?? null,
            'status' => $input['status'] ?? null,
            'product_id' => $input['product_id'] ?? null,
            'category_id' => $input['category_id'] ?? null,
            'supplier_id' => $input['supplier_id'] ?? null,
            'customer_id' => $input['customer_id'] ?? null,
            'user_id' => $input['user_id'] ?? null,
            'last_updated_at' => now('Asia/Jakarta'),
        ];
    }

    /**
     * @param  ReportFilters  $filters
     * @return array<string, mixed>
     */
    public function ownerDashboard(User $user, array $filters): array
    {
        return Cache::remember($this->cacheKey('owner', $user, $filters), 60, function () use ($filters): array {
            $sales = $this->salesSummary($filters);
            $b2b = $this->b2bSummary($filters);
            $stock = $this->stockSummary($filters);
            $receivable = $this->receivableSummary($filters);

            $revenue = Decimal::add($sales['revenue'], $b2b['revenue'], 2);
            $grossMargin = Decimal::add($sales['margin'], $b2b['estimated_margin'], 2);

            return [
                'kpis' => [
                    'revenue' => $revenue,
                    'gross_margin' => $grossMargin,
                    'margin_percent' => $this->percent($grossMargin, $revenue),
                    'stock_value' => $stock['stock_value'],
                    'critical_stock_count' => $stock['critical_count'],
                    'fast_moving_count' => $this->fastMovingProducts($filters)->count(),
                    'slow_moving_count' => $this->slowMovingProducts($filters)->count(),
                    'receivable_outstanding' => $receivable['outstanding'],
                    'overdue_receivable' => $receivable['overdue'],
                    'transactions_today' => $this->transactionsToday($filters),
                    'cash_difference' => $this->cashDifference($filters),
                    'attendance_late' => $this->attendanceCount($filters, AttendanceStatus::LATE->value),
                    'anomaly_open' => DB::table('anomaly_alerts')->where('status', AnomalyStatus::OPEN->value)->count(),
                    'pending_approval' => DB::table('approval_requests')->where('current_status', ApprovalRequestStatus::PENDING->value)->count(),
                    'returns_value' => $this->returnsValue($filters),
                ],
                'charts' => [
                    'daily_revenue' => $this->dailyRevenue($filters),
                    'channel_mix' => [
                        ['label' => 'Retail POS', 'value' => $sales['revenue']],
                        ['label' => 'B2B', 'value' => $b2b['revenue']],
                    ],
                    'branch_margin' => $this->branchMargin($filters),
                    'top_products' => $this->topProducts($filters)->take(10)->values()->all(),
                    'slow_products' => $this->slowMovingProducts($filters)->take(10)->values()->all(),
                    'aging' => $receivable['aging'],
                ],
                'alerts' => $this->alerts(),
                'last_updated_at' => $filters['last_updated_at'],
            ];
        });
    }

    /**
     * @param  ReportFilters  $filters
     * @return array<string, mixed>
     */
    public function warehouseDashboard(User $user, array $filters): array
    {
        return Cache::remember($this->cacheKey('warehouse', $user, $filters), 60, function () use ($filters): array {
            $stock = $this->stockSummary($filters);

            return [
                'kpis' => [
                    'available_quantity' => $stock['available_quantity'],
                    'reserved_quantity' => $stock['reserved_quantity'],
                    'damaged_quantity' => $stock['damaged_quantity'],
                    'stock_value' => $stock['stock_value'],
                    'critical_count' => $stock['critical_count'],
                    'empty_count' => $stock['empty_count'],
                    'incoming_count' => $this->mutationCount($filters, [StockMutationType::RECEIVE->value, StockMutationType::RETURN_IN->value, StockMutationType::TRANSFER_IN->value]),
                    'outgoing_count' => $this->mutationCount($filters, [StockMutationType::ISSUE->value, StockMutationType::RETURN_OUT->value, StockMutationType::TRANSFER_OUT->value]),
                    'pending_po' => DB::table('purchase_orders')->whereIn('status', [PurchaseOrderStatus::SUBMITTED->value, PurchaseOrderStatus::APPROVED->value, PurchaseOrderStatus::SENT_TO_SUPPLIER->value, PurchaseOrderStatus::PARTIALLY_RECEIVED->value])->count(),
                    'pending_transfer' => $this->locationScopedCount('stock_transfers', 'source_work_location_id', $filters, [StockTransferStatus::PENDING_APPROVAL->value, StockTransferStatus::APPROVED->value, StockTransferStatus::PACKING->value, StockTransferStatus::SHIPPED->value]),
                    'pending_order' => DB::table('b2b_orders')->whereIn('status', [B2bOrderStatus::PENDING_CONFIRMATION->value, B2bOrderStatus::WAREHOUSE_VALIDATION->value, B2bOrderStatus::RESERVED->value, B2bOrderStatus::PACKING->value])->count(),
                    'posted_receipts' => DB::table('goods_receipts')->where('status', GoodsReceiptStatus::POSTED->value)->whereBetween('posted_at', [$filters['start'], $filters['end']])->count(),
                    'open_opname' => $this->locationScopedCount('stock_opnames', 'work_location_id', $filters, [StockOpnameStatus::DRAFT->value, StockOpnameStatus::COUNTING->value, StockOpnameStatus::PENDING_APPROVAL->value]),
                ],
                'large_mutations' => $this->largeMutations($filters),
                'charts' => ['daily_movement' => $this->dailyStockMovement($filters)],
                'last_updated_at' => $filters['last_updated_at'],
            ];
        });
    }

    /**
     * @param  ReportFilters  $filters
     * @return array<string, mixed>
     */
    public function retailDashboard(User $user, array $filters): array
    {
        return Cache::remember($this->cacheKey('retail', $user, $filters), 60, function () use ($filters): array {
            $sales = $this->salesSummary($filters);
            $stock = $this->stockSummary($filters);

            return [
                'kpis' => [
                    'revenue' => $sales['revenue'],
                    'margin' => $sales['margin'],
                    'margin_percent' => $this->percent($sales['margin'], $sales['revenue']),
                    'transaction_count' => $sales['transaction_count'],
                    'average_ticket' => $this->divideMoney($sales['revenue'], max(1, (int) $sales['transaction_count'])),
                    'critical_stock_count' => $stock['critical_count'],
                    'active_shift_count' => $this->locationScopedCount('cash_shifts', 'work_location_id', $filters, [CashShiftStatus::OPEN->value, CashShiftStatus::CLOSING_SUBMITTED->value]),
                    'closing_pending_count' => $this->locationScopedCount('cash_shifts', 'work_location_id', $filters, [CashShiftStatus::CLOSING_SUBMITTED->value]),
                    'cash_difference' => $this->cashDifference($filters),
                    'receivable_today' => $this->receivableIssued($filters, 'retail'),
                    'void_count' => $this->locationScopedCount('pos_sales', 'work_location_id', $filters, [PosSaleStatus::VOID_APPROVED->value]),
                    'return_amount' => $this->returnsValue($filters),
                ],
                'charts' => [
                    'daily_revenue' => $this->dailyRevenue($filters, 'retail'),
                    'payment_methods' => $this->paymentMethods($filters),
                    'top_products' => $this->topProducts($filters)->take(10)->values()->all(),
                    'slow_products' => $this->slowMovingProducts($filters)->take(10)->values()->all(),
                ],
                'last_updated_at' => $filters['last_updated_at'],
            ];
        });
    }

    /**
     * @param  ReportFilters  $filters
     * @return array<string, mixed>
     */
    public function b2bDashboard(User $user, array $filters, ?int $customerId = null): array
    {
        $summary = $this->b2bSummary($filters, $customerId);
        $receivables = $this->receivableSummary($filters, $customerId);

        return [
            'kpis' => [
                'active_orders' => DB::table('b2b_orders')->when($customerId, fn (Builder $query) => $query->where('customer_id', $customerId))->whereIn('status', [B2bOrderStatus::PENDING_CONFIRMATION->value, B2bOrderStatus::WAREHOUSE_VALIDATION->value, B2bOrderStatus::RESERVED->value, B2bOrderStatus::INVOICE_READY->value, B2bOrderStatus::AWAITING_PAYMENT->value, B2bOrderStatus::APPROVED_CREDIT->value, B2bOrderStatus::PACKING->value, B2bOrderStatus::SHIPPED->value])->count(),
                'revenue' => $summary['revenue'],
                'open_invoices' => DB::table('invoices')->when($customerId, fn (Builder $query) => $query->where('customer_id', $customerId))->whereIn('status', [InvoiceStatus::ISSUED->value, InvoiceStatus::PARTIAL->value, InvoiceStatus::OVERDUE->value])->count(),
                'outstanding_receivable' => $receivables['outstanding'],
                'shipment_pending' => DB::table('shipments')->when($customerId, fn (Builder $query) => $query->where('customer_id', $customerId))->whereIn('status', [ShipmentStatus::WAITING->value, ShipmentStatus::PACKING->value, ShipmentStatus::READY->value, ShipmentStatus::SHIPPED->value])->count(),
            ],
            'latest_orders' => DB::table('b2b_orders')->when($customerId, fn (Builder $query) => $query->where('customer_id', $customerId))->latest('id')->limit(8)->get(),
            'last_updated_at' => $filters['last_updated_at'],
        ];
    }

    /**
     * @param  ReportFilters  $filters
     * @return ReportPayload
     */
    public function report(string $type, User $user, array $filters): array
    {
        $data = match ($type) {
            'daily' => $this->dailyReport($filters),
            'warehouse' => $this->warehouseReport($filters),
            'retail' => $this->retailReport($filters),
            'b2b' => $this->b2bReport($filters),
            'pricing' => $this->pricingReport($filters),
            'suppliers' => $this->supplierReport($filters),
            'attendance' => $this->attendanceReport($filters),
            'receivables' => $this->receivableReport($filters),
            'audit_notifications' => $this->auditNotificationReport($filters),
            default => $this->dailyReport($filters),
        };

        return [
            'type' => $type,
            'filters' => $filters,
            'definitions' => $this->definitions($type),
            'summary' => $data['summary'],
            'rows' => $data['rows'],
            'last_updated_at' => $filters['last_updated_at'],
        ];
    }

    /**
     * @param  ReportFilters  $filters
     * @return array{summary: array<string, mixed>, rows: list<ReportRow>}
     */
    private function dailyReport(array $filters): array
    {
        $owner = $this->ownerDashboard(new User, $filters);

        return [
            'summary' => $owner['kpis'],
            'rows' => $owner['charts']['daily_revenue'],
        ];
    }

    /**
     * @param  ReportFilters  $filters
     * @return array{summary: array<string, mixed>, rows: list<ReportRow>}
     */
    private function warehouseReport(array $filters): array
    {
        return [
            'summary' => $this->warehouseDashboard(new User, $filters)['kpis'],
            'rows' => DB::table('stocks')->join('products', 'products.id', '=', 'stocks.product_id')
                ->leftJoin('work_locations', 'work_locations.id', '=', 'stocks.work_location_id')
                ->whereIn('stocks.work_location_id', $filters['location_ids'])
                ->selectRaw('products.sku, products.name as product, work_locations.name as location, stocks.quantity_on_hand, stocks.quantity_reserved, stocks.quantity_damaged, (stocks.quantity_on_hand - stocks.quantity_reserved - stocks.quantity_damaged) as available, stocks.cost_value')
                ->orderBy('products.name')
                ->limit(200)
                ->get()
                ->map(fn ($row): array => (array) $row)
                ->all(),
        ];
    }

    /**
     * @param  ReportFilters  $filters
     * @return array{summary: array<string, mixed>, rows: list<ReportRow>}
     */
    private function retailReport(array $filters): array
    {
        return [
            'summary' => $this->retailDashboard(new User, $filters)['kpis'],
            'rows' => DB::table('pos_sales')
                ->leftJoin('users', 'users.id', '=', 'pos_sales.cashier_user_id')
                ->leftJoin('work_locations', 'work_locations.id', '=', 'pos_sales.work_location_id')
                ->whereIn('pos_sales.work_location_id', $filters['location_ids'])
                ->whereBetween('pos_sales.completed_at', [$filters['start'], $filters['end']])
                ->whereIn('pos_sales.status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value])
                ->selectRaw('DATE(pos_sales.completed_at) as date, work_locations.name as location, users.name as cashier, COUNT(*) as transaction_count, COALESCE(SUM(pos_sales.grand_total_amount),0) as revenue, COALESCE(SUM(pos_sales.total_margin_amount),0) as margin')
                ->groupBy('date', 'work_locations.name', 'users.name')
                ->orderBy('date')
                ->get()
                ->map(fn ($row): array => (array) $row)
                ->all(),
        ];
    }

    /**
     * @param  ReportFilters  $filters
     * @return array{summary: array<string, mixed>, rows: list<ReportRow>}
     */
    private function b2bReport(array $filters): array
    {
        return [
            'summary' => $this->b2bSummary($filters),
            'rows' => DB::table('b2b_orders')
                ->leftJoin('customers', 'customers.id', '=', 'b2b_orders.customer_id')
                ->whereBetween('b2b_orders.submitted_at', [$filters['start'], $filters['end']])
                ->whereNotIn('b2b_orders.status', [B2bOrderStatus::CANCELLED->value, B2bOrderStatus::REJECTED->value])
                ->selectRaw('customers.business_name as customer, b2b_orders.status, COUNT(*) as order_count, COALESCE(SUM(b2b_orders.grand_total_amount),0) as revenue')
                ->groupBy('customers.business_name', 'b2b_orders.status')
                ->orderByDesc('revenue')
                ->get()
                ->map(fn ($row): array => (array) $row)
                ->all(),
        ];
    }

    /**
     * @param  ReportFilters  $filters
     * @return array{summary: array<string, mixed>, rows: list<ReportRow>}
     */
    private function pricingReport(array $filters): array
    {
        return [
            'summary' => [
                'price_changes' => DB::table('price_histories')->whereBetween('created_at', [$filters['start'], $filters['end']])->count(),
                'pending_approvals' => DB::table('price_approval_requests')->where('status', 'pending')->count(),
                'sensitive_anomalies' => DB::table('anomaly_alerts')->where('rule_key', 'pricing_sensitive')->where('status', AnomalyStatus::OPEN->value)->count(),
            ],
            'rows' => DB::table('price_histories')
                ->leftJoin('products', 'products.id', '=', 'price_histories.product_id')
                ->whereBetween('price_histories.created_at', [$filters['start'], $filters['end']])
                ->selectRaw('products.sku, products.name as product, price_histories.channel, price_histories.old_price, price_histories.new_price, price_histories.hpp_snapshot, price_histories.minimum_price_snapshot, price_histories.reason, price_histories.created_at')
                ->latest('price_histories.created_at')
                ->limit(200)
                ->get()
                ->map(fn ($row): array => (array) $row)
                ->all(),
        ];
    }

    /**
     * @param  ReportFilters  $filters
     * @return array{summary: array<string, mixed>, rows: list<ReportRow>}
     */
    private function supplierReport(array $filters): array
    {
        return [
            'summary' => [
                'purchase_value' => $this->money(DB::table('purchase_orders')->whereBetween('order_date', [$filters['start_date'], $filters['end_date']])->whereNot('status', PurchaseOrderStatus::CANCELLED->value)->sum('grand_total')),
                'posted_receipts' => DB::table('goods_receipts')->where('status', GoodsReceiptStatus::POSTED->value)->whereBetween('posted_at', [$filters['start'], $filters['end']])->count(),
                'avg_supplier_score' => Decimal::normalize((string) (DB::table('supplier_scores')->avg('total_score') ?? 0), 2),
            ],
            'rows' => DB::table('purchase_orders')
                ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
                ->whereBetween('purchase_orders.order_date', [$filters['start_date'], $filters['end_date']])
                ->whereNot('purchase_orders.status', PurchaseOrderStatus::CANCELLED->value)
                ->selectRaw('suppliers.business_name as supplier, COUNT(*) as po_count, COALESCE(SUM(purchase_orders.grand_total),0) as purchase_value, MAX(purchase_orders.order_date) as last_order_date')
                ->groupBy('suppliers.business_name')
                ->orderByDesc('purchase_value')
                ->get()
                ->map(fn ($row): array => (array) $row)
                ->all(),
        ];
    }

    /**
     * @param  ReportFilters  $filters
     * @return array{summary: array<string, mixed>, rows: list<ReportRow>}
     */
    private function attendanceReport(array $filters): array
    {
        return [
            'summary' => [
                'present' => $this->attendanceCount($filters, AttendanceStatus::PRESENT->value),
                'late' => $this->attendanceCount($filters, AttendanceStatus::LATE->value),
                'permission' => $this->attendanceCount($filters, AttendanceStatus::PERMISSION->value),
                'sick' => $this->attendanceCount($filters, AttendanceStatus::SICK->value),
                'alpha' => $this->attendanceCount($filters, AttendanceStatus::ALPHA->value),
                'overtime_minutes' => (string) DB::table('attendances')->whereIn('work_location_id', $filters['location_ids'])->whereBetween('attendance_date', [$filters['start_date'], $filters['end_date']])->sum('overtime_minutes'),
            ],
            'rows' => DB::table('attendances')
                ->leftJoin('employees', 'employees.id', '=', 'attendances.employee_id')
                ->leftJoin('work_locations', 'work_locations.id', '=', 'attendances.work_location_id')
                ->whereIn('attendances.work_location_id', $filters['location_ids'])
                ->whereBetween('attendance_date', [$filters['start_date'], $filters['end_date']])
                ->selectRaw('attendances.attendance_date, employees.employee_no, employees.name as employee, work_locations.name as location, attendances.status, attendances.late_minutes, attendances.worked_minutes, attendances.overtime_minutes')
                ->orderByDesc('attendances.attendance_date')
                ->limit(200)
                ->get()
                ->map(fn ($row): array => (array) $row)
                ->all(),
        ];
    }

    /**
     * @param  ReportFilters  $filters
     * @return array{summary: array<string, mixed>, rows: list<ReportRow>}
     */
    private function receivableReport(array $filters): array
    {
        $summary = $this->receivableSummary($filters);

        return [
            'summary' => $summary,
            'rows' => DB::table('receivables')
                ->leftJoin('customers', 'customers.id', '=', 'receivables.customer_id')
                ->leftJoin('work_locations', 'work_locations.id', '=', 'receivables.work_location_id')
                ->whereIn('receivables.work_location_id', $filters['location_ids'])
                ->selectRaw('receivables.number, customers.business_name as customer, work_locations.name as location, receivables.channel, receivables.issue_date, receivables.due_date, receivables.principal_amount, receivables.paid_amount, receivables.outstanding_amount, receivables.aging_bucket, receivables.status')
                ->orderByDesc('receivables.due_date')
                ->limit(200)
                ->get()
                ->map(fn ($row): array => (array) $row)
                ->all(),
        ];
    }

    /**
     * @param  ReportFilters  $filters
     * @return array{summary: array<string, mixed>, rows: list<ReportRow>}
     */
    private function auditNotificationReport(array $filters): array
    {
        return [
            'summary' => [
                'audit_events' => DB::table('audit_logs')->whereBetween('occurred_at', [$filters['start'], $filters['end']])->count(),
                'anomaly_open' => DB::table('anomaly_alerts')->where('status', AnomalyStatus::OPEN->value)->count(),
                'approval_pending' => DB::table('approval_requests')->where('current_status', ApprovalRequestStatus::PENDING->value)->count(),
                'security_events' => DB::table('audit_logs')->where('module', 'security')->whereBetween('occurred_at', [$filters['start'], $filters['end']])->count(),
            ],
            'rows' => DB::table('audit_logs')
                ->whereBetween('occurred_at', [$filters['start'], $filters['end']])
                ->select('occurred_at', 'module', 'event', 'subject_type', 'subject_id', 'severity', 'reason')
                ->latest('occurred_at')
                ->limit(200)
                ->get()
                ->map(fn ($row): array => (array) $row)
                ->all(),
        ];
    }

    /**
     * @param  ReportFilters  $filters
     * @return array{revenue: string, margin: string, transaction_count: int}
     */
    private function salesSummary(array $filters): array
    {
        $query = DB::table('pos_sales')
            ->whereIn('work_location_id', $filters['location_ids'])
            ->whereBetween('completed_at', [$filters['start'], $filters['end']])
            ->whereIn('status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value]);

        return [
            'revenue' => $this->money((clone $query)->sum('grand_total_amount')),
            'margin' => $this->money((clone $query)->sum('total_margin_amount')),
            'transaction_count' => (clone $query)->count(),
        ];
    }

    /**
     * @param  ReportFilters  $filters
     * @return array{revenue: string, order_count: int, estimated_margin: string}
     */
    private function b2bSummary(array $filters, ?int $customerId = null): array
    {
        $query = DB::table('b2b_orders')
            ->when($customerId, fn (Builder $query) => $query->where('customer_id', $customerId))
            ->whereBetween('submitted_at', [$filters['start'], $filters['end']])
            ->whereNotIn('status', [B2bOrderStatus::CANCELLED->value, B2bOrderStatus::REJECTED->value]);

        return [
            'revenue' => $this->money((clone $query)->sum('grand_total_amount')),
            'order_count' => (clone $query)->count(),
            'estimated_margin' => '0.00',
        ];
    }

    /**
     * @param  ReportFilters  $filters
     * @return array{on_hand_quantity: string, reserved_quantity: string, damaged_quantity: string, available_quantity: string, stock_value: string, critical_count: int, empty_count: int}
     */
    private function stockSummary(array $filters): array
    {
        $query = DB::table('stocks')->whereIn('work_location_id', $filters['location_ids']);
        $onHand = $this->quantity((clone $query)->sum('quantity_on_hand'));
        $reserved = $this->quantity((clone $query)->sum('quantity_reserved'));
        $damaged = $this->quantity((clone $query)->sum('quantity_damaged'));
        $available = Decimal::sub(Decimal::sub($onHand, $reserved), $damaged);

        return [
            'on_hand_quantity' => $onHand,
            'reserved_quantity' => $reserved,
            'damaged_quantity' => $damaged,
            'available_quantity' => $available,
            'stock_value' => $this->money((clone $query)->sum('cost_value')),
            'critical_count' => (clone $query)->join('products', 'products.id', '=', 'stocks.product_id')->whereColumn('stocks.quantity_on_hand', '<=', 'products.minimum_stock')->count(),
            'empty_count' => (clone $query)->where('quantity_on_hand', '<=', 0)->count(),
        ];
    }

    /**
     * @param  ReportFilters  $filters
     * @return array{outstanding: string, overdue: string, aging: array<string, string>}
     */
    private function receivableSummary(array $filters, ?int $customerId = null): array
    {
        $query = DB::table('receivables')
            ->when($customerId, fn (Builder $query) => $query->where('customer_id', $customerId))
            ->when($customerId === null, fn (Builder $query) => $query->whereIn('work_location_id', $filters['location_ids']))
            ->whereNotIn('status', [ReceivableStatus::CANCELLED->value, ReceivableStatus::WRITTEN_OFF->value]);

        $aging = (clone $query)
            ->selectRaw('aging_bucket, COALESCE(SUM(outstanding_amount),0) as total')
            ->groupBy('aging_bucket')
            ->pluck('total', 'aging_bucket')
            ->map(fn ($value): string => $this->money($value))
            ->all();

        return [
            'outstanding' => $this->money((clone $query)->sum('outstanding_amount')),
            'overdue' => $this->money((clone $query)->whereDate('due_date', '<', now('Asia/Jakarta')->toDateString())->sum('outstanding_amount')),
            'aging' => $aging,
        ];
    }

    /**
     * @param  ReportFilters  $filters
     * @return list<ReportRow>
     */
    private function dailyRevenue(array $filters, ?string $channel = null): array
    {
        $pos = DB::table('pos_sales')
            ->whereIn('work_location_id', $filters['location_ids'])
            ->whereBetween('completed_at', [$filters['start'], $filters['end']])
            ->whereIn('status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value])
            ->selectRaw('DATE(completed_at) as date, COALESCE(SUM(grand_total_amount),0) as retail, 0 as b2b')
            ->groupBy('date')
            ->get();
        $b2b = $channel === 'retail' ? collect() : DB::table('b2b_orders')
            ->whereBetween('submitted_at', [$filters['start'], $filters['end']])
            ->whereNotIn('status', [B2bOrderStatus::CANCELLED->value, B2bOrderStatus::REJECTED->value])
            ->selectRaw('DATE(submitted_at) as date, 0 as retail, COALESCE(SUM(grand_total_amount),0) as b2b')
            ->groupBy('date')
            ->get();

        return $pos->merge($b2b)
            ->groupBy('date')
            ->map(fn ($items, string $date): array => [
                'date' => $date,
                'retail' => $this->money($items->sum('retail')),
                'b2b' => $this->money($items->sum('b2b')),
                'total' => $this->money($items->sum('retail') + $items->sum('b2b')),
            ])
            ->sortBy('date')
            ->values()
            ->all();
    }

    /**
     * @param  ReportFilters  $filters
     * @return list<ReportRow>
     */
    private function branchMargin(array $filters): array
    {
        return DB::table('pos_sales')
            ->leftJoin('work_locations', 'work_locations.id', '=', 'pos_sales.work_location_id')
            ->whereIn('pos_sales.work_location_id', $filters['location_ids'])
            ->whereBetween('pos_sales.completed_at', [$filters['start'], $filters['end']])
            ->whereIn('pos_sales.status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value])
            ->selectRaw('work_locations.name as label, COALESCE(SUM(pos_sales.grand_total_amount),0) as revenue, COALESCE(SUM(pos_sales.total_margin_amount),0) as margin')
            ->groupBy('work_locations.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row): array => ['label' => $row->label ?: 'Tanpa Lokasi', 'revenue' => $this->money($row->revenue), 'margin' => $this->money($row->margin), 'margin_percent' => $this->percent($this->money($row->margin), $this->money($row->revenue))])
            ->all();
    }

    /**
     * @param  ReportFilters  $filters
     * @return Collection<int, array{sku: mixed, product: mixed, quantity: string, revenue: string}>
     */
    private function topProducts(array $filters): Collection
    {
        return DB::table('pos_sale_items')
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->whereIn('pos_sales.work_location_id', $filters['location_ids'])
            ->whereBetween('pos_sales.completed_at', [$filters['start'], $filters['end']])
            ->whereIn('pos_sales.status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value])
            ->selectRaw('pos_sale_items.sku_snapshot as sku, pos_sale_items.product_name_snapshot as product, COALESCE(SUM(pos_sale_items.base_quantity),0) as quantity, COALESCE(SUM(pos_sale_items.line_total),0) as revenue')
            ->groupBy('pos_sale_items.sku_snapshot', 'pos_sale_items.product_name_snapshot')
            ->orderByDesc('quantity')
            ->limit(20)
            ->get()
            ->map(fn ($row): array => ['sku' => $row->sku, 'product' => $row->product, 'quantity' => $this->quantity($row->quantity), 'revenue' => $this->money($row->revenue)]);
    }

    /**
     * @param  ReportFilters  $filters
     * @return Collection<int, array{sku: mixed, product: mixed, quantity: string, revenue: string}>
     */
    private function fastMovingProducts(array $filters): Collection
    {
        return $this->topProducts($filters)->filter(fn (array $row): bool => Decimal::compare($row['quantity'], '0', 4) > 0);
    }

    /**
     * @param  ReportFilters  $filters
     * @return Collection<int, array{sku: mixed, product: mixed, quantity: string, stock_value: string}>
     */
    private function slowMovingProducts(array $filters): Collection
    {
        $soldProductIds = DB::table('pos_sale_items')
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->whereIn('pos_sales.work_location_id', $filters['location_ids'])
            ->whereBetween('pos_sales.completed_at', [$filters['start'], $filters['end']])
            ->pluck('pos_sale_items.product_id')
            ->unique()
            ->all();

        return DB::table('stocks')
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->whereIn('stocks.work_location_id', $filters['location_ids'])
            ->where('stocks.quantity_on_hand', '>', 0)
            ->when($soldProductIds !== [], fn (Builder $query) => $query->whereNotIn('stocks.product_id', $soldProductIds))
            ->selectRaw('products.sku, products.name as product, COALESCE(SUM(stocks.quantity_on_hand),0) as quantity, COALESCE(SUM(stocks.cost_value),0) as stock_value')
            ->groupBy('products.sku', 'products.name')
            ->orderByDesc('stock_value')
            ->limit(20)
            ->get()
            ->map(fn ($row): array => ['sku' => $row->sku, 'product' => $row->product, 'quantity' => $this->quantity($row->quantity), 'stock_value' => $this->money($row->stock_value)]);
    }

    /**
     * @param  ReportFilters  $filters
     * @return list<ReportRow>
     */
    private function paymentMethods(array $filters): array
    {
        return DB::table('sale_payments')
            ->join('pos_sales', 'pos_sales.id', '=', 'sale_payments.pos_sale_id')
            ->whereIn('pos_sales.work_location_id', $filters['location_ids'])
            ->whereBetween('pos_sales.completed_at', [$filters['start'], $filters['end']])
            ->whereIn('pos_sales.status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value])
            ->selectRaw('sale_payments.method, COALESCE(SUM(sale_payments.amount),0) as amount')
            ->groupBy('sale_payments.method')
            ->get()
            ->map(fn ($row): array => ['label' => $row->method, 'value' => $this->money($row->amount)])
            ->all();
    }

    /**
     * @param  ReportFilters  $filters
     * @return list<ReportRow>
     */
    private function dailyStockMovement(array $filters): array
    {
        return DB::table('stock_mutations')
            ->whereIn('work_location_id', $filters['location_ids'])
            ->whereBetween('occurred_at', [$filters['start'], $filters['end']])
            ->selectRaw('DATE(occurred_at) as date, SUM(CASE WHEN quantity_on_hand_change > 0 THEN quantity_on_hand_change ELSE 0 END) as incoming, SUM(CASE WHEN quantity_on_hand_change < 0 THEN ABS(quantity_on_hand_change) ELSE 0 END) as outgoing')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row): array => ['date' => $row->date, 'incoming' => $this->quantity($row->incoming), 'outgoing' => $this->quantity($row->outgoing)])
            ->all();
    }

    /**
     * @param  ReportFilters  $filters
     * @return list<ReportRow>
     */
    private function largeMutations(array $filters): array
    {
        return DB::table('stock_mutations')
            ->join('products', 'products.id', '=', 'stock_mutations.product_id')
            ->leftJoin('work_locations', 'work_locations.id', '=', 'stock_mutations.work_location_id')
            ->whereIn('stock_mutations.work_location_id', $filters['location_ids'])
            ->whereBetween('stock_mutations.occurred_at', [$filters['start'], $filters['end']])
            ->where(function (Builder $query): void {
                $query->where('quantity_on_hand_change', '>=', 100)
                    ->orWhere('quantity_on_hand_change', '<=', -100);
            })
            ->select('stock_mutations.id', 'stock_mutations.occurred_at', 'stock_mutations.mutation_type', 'stock_mutations.quantity_on_hand_change', 'products.sku', 'products.name as product', 'work_locations.name as location')
            ->latest('stock_mutations.occurred_at')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();
    }

    /** @param ReportFilters $filters */
    private function transactionsToday(array $filters): int
    {
        return DB::table('pos_sales')
            ->whereIn('work_location_id', $filters['location_ids'])
            ->whereDate('completed_at', now('Asia/Jakarta')->toDateString())
            ->whereIn('status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value])
            ->count();
    }

    /** @param ReportFilters $filters */
    private function cashDifference(array $filters): string
    {
        return $this->money(DB::table('cash_shifts')
            ->whereIn('work_location_id', $filters['location_ids'])
            ->whereBetween('closing_submitted_at', [$filters['start'], $filters['end']])
            ->sum('difference_amount'));
    }

    /** @param ReportFilters $filters */
    private function returnsValue(array $filters): string
    {
        $posReturns = DB::table('pos_returns')
            ->whereIn('work_location_id', $filters['location_ids'])
            ->where('status', PosReturnStatus::COMPLETED->value)
            ->whereBetween('completed_at', [$filters['start'], $filters['end']])
            ->sum('refund_amount');
        $warehouseReturns = DB::table('returns')
            ->whereIn('work_location_id', $filters['location_ids'])
            ->whereIn('status', [ReturnStatus::APPROVED->value, ReturnStatus::SETTLED->value])
            ->whereBetween('return_date', [$filters['start_date'], $filters['end_date']])
            ->sum('total_value');

        return Decimal::add($this->money($posReturns), $this->money($warehouseReturns), 2);
    }

    /** @param ReportFilters $filters */
    private function receivableIssued(array $filters, string $channel): string
    {
        return $this->money(DB::table('receivables')
            ->whereIn('work_location_id', $filters['location_ids'])
            ->where('channel', $channel)
            ->whereBetween('issue_date', [$filters['start_date'], $filters['end_date']])
            ->sum('principal_amount'));
    }

    /**
     * @param  ReportFilters  $filters
     * @param  list<string>  $types
     */
    private function mutationCount(array $filters, array $types): int
    {
        return DB::table('stock_mutations')
            ->whereIn('work_location_id', $filters['location_ids'])
            ->whereBetween('occurred_at', [$filters['start'], $filters['end']])
            ->whereIn('mutation_type', $types)
            ->count();
    }

    /**
     * @param  ReportFilters  $filters
     * @param  list<string>  $statuses
     */
    private function locationScopedCount(string $table, string $column, array $filters, array $statuses): int
    {
        return DB::table($table)
            ->whereIn($column, $filters['location_ids'])
            ->whereIn('status', $statuses)
            ->count();
    }

    /** @param ReportFilters $filters */
    private function attendanceCount(array $filters, string $status): int
    {
        return DB::table('attendances')
            ->whereIn('work_location_id', $filters['location_ids'])
            ->whereBetween('attendance_date', [$filters['start_date'], $filters['end_date']])
            ->where('status', $status)
            ->count();
    }

    /** @return list<ReportRow> */
    private function alerts(): array
    {
        return DB::table('anomaly_alerts')
            ->where('status', AnomalyStatus::OPEN->value)
            ->latest('detected_at')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();
    }

    /** @return list<string> */
    public function reportTypes(): array
    {
        return ['daily', 'warehouse', 'retail', 'b2b', 'pricing', 'suppliers', 'attendance', 'receivables', 'audit_notifications'];
    }

    /** @return array<string, string> */
    public function reportLabels(): array
    {
        return [
            'daily' => 'Laporan Harian Owner',
            'warehouse' => 'Laporan Gudang',
            'retail' => 'Laporan Toko',
            'b2b' => 'Laporan Langganan/B2B',
            'pricing' => 'Laporan Harga dan Margin',
            'suppliers' => 'Laporan Supplier',
            'attendance' => 'Laporan Kehadiran',
            'receivables' => 'Laporan Piutang',
            'audit_notifications' => 'Laporan Audit dan Notifikasi',
        ];
    }

    /** @return list<string> */
    public function definitions(string $type): array
    {
        return match ($type) {
            'warehouse' => [
                'Nilai stok berasal dari stocks.cost_value aktif sesuai scope lokasi.',
                'Available = on hand - reserved - damaged dalam unit dasar.',
                'Mutasi masuk/keluar dihitung dari stock_mutations append-only pada periode filter.',
            ],
            'retail' => [
                'Omzet POS hanya menghitung status completed/returned dan mengecualikan void approved.',
                'Margin POS memakai snapshot total_margin_amount transaksi selesai.',
                'Selisih kas memakai difference_amount shift yang sudah submit closing.',
            ],
            'b2b' => [
                'Order B2B mengecualikan cancelled dan rejected.',
                'Invoice belum lunas adalah issued/partial/overdue.',
                'Piutang berasal dari ledger receivables outstanding.',
            ],
            'pricing' => [
                'Histori harga memakai price_histories sebagai audit perubahan.',
                'Approval pending berasal dari price_approval_requests dan approval_requests.',
                'Margin rendah/overpricing ditandai oleh anomaly rule pricing_sensitive.',
            ],
            'attendance' => [
                'Kehadiran dihitung berdasarkan attendance_date timezone Asia/Jakarta.',
                'Status telat/izin/sakit/alfa mengikuti enum attendance.',
                'Produktivitas shift dapat dibandingkan dengan POS/closing berdasarkan lokasi kerja.',
            ],
            'receivables' => [
                'Saldo piutang memakai outstanding_amount dan mengecualikan cancelled/written_off.',
                'Overdue memakai due_date lebih kecil dari tanggal hari ini Asia/Jakarta.',
                'Aging bucket mengikuti nilai tersimpan di receivables.aging_bucket.',
            ],
            'audit_notifications' => [
                'Audit bersifat read-only dan data sensitif sudah direduksi.',
                'Anomali bersifat rule-based, hanya ditinjau/resolve, tidak mengubah data otomatis.',
                'Approval pending berasal dari approval_requests current_status pending.',
            ],
            default => [
                'Omzet adalah POS completed/returned ditambah B2B non-cancelled/non-rejected.',
                'Void/cancelled/rejected tidak dihitung sebagai omzet.',
                'Last updated menunjukkan waktu query dashboard dibuat dengan cache singkat 60 detik.',
            ],
        };
    }

    private function percent(string $value, string $base): string
    {
        if (Decimal::compare($base, '0', 2) === 0) {
            return '0.00';
        }

        return Decimal::mul(Decimal::div($value, $base, 2, 2, 4), '100', 4, 2, 2);
    }

    private function divideMoney(string $value, int $divider): string
    {
        if ($divider < 1) {
            return '0.00';
        }

        return Decimal::div($value, (string) $divider, 2, 0, 2);
    }

    private function money(mixed $value): string
    {
        return Decimal::normalize((string) ($value ?? 0), 2);
    }

    private function quantity(mixed $value): string
    {
        return Decimal::normalize((string) ($value ?? 0), 4);
    }

    /** @param ReportFilters $filters */
    private function cacheKey(string $scope, User $user, array $filters): string
    {
        return 'reports.'.$scope.'.'.($user->id ?? 'system').'.'.md5(json_encode([
            $filters['start_date'],
            $filters['end_date'],
            $filters['work_location_id'],
            $filters['location_ids'],
            $filters['channel'],
        ], JSON_THROW_ON_ERROR));
    }
}
