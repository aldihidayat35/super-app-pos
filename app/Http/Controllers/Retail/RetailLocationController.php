<?php

namespace App\Http\Controllers\Retail;

use App\Enums\WarehouseLocationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreWarehouseLocationRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseLocationRequest;
use App\Models\Branch;
use App\Models\WarehouseLocation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RetailLocationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', WarehouseLocation::class);
        $branchId = $request->integer('branch_id');
        $status = $request->query('status');

        $branches = $this->branches($request);
        $permittedLocationIds = $request->user()?->permittedWorkLocationIds() ?? [];

        $locations = WarehouseLocation::query()
            ->with(['branch.workLocation', 'parent'])
            ->whereNotNull('branch_id')
            ->forWorkLocationIds($permittedLocationIds)
            ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('full_code')
            ->paginate(20)
            ->withQueryString();

        return view('retail.locations.index', compact('locations', 'branches', 'branchId', 'status'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', WarehouseLocation::class);
        $branches = $this->branches($request);
        $firstBranch = $branches->first();
        $defaultBranchId = $request->integer('branch_id') ?: ($firstBranch instanceof Branch ? $firstBranch->id : null);

        return view('retail.locations.create', [
            'location' => new WarehouseLocation([
                'branch_id' => $defaultBranchId,
                'type' => WarehouseLocationType::ZONE,
                'is_active' => true,
            ]),
            'branches' => $branches,
            'parents' => $this->branchParents($request),
            'types' => WarehouseLocationType::options(),
        ]);
    }

    public function store(StoreWarehouseLocationRequest $request): RedirectResponse
    {
        $location = DB::transaction(function () use ($request): WarehouseLocation {
            $data = $request->validated();
            $branchId = (int) ($data['branch_id'] ?? 0);

            if ($branchId <= 0) {
                throw ValidationException::withMessages(['branch_id' => 'Pilih toko / cabang tujuan.']);
            }

            $this->ensureBranchScope($request, $branchId);
            $data['warehouse_id'] = null;
            $data['branch_id'] = $branchId;
            $data['is_active'] = $request->boolean('is_active');
            $data['full_code'] = $this->fullCode($branchId, $data['parent_id'] ?? null, $data['code']);

            $location = WarehouseLocation::query()->create($data);
            activity()->causedBy($request->user())->performedOn($location)->log('retail.location.created');

            return $location;
        });

        return redirect()->route('retail.locations.index')->with('notification', ['type' => 'success', 'message' => "Lokasi toko {$location->full_code} berhasil dibuat."]);
    }

    public function edit(Request $request, WarehouseLocation $location): View
    {
        $this->authorize('update', $location);
        abort_unless($location->branch_id !== null, 404);
        $this->ensureBranchScope($request, (int) $location->branch_id);

        return view('retail.locations.edit', [
            'location' => $location,
            'branches' => $this->branches($request),
            'parents' => $this->branchParents($request, $location->id),
            'types' => WarehouseLocationType::options(),
        ]);
    }

    public function update(UpdateWarehouseLocationRequest $request, WarehouseLocation $location): RedirectResponse
    {
        $this->authorize('update', $location);
        abort_unless($location->branch_id !== null, 404);

        DB::transaction(function () use ($request, $location): void {
            $data = $request->validated();
            $branchId = (int) ($data['branch_id'] ?? $location->branch_id);
            $this->ensureBranchScope($request, $branchId);

            $data['warehouse_id'] = null;
            $data['branch_id'] = $branchId;
            $data['is_active'] = $request->boolean('is_active');
            $data['full_code'] = $this->fullCode($branchId, $data['parent_id'] ?? null, $data['code']);
            $location->fill($data)->save();

            activity()->causedBy($request->user())->performedOn($location)->log('retail.location.updated');
        });

        return redirect()->route('retail.locations.index')->with('notification', ['type' => 'success', 'message' => 'Lokasi toko berhasil diperbarui.']);
    }

    public function deactivate(Request $request, WarehouseLocation $location): RedirectResponse
    {
        $this->authorize('update', $location);
        abort_unless($location->branch_id !== null, 404);
        $this->ensureBranchScope($request, (int) $location->branch_id);

        $location->forceFill(['is_active' => false])->save();
        activity()->causedBy($request->user())->performedOn($location)->log('retail.location.deactivated');

        return back()->with('notification', ['type' => 'success', 'message' => 'Lokasi toko berhasil dinonaktifkan.']);
    }

    private function fullCode(int $branchId, ?int $parentId, string $code): string
    {
        if ($parentId) {
            $parent = WarehouseLocation::query()->findOrFail($parentId);
            $prefix = $parent->full_code;
        } else {
            $branch = Branch::query()->findOrFail($branchId);
            $prefix = $branch->code;
        }

        return strtoupper($prefix.'-'.$code);
    }

    private function ensureBranchScope(Request $request, int $branchId): void
    {
        $branch = Branch::query()->findOrFail($branchId);

        if ($branch->work_location_id !== null && ! $request->user()?->canAccessWorkLocation((int) $branch->work_location_id)) {
            throw ValidationException::withMessages(['branch_id' => 'Anda tidak memiliki akses ke toko ini.']);
        }
    }

    private function branches(Request $request): mixed
    {
        return Branch::query()
            ->where('is_active', true)
            ->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? [])
            ->orderBy('name')
            ->get();
    }

    private function branchParents(Request $request, ?int $exceptId = null): mixed
    {
        return WarehouseLocation::query()
            ->where('is_active', true)
            ->whereNotNull('branch_id')
            ->forWorkLocationIds($request->user()?->permittedWorkLocationIds() ?? [])
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->orderBy('full_code')
            ->get();
    }
}
