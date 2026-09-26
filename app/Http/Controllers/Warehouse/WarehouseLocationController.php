<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\WarehouseLocationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreWarehouseLocationRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseLocationRequest;
use App\Models\Branch;
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
        $ownerType = $request->query('owner_type');
        $warehouseId = $request->integer('warehouse_id');
        $branchId = $request->integer('branch_id');
        $status = $request->query('status');

        $warehouses = $this->warehouses($request);
        $branches = $this->branches($request);
        $permittedLocationIds = $request->user()?->permittedWorkLocationIds() ?? [];

        $locations = WarehouseLocation::query()
            ->with(['warehouse.workLocation', 'branch.workLocation', 'parent'])
            ->forWorkLocationIds($permittedLocationIds)
            ->when($ownerType === 'warehouse', fn ($query) => $query->whereNotNull('warehouse_id'))
            ->when($ownerType === 'branch', fn ($query) => $query->whereNotNull('branch_id'))
            ->when($warehouseId > 0, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('full_code')
            ->paginate(20)
            ->withQueryString();

        return view('warehouse.locations.index', compact('locations', 'warehouses', 'branches', 'ownerType', 'warehouseId', 'branchId', 'status'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', WarehouseLocation::class);
        $ownerType = $request->query('owner_type', 'warehouse');
        $selectedWarehouseId = $request->integer('warehouse_id') ?: null;
        $selectedBranchId = $request->integer('branch_id') ?: null;

        return view('warehouse.locations.create', [
            'location' => new WarehouseLocation([
                'warehouse_id' => $selectedWarehouseId,
                'branch_id' => $selectedBranchId,
                'type' => WarehouseLocationType::ZONE,
                'is_active' => true,
            ]),
            'ownerType' => $ownerType,
            'warehouses' => $this->warehouses($request),
            'branches' => $this->branches($request),
            'parents' => $this->parents($request),
            'types' => WarehouseLocationType::options(),
        ]);
    }

    public function store(StoreWarehouseLocationRequest $request): RedirectResponse
    {
        $location = DB::transaction(function () use ($request): WarehouseLocation {
            $data = $request->validated();
            $ownerType = $data['owner_type'] ?? (! empty($data['branch_id']) ? 'branch' : 'warehouse');

            if ($ownerType === 'branch') {
                $branchId = (int) $data['branch_id'];
                $this->ensureBranchScope($request, $branchId);
                $data['warehouse_id'] = null;
                $data['branch_id'] = $branchId;
            } else {
                $warehouseId = (int) $data['warehouse_id'];
                $this->ensureWarehouseScope($request, $warehouseId);
                $data['warehouse_id'] = $warehouseId;
                $data['branch_id'] = null;
            }

            unset($data['owner_type']);
            $data['is_active'] = $request->boolean('is_active');
            $data['full_code'] = $this->fullCode($data['warehouse_id'], $data['branch_id'], $data['parent_id'] ?? null, $data['code']);

            $location = WarehouseLocation::query()->create($data);
            activity()->causedBy($request->user())->performedOn($location)->log('warehouse.location.created');

            return $location;
        });

        return redirect()->route('warehouse.locations.index')->with('notification', ['type' => 'success', 'message' => "Lokasi {$location->full_code} berhasil dibuat."]);
    }

    public function edit(Request $request, WarehouseLocation $location): View
    {
        $this->authorize('update', $location);
        $ownerType = $location->branch_id !== null ? 'branch' : 'warehouse';

        return view('warehouse.locations.edit', [
            'location' => $location,
            'ownerType' => $ownerType,
            'warehouses' => $this->warehouses($request),
            'branches' => $this->branches($request),
            'parents' => $this->parents($request, $location->id),
            'types' => WarehouseLocationType::options(),
        ]);
    }

    public function update(UpdateWarehouseLocationRequest $request, WarehouseLocation $location): RedirectResponse
    {
        $this->authorize('update', $location);

        DB::transaction(function () use ($request, $location): void {
            $data = $request->validated();
            $ownerType = $data['owner_type'] ?? (! empty($data['branch_id']) ? 'branch' : 'warehouse');

            if ($ownerType === 'branch') {
                $branchId = (int) $data['branch_id'];
                $this->ensureBranchScope($request, $branchId);
                $data['warehouse_id'] = null;
                $data['branch_id'] = $branchId;
            } else {
                $warehouseId = (int) $data['warehouse_id'];
                $this->ensureWarehouseScope($request, $warehouseId);
                $data['warehouse_id'] = $warehouseId;
                $data['branch_id'] = null;
            }

            unset($data['owner_type']);
            $data['is_active'] = $request->boolean('is_active');
            $data['full_code'] = $this->fullCode($data['warehouse_id'], $data['branch_id'], $data['parent_id'] ?? null, $data['code']);
            $location->fill($data)->save();
            activity()->causedBy($request->user())->performedOn($location)->log('warehouse.location.updated');
        });

        return redirect()->route('warehouse.locations.index')->with('notification', ['type' => 'success', 'message' => 'Lokasi berhasil diperbarui.']);
    }

    public function deactivate(Request $request, WarehouseLocation $location): RedirectResponse
    {
        $this->authorize('update', $location);
        $location->forceFill(['is_active' => false])->save();
        activity()->causedBy($request->user())->performedOn($location)->log('warehouse.location.deactivated');

        return back()->with('notification', ['type' => 'success', 'message' => 'Lokasi berhasil dinonaktifkan.']);
    }

    private function fullCode(?int $warehouseId, ?int $branchId, ?int $parentId, string $code): string
    {
        if ($parentId) {
            $parent = WarehouseLocation::query()->findOrFail($parentId);
            $prefix = $parent->full_code;
        } elseif ($branchId) {
            $branch = Branch::query()->findOrFail($branchId);
            $prefix = $branch->code;
        } else {
            $warehouse = Warehouse::query()->findOrFail((int) $warehouseId);
            $prefix = $warehouse->code;
        }

        return strtoupper($prefix.'-'.$code);
    }

    private function ensureWarehouseScope(Request $request, int $warehouseId): void
    {
        $warehouse = Warehouse::query()->findOrFail($warehouseId);

        if ($warehouse->work_location_id !== null && ! $request->user()?->canAccessWorkLocation((int) $warehouse->work_location_id)) {
            throw ValidationException::withMessages(['warehouse_id' => 'Anda tidak memiliki akses ke gudang ini.']);
        }
    }

    private function ensureBranchScope(Request $request, int $branchId): void
    {
        $branch = Branch::query()->findOrFail($branchId);

        if ($branch->work_location_id !== null && ! $request->user()?->canAccessWorkLocation((int) $branch->work_location_id)) {
            throw ValidationException::withMessages(['branch_id' => 'Anda tidak memiliki akses ke toko ini.']);
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

    private function branches(Request $request): mixed
    {
        return Branch::query()
            ->where('is_active', true)
            ->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? [])
            ->orderBy('name')
            ->get();
    }

    private function parents(Request $request, ?int $exceptId = null): mixed
    {
        return WarehouseLocation::query()
            ->where('is_active', true)
            ->forWorkLocationIds($request->user()?->permittedWorkLocationIds() ?? [])
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->orderBy('full_code')
            ->get();
    }
}
