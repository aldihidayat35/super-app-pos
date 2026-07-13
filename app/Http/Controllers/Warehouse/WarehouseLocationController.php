<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\WarehouseLocationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreWarehouseLocationRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseLocationRequest;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseLocationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', WarehouseLocation::class);
        $warehouseId = $request->integer('warehouse_id');
        $status = $request->query('status');

        $warehouses = $this->warehouses($request);
        $locations = WarehouseLocation::query()
            ->with(['warehouse.workLocation', 'parent'])
            ->whereHas('warehouse', fn ($query) => $query->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? []))
            ->when($warehouseId > 0, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('full_code')
            ->paginate(20)
            ->withQueryString();

        return view('warehouse.locations.index', compact('locations', 'warehouses', 'warehouseId', 'status'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', WarehouseLocation::class);

        return view('warehouse.locations.create', [
            'location' => new WarehouseLocation(['type' => WarehouseLocationType::ZONE, 'is_active' => true]),
            'warehouses' => $this->warehouses($request),
            'parents' => $this->parents($request),
            'types' => WarehouseLocationType::options(),
        ]);
    }

    public function store(StoreWarehouseLocationRequest $request): RedirectResponse
    {
        $location = DB::transaction(function () use ($request): WarehouseLocation {
            $data = $request->validated();
            $this->ensureWarehouseScope($request, (int) $data['warehouse_id']);
            $data['is_active'] = $request->boolean('is_active');
            $data['full_code'] = $this->fullCode($data['warehouse_id'], $data['parent_id'] ?? null, $data['code']);

            $location = WarehouseLocation::query()->create($data);
            activity()->causedBy($request->user())->performedOn($location)->log('warehouse.location.created');

            return $location;
        });

        return redirect()->route('warehouse.locations.index')->with('notification', ['type' => 'success', 'message' => "Lokasi {$location->full_code} berhasil dibuat."]);
    }

    public function edit(Request $request, WarehouseLocation $location): View
    {
        $this->authorize('update', $location);

        return view('warehouse.locations.edit', [
            'location' => $location,
            'warehouses' => $this->warehouses($request),
            'parents' => $this->parents($request, $location->id),
            'types' => WarehouseLocationType::options(),
        ]);
    }

    public function update(UpdateWarehouseLocationRequest $request, WarehouseLocation $location): RedirectResponse
    {
        $this->authorize('update', $location);

        DB::transaction(function () use ($request, $location): void {
            $data = $request->validated();
            $this->ensureWarehouseScope($request, (int) $data['warehouse_id']);
            $data['is_active'] = $request->boolean('is_active');
            $data['full_code'] = $this->fullCode($data['warehouse_id'], $data['parent_id'] ?? null, $data['code']);
            $location->fill($data)->save();
            activity()->causedBy($request->user())->performedOn($location)->log('warehouse.location.updated');
        });

        return redirect()->route('warehouse.locations.index')->with('notification', ['type' => 'success', 'message' => 'Lokasi gudang berhasil diperbarui.']);
    }

    public function deactivate(Request $request, WarehouseLocation $location): RedirectResponse
    {
        $this->authorize('update', $location);
        $location->forceFill(['is_active' => false])->save();
        activity()->causedBy($request->user())->performedOn($location)->log('warehouse.location.deactivated');

        return back()->with('notification', ['type' => 'success', 'message' => 'Lokasi gudang berhasil dinonaktifkan.']);
    }

    private function fullCode(int $warehouseId, ?int $parentId, string $code): string
    {
        $warehouse = Warehouse::query()->findOrFail($warehouseId);
        $prefix = $parentId ? WarehouseLocation::query()->findOrFail($parentId)->full_code : $warehouse->code;

        return strtoupper($prefix.'-'.$code);
    }

    private function ensureWarehouseScope(Request $request, int $warehouseId): void
    {
        $warehouse = Warehouse::query()->findOrFail($warehouseId);

        if ($warehouse->work_location_id !== null && ! $request->user()?->canAccessWorkLocation((int) $warehouse->work_location_id)) {
            throw ValidationException::withMessages(['warehouse_id' => 'Anda tidak memiliki akses ke gudang ini.']);
        }
    }

    private function warehouses(Request $request): mixed
    {
        return Warehouse::query()
            ->where('is_active', true)
            ->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? [])
            ->orderBy('name')
            ->get();
    }

    private function parents(Request $request, ?int $exceptId = null): mixed
    {
        return WarehouseLocation::query()
            ->where('is_active', true)
            ->whereHas('warehouse', fn ($query) => $query->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? []))
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->orderBy('full_code')
            ->get();
    }
}
