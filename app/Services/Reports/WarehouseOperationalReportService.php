<?php

namespace App\Services\Reports;

use App\Support\Decimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class WarehouseOperationalReportService
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public function build(array $filters, array $base, bool $canViewSensitive): array
    {
        $locationIds = array_map('intval', $filters['location_ids']);
        $summary = $base['summary'] ?? [];
        $chartData = data_get($base, 'charts.movement', []);
        $movements = collect(is_array($chartData) ? $chartData : [])->values();
        $inventoryRows = $base['rows'] ?? [];
        $inventoryRows = is_array($inventoryRows) ? $inventoryRows : [];
        $layerValue = (string) DB::table('stock_batches')
            ->whereIn('work_location_id', $locationIds)
            ->where('status', 'active')
            ->selectRaw('COALESCE(SUM(quantity_on_hand * cost_price), 0) as value')
            ->value('value');

        $mutationTotal = DB::table('stock_mutations')
            ->whereIn('work_location_id', $locationIds)
            ->whereBetween('occurred_at', [$filters['start'], $filters['end']])
            ->count();

        $receiptQuery = DB::table('goods_receipts')
            ->whereIn('destination_work_location_id', $locationIds)
            ->where('status', 'posted')
            ->whereBetween('posted_at', [$filters['start'], $filters['end']]);
        $receiptCount = (clone $receiptQuery)->count();
        $receiptValue = (string) (clone $receiptQuery)
            ->join('goods_receipt_items', 'goods_receipt_items.goods_receipt_id', '=', 'goods_receipts.id')
            ->selectRaw('COALESCE(SUM(goods_receipt_items.quantity_accepted * COALESCE(goods_receipt_items.actual_unit_cost, goods_receipt_items.unit_price, 0)), 0) as value')
            ->value('value');

        $suppliers = (clone $receiptQuery)
            ->join('suppliers', 'suppliers.id', '=', 'goods_receipts.supplier_id')
            ->join('goods_receipt_items', 'goods_receipt_items.goods_receipt_id', '=', 'goods_receipts.id')
            ->selectRaw('suppliers.id, suppliers.name, SUM(goods_receipt_items.quantity_accepted * COALESCE(goods_receipt_items.actual_unit_cost, goods_receipt_items.unit_price, 0)) as value')
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('value')->limit(4)->get()->map(fn ($row): array => [
                'name' => (string) $row->name,
                'value' => (string) $row->value,
            ])->all();

        $transferRows = DB::table('stock_transfers')
            ->leftJoin('stock_transfer_items', 'stock_transfer_items.stock_transfer_id', '=', 'stock_transfers.id')
            ->where(function (Builder $query) use ($locationIds): void {
                $query->whereIn('source_work_location_id', $locationIds)
                    ->orWhereIn('destination_work_location_id', $locationIds);
            })
            ->whereBetween('transfer_date', [$filters['start_date'], $filters['end_date']])
            ->selectRaw('DATE(transfer_date) as date,
                SUM(CASE WHEN destination_work_location_id IN ('.implode(',', $locationIds).') THEN COALESCE(quantity_received, 0) ELSE 0 END) as incoming,
                SUM(CASE WHEN source_work_location_id IN ('.implode(',', $locationIds).') THEN COALESCE(quantity_shipped, 0) ELSE 0 END) as outgoing')
            ->groupBy('date')->orderBy('date')->get()->map(fn ($row): array => [
                'date' => (string) $row->date,
                'incoming' => Decimal::normalize((string) $row->incoming),
                'outgoing' => Decimal::normalize((string) $row->outgoing),
            ])->all();

        $completedOpnames = DB::table('stock_opnames')->whereIn('work_location_id', $locationIds)
            ->where('status', 'completed')->whereBetween('completed_at', [$filters['start'], $filters['end']]);
        $correctionCount = (clone $completedOpnames)->whereRaw('ABS(total_difference_qty) > 0')->count();
        $lossQuery = DB::table('inventory_losses')->whereIn('work_location_id', $locationIds)
            ->whereIn('status', ['approved', 'posted', 'completed'])
            ->whereBetween('approved_at', [$filters['start'], $filters['end']]);
        $lossCount = (clone $lossQuery)->count();
        $lossValue = (string) (clone $lossQuery)->sum('loss_value');

        $teamQuery = DB::table('employees')->whereIn('work_location_id', $locationIds)
            ->where('is_active', true)->whereNull('deleted_at');
        $teamCount = (clone $teamQuery)->count();
        $team = (clone $teamQuery)->select('id', 'name', 'position')->orderBy('name')->limit(6)->get();
        $attendance = DB::table('attendances')->whereIn('work_location_id', $locationIds)
            ->whereBetween('attendance_date', [$filters['start_date'], $filters['end_date']]);
        $presentEmployeeIds = (clone $attendance)->whereIn('status', ['present', 'late'])->distinct()->pluck('employee_id');
        $lateCount = (clone $attendance)->where(function (Builder $query): void {
            $query->where('status', 'late')->orWhere('late_minutes', '>', 0);
        })->count();

        return [
            'summary' => [
                'total_products' => (int) ($summary['total_products'] ?? 0),
                'available_quantity' => (string) ($summary['available_quantity'] ?? '0'),
                'layer_value' => $canViewSensitive ? $layerValue : null,
                'mutation_count' => $mutationTotal,
                'receipt_count' => $receiptCount,
                'supplier_count' => count($suppliers),
            ],
            'movements' => $movements->all(),
            'stock_status' => [
                'normal' => max(0, (int) ($summary['total_products'] ?? 0) - (int) ($summary['critical_count'] ?? 0) - (int) ($summary['empty_count'] ?? 0)),
                'critical' => (int) ($summary['critical_count'] ?? 0),
                'empty' => (int) ($summary['empty_count'] ?? 0),
            ],
            'inventory_rows' => collect($inventoryRows)->sortByDesc(fn (array $row): string => (string) ($row['available'] ?? '0'))->take(5)->values()->all(),
            'alerts' => array_slice(data_get($base, 'sections.alerts', []), 0, 5),
            'restock' => array_slice(data_get($base, 'sections.restock_needed', []), 0, 5),
            'transfers' => $transferRows,
            'inbound' => ['count' => $receiptCount, 'value' => $canViewSensitive ? $receiptValue : null, 'suppliers' => $suppliers],
            'controls' => ['corrections' => $correctionCount, 'loss_count' => $lossCount, 'loss_value' => $canViewSensitive ? $lossValue : null],
            'team' => [
                'total' => $teamCount, 'present' => $presentEmployeeIds->count(), 'late' => $lateCount,
                'rows' => $team->map(fn ($employee): array => [
                    'name' => (string) $employee->name,
                    'position' => (string) ($employee->position ?: '-'),
                    'status' => $presentEmployeeIds->contains($employee->id) ? 'Hadir' : 'Belum tercatat',
                ])->all(),
            ],
        ];
    }
}
