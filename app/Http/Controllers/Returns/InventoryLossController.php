<?php

namespace App\Http\Controllers\Returns;

use App\Http\Controllers\Controller;
use App\Http\Requests\Returns\StoreInventoryLossRequest;
use App\Models\InventoryLoss;
use App\Models\Product;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Returns\ReturnService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InventoryLossController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', InventoryLoss::class);

        return view('warehouse.losses.index', [
            'losses' => InventoryLoss::query()
                ->with(['workLocation', 'warehouseLocation', 'product'])
                ->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? [])
                ->when($request->filled('loss_type'), fn ($query) => $query->where('loss_type', $request->query('loss_type')))
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
                ->latest('reported_at')
                ->paginate(15)
                ->withQueryString(),
            'workLocations' => WorkLocation::query()->whereIn('id', $request->user()?->permittedWorkLocationIds() ?? [])->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->where('status', 'active')->orderBy('name')->limit(500)->get(),
            'warehouseLocations' => WarehouseLocation::query()->where('is_active', true)->orderBy('full_code')->limit(300)->get(),
            'filters' => $request->only(['loss_type', 'status']),
        ]);
    }

    public function store(StoreInventoryLossRequest $request, ReturnService $service): RedirectResponse
    {
        $data = $request->validated();
        abort_unless($request->user()?->canAccessWorkLocation((int) $data['work_location_id']), 403);
        if ($request->hasFile('evidence')) {
            $data['evidence_path'] = $request->file('evidence')?->store('losses', 'public');
        }

        $loss = $service->createLoss($data, $request->user());

        return back()->with('notification', ['type' => 'success', 'message' => "Loss {$loss->number} berhasil dicatat."]);
    }

    public function approve(Request $request, InventoryLoss $loss, ReturnService $service): RedirectResponse
    {
        $this->authorize('approve', $loss);
        $service->approveLoss($loss, $request->user());

        return back()->with('notification', ['type' => 'success', 'message' => 'Loss berhasil disetujui.']);
    }
}
