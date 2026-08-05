<?php

namespace App\Http\Controllers\Returns;

use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Returns\StoreInventoryLossRequest;
use App\Models\InventoryLoss;
use App\Models\Product;
use App\Models\Stock;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Returns\ReturnService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryLossController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', InventoryLoss::class);

        $losses = InventoryLoss::query()
            ->with(['workLocation', 'warehouseLocation', 'product'])
            ->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? [])
            ->when($request->filled('loss_type'), fn ($query) => $query->where('loss_type', $request->query('loss_type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->latest('reported_at')
            ->paginate(15)
            ->withQueryString();
        $availability = Stock::query()
            ->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? [])
            ->whereIn('product_id', $losses->getCollection()->pluck('product_id'))
            ->get()
            ->keyBy(fn (Stock $stock): string => $stock->product_id.'|'.$stock->work_location_id.'|'.($stock->warehouse_location_id ?? 'null'))
            ->map(fn (Stock $stock): string => $stock->available_quantity);

        return view('warehouse.losses.index', [
            'losses' => $losses,
            'availability' => $availability,
            'workLocations' => WorkLocation::query()->whereIn('id', $request->user()?->permittedWorkLocationIds() ?? [])->where('is_active', true)->orderBy('name')->get(),
            'selectedProduct' => Product::query()->where('status', 'active')->find(old('product_id')),
            'selectedWarehouseLocation' => WarehouseLocation::query()->where('is_active', true)->find(old('warehouse_location_id')),
            'filters' => $request->only(['loss_type', 'status']),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['locations', 'products'])],
            'work_location_id' => ['required', 'integer', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'warehouse_location_id' => ['nullable', 'integer', Rule::exists('warehouse_locations', 'id')->where('is_active', true)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $workLocationId = (int) $data['work_location_id'];
        abort_unless($request->user()?->canAccessWorkLocation($workLocationId), 403);
        $search = trim((string) ($data['q'] ?? ''));

        if ($data['type'] === 'locations') {
            $locations = WarehouseLocation::query()
                ->where('is_active', true)
                ->whereHas('warehouse', fn ($query) => $query->where('work_location_id', $workLocationId))
                ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search): void {
                    $inner->where('full_code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%");
                }))
                ->orderBy('full_code')->limit(300)->get();

            return response()->json(['results' => $locations->map(fn (WarehouseLocation $location): array => ['id' => $location->id, 'text' => $location->full_code.' — '.$location->name])->values()]);
        }

        $warehouseLocationId = filled($data['warehouse_location_id'] ?? null) ? (int) $data['warehouse_location_id'] : null;
        if ($warehouseLocationId !== null) {
            abort_unless(WarehouseLocation::query()->whereKey($warehouseLocationId)->whereHas('warehouse', fn ($query) => $query->where('work_location_id', $workLocationId))->exists(), 422);
        }

        $stocks = Stock::query()
            ->select('stocks.*')->join('products', 'products.id', '=', 'stocks.product_id')->with('product.baseUnit')
            ->where('stocks.work_location_id', $workLocationId)
            ->when($warehouseLocationId === null, fn ($query) => $query->whereNull('stocks.warehouse_location_id'), fn ($query) => $query->where('stocks.warehouse_location_id', $warehouseLocationId))
            ->where('products.status', 'active')
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search): void {
                $inner->where('products.sku', 'like', "%{$search}%")->orWhere('products.name', 'like', "%{$search}%");
            }))
            ->orderBy('products.name')->limit(500)->get();

        return response()->json(['results' => $stocks->map(fn (Stock $stock): array => [
            'id' => $stock->product_id,
            'text' => $stock->product->sku.' — '.$stock->product->name,
            'cost_price' => (string) $stock->product->cost_price,
            'available' => $stock->available_quantity,
            'unit' => $stock->product->baseUnit?->symbol ?: 'unit',
        ])->values()]);
    }

    public function store(StoreInventoryLossRequest $request, ReturnService $service): RedirectResponse
    {
        $data = $request->validated();
        abort_unless($request->user()?->canAccessWorkLocation((int) $data['work_location_id']), 403);
        if ($request->hasFile('evidence')) {
            $data['evidence_path'] = $request->file('evidence')?->store('losses', 'public');
        }

        try {
            $loss = $service->createLoss($data, $request->user());
        } catch (ServiceException $exception) {
            return back()->withInput()->withErrors(['loss' => $exception->getMessage()])
                ->with('notification', ['type' => 'danger', 'message' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => "Loss {$loss->number} berhasil dicatat."]);
    }

    public function approve(Request $request, InventoryLoss $loss, ReturnService $service): RedirectResponse
    {
        $this->authorize('approve', $loss);
        try {
            $service->approveLoss($loss, $request->user());
        } catch (ServiceException $exception) {
            return back()->with('notification', ['type' => 'danger', 'message' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Loss berhasil disetujui.']);
    }
}
