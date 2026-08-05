<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWarehouseRequest;
use App\Http\Requests\Admin\UpdateWarehouseRequest;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Organization\WorkLocationSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Warehouse::class);

        $status = $request->query('status');
        $city = trim((string) $request->query('city'));

        $warehouses = Warehouse::query()
            ->with('manager')
            ->when(! $request->user()?->hasUnrestrictedLocationScope(), function ($query) use ($request): void {
                $query->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? []);
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($city !== '', fn ($query) => $query->where('city', 'like', "%{$city}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.warehouses.index', compact('warehouses', 'status', 'city'));
    }

    public function create(): View
    {
        $this->authorize('create', Warehouse::class);

        return view('admin.warehouses.create', [
            'warehouse' => new Warehouse(['is_active' => true]),
            'managers' => $this->managerOptions(),
        ]);
    }

    public function store(StoreWarehouseRequest $request, WorkLocationSyncService $sync): RedirectResponse
    {
        $warehouse = DB::transaction(function () use ($request, $sync): Warehouse {
            $data = $request->validated();
            $data['is_active'] = $request->boolean('is_active');
            $warehouse = Warehouse::query()->create($data);
            $sync->syncWarehouse($warehouse);
            $this->audit($request, $warehouse, 'admin.warehouse.created');

            return $warehouse;
        });

        return redirect()->route('admin.warehouses.show', $warehouse)->with('notification', [
            'type' => 'success',
            'message' => 'Gudang berhasil dibuat.',
        ]);
    }

    public function show(Warehouse $warehouse): View
    {
        $this->authorize('view', $warehouse);

        $warehouse->load([
            'manager',
            'branches' => fn ($q) => $q->with('workLocation'),
            'workLocation.users',
            'warehouseLocations' => fn ($q) => $q->with('parent')->orderBy('full_code'),
        ]);

        // 统计仓库的库存数据
        $stockSummary = Stock::query()
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->whereHas('warehouseLocation', fn ($q) => $q->where('warehouse_id', $warehouse->id))
            ->selectRaw('
                COUNT(*) as total_products,
                SUM(stocks.quantity_on_hand) as total_on_hand,
                SUM(stocks.quantity_reserved) as total_reserved,
                SUM(stocks.quantity_damaged) as total_damaged,
                SUM('.Stock::inventoryValueSql().') as total_value
            ')
            ->first();

        // 获取最近的库存变动
        $recentMutations = StockMutation::query()
            ->where('work_location_id', $warehouse->work_location_id)
            ->where('warehouse_location_id', function ($q) use ($warehouse) {
                $q->select('id')->from('warehouse_locations')->where('warehouse_id', $warehouse->id);
            })
            ->with(['product', 'warehouseLocation', 'actor'])
            ->orderBy('occurred_at', 'desc')
            ->limit(20)
            ->get();

        // 获取仓库的所有用户
        $warehouseUsers = $warehouse->workLocation->users;

        // 统计各类型仓库位置数量
        $locationStats = WarehouseLocation::query()
            ->where('warehouse_id', $warehouse->id)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->get();

        return view('admin.warehouses.show', compact(
            'warehouse',
            'stockSummary',
            'recentMutations',
            'warehouseUsers',
            'locationStats'
        ));
    }

    public function edit(Warehouse $warehouse): View
    {
        $this->authorize('update', $warehouse);

        return view('admin.warehouses.edit', [
            'warehouse' => $warehouse,
            'managers' => $this->managerOptions(),
        ]);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse, WorkLocationSyncService $sync): RedirectResponse
    {
        DB::transaction(function () use ($request, $warehouse, $sync): void {
            $data = $request->validated();
            $data['is_active'] = $request->boolean('is_active');
            $warehouse->fill($data)->save();
            $sync->syncWarehouse($warehouse);
            $this->audit($request, $warehouse, 'admin.warehouse.updated');
        });

        return redirect()->route('admin.warehouses.show', $warehouse)->with('notification', [
            'type' => 'success',
            'message' => 'Gudang berhasil diperbarui.',
        ]);
    }

    public function deactivate(Request $request, Warehouse $warehouse, WorkLocationSyncService $sync): RedirectResponse
    {
        $this->authorize('update', $warehouse);

        DB::transaction(function () use ($request, $warehouse, $sync): void {
            $warehouse->forceFill(['is_active' => false])->save();
            $sync->syncWarehouse($warehouse);
            $this->audit($request, $warehouse, 'admin.warehouse.deactivated');
        });

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Gudang berhasil dinonaktifkan.',
        ]);
    }

    private function managerOptions(): mixed
    {
        return User::query()->where('is_active', true)->orderBy('name')->get();
    }

    private function audit(Request $request, Warehouse $warehouse, string $event): void
    {
        activity()->causedBy($request->user())->performedOn($warehouse)->log($event);
    }
}
