<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OperationalLocationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user?->can('admin.warehouses.view') && ! $user?->can('admin.branches.view')) {
            abort(403);
        }

        $warehouseStatus = $request->query('warehouse_status');
        $warehouseCity = trim((string) $request->query('warehouse_city'));

        $warehouses = Warehouse::query()
            ->with(['manager', 'workLocation'])
            ->when(! $user->hasUnrestrictedLocationScope(), function ($query) use ($user): void {
                $query->whereIn('work_location_id', $user->permittedWorkLocationIds());
            })
            ->when($warehouseStatus === 'active', fn ($query) => $query->where('is_active', true))
            ->when($warehouseStatus === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($warehouseCity !== '', fn ($query) => $query->where('city', 'like', "%{$warehouseCity}%"))
            ->orderBy('name')
            ->paginate(10, ['*'], 'warehouse_page')
            ->withQueryString();

        $branchStatus = $request->query('branch_status');
        $primaryWarehouseId = $request->query('primary_warehouse_id');

        $branches = Branch::query()
            ->with(['primaryWarehouse', 'manager', 'workLocation'])
            ->when(! $user->hasUnrestrictedLocationScope(), function ($query) use ($user): void {
                $query->whereIn('work_location_id', $user->permittedWorkLocationIds());
            })
            ->when($branchStatus === 'active', fn ($query) => $query->where('is_active', true))
            ->when($branchStatus === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when(is_numeric($primaryWarehouseId), fn ($query) => $query->where('primary_warehouse_id', (int) $primaryWarehouseId))
            ->orderBy('name')
            ->paginate(10, ['*'], 'branch_page')
            ->withQueryString();

        $warehouseKpis = [
            'total' => Warehouse::query()->count(),
            'active' => Warehouse::query()->where('is_active', true)->count(),
        ];

        $branchKpis = [
            'total' => Branch::query()->count(),
            'active' => Branch::query()->where('is_active', true)->count(),
        ];

        $allActiveWarehouses = Warehouse::query()->where('is_active', true)->orderBy('name')->get();

        return view('admin.locations.index', [
            'warehouses' => $warehouses,
            'warehouseStatus' => $warehouseStatus,
            'warehouseCity' => $warehouseCity,
            'warehouseKpis' => $warehouseKpis,
            'branches' => $branches,
            'branchStatus' => $branchStatus,
            'primaryWarehouseId' => $primaryWarehouseId,
            'branchKpis' => $branchKpis,
            'allActiveWarehouses' => $allActiveWarehouses,
        ]);
    }
}
