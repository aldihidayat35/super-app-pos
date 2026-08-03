<?php

namespace App\Http\Controllers\Warehouse;

use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreLocationTransferRequest;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LocationTransferController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockMutation::class);
        $permittedLocationIds = $request->user()?->permittedWorkLocationIds() ?? [];

        return view('warehouse.location-transfers.index', [
            'transfers' => StockMutation::query()
                ->with(['product', 'workLocation', 'warehouseLocation', 'actor'])
                ->whereIn('work_location_id', $permittedLocationIds)
                ->whereIn('mutation_type', ['transfer_out', 'transfer_in'])
                ->latest('occurred_at')
                ->paginate(20),
            'workLocations' => WorkLocation::query()->whereIn('id', $permittedLocationIds)->where('is_active', true)->orderBy('name')->get(),
            'selectedSourceWarehouseLocation' => $this->selectedWarehouseLocation($request, 'source_warehouse_location_id', $permittedLocationIds),
            'selectedDestinationWarehouseLocation' => $this->selectedWarehouseLocation($request, 'destination_warehouse_location_id', $permittedLocationIds),
            'selectedProduct' => Product::query()->where('status', 'active')->find($request->old('product_id')),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['locations', 'products'])],
            'work_location_id' => ['required', 'integer', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'warehouse_location_id' => ['nullable', 'integer', Rule::exists('warehouse_locations', 'id')->where('is_active', true)],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $workLocationId = (int) $data['work_location_id'];
        $this->ensureScope($request, $workLocationId, 'work_location_id');

        if ($data['type'] === 'locations') {
            return $this->warehouseLocationOptions($request, $workLocationId);
        }

        $warehouseLocationId = (int) ($data['warehouse_location_id'] ?? 0);

        if ($warehouseLocationId <= 0) {
            return response()->json(['results' => [], 'pagination' => ['more' => false]]);
        }

        $this->ensureWarehouseLocationBelongsToWorkLocation($warehouseLocationId, $workLocationId);

        return $this->productOptions($request, $workLocationId, $warehouseLocationId);
    }

    public function store(StoreLocationTransferRequest $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureScope($request, (int) $data['source_work_location_id'], 'source_work_location_id');
        $this->ensureScope($request, (int) $data['destination_work_location_id'], 'destination_work_location_id');

        $product = Product::query()->findOrFail($data['product_id']);
        $sourceWorkLocation = WorkLocation::query()->findOrFail($data['source_work_location_id']);
        $destinationWorkLocation = WorkLocation::query()->findOrFail($data['destination_work_location_id']);
        $sourceWarehouseLocation = filled($data['source_warehouse_location_id'] ?? null) ? WarehouseLocation::query()->findOrFail($data['source_warehouse_location_id']) : null;
        $destinationWarehouseLocation = filled($data['destination_warehouse_location_id'] ?? null) ? WarehouseLocation::query()->findOrFail($data['destination_warehouse_location_id']) : null;

        try {
            $inventory->transferInternal(
                product: $product,
                sourceWorkLocation: $sourceWorkLocation,
                sourceWarehouseLocation: $sourceWarehouseLocation,
                destinationWorkLocation: $destinationWorkLocation,
                destinationWarehouseLocation: $destinationWarehouseLocation,
                quantity: $data['quantity'],
                actor: $request->user(),
                reference: ['type' => 'location_transfer', 'no' => 'TRF-'.now()->format('YmdHis')],
                reason: $data['reason'],
                idempotencyKey: $data['idempotency_key'] ?? (string) str()->uuid(),
            );
        } catch (ServiceException $exception) {
            return back()
                ->withInput()
                ->withErrors(['transfer' => $exception->getMessage()])
                ->with('notification', ['type' => 'danger', 'message' => $exception->getMessage()]);
        }

        return redirect()->route('warehouse.location-transfers.index')->with('notification', ['type' => 'success', 'message' => 'Transfer lokasi berhasil diproses.']);
    }

    private function ensureScope(Request $request, int $workLocationId, string $field): void
    {
        if (! $request->user()?->canAccessWorkLocation($workLocationId)) {
            throw ValidationException::withMessages([$field => 'Anda tidak memiliki akses ke lokasi kerja ini.']);
        }
    }

    /** @param array<int> $permittedLocationIds */
    private function selectedWarehouseLocation(Request $request, string $field, array $permittedLocationIds): ?WarehouseLocation
    {
        $id = (int) $request->old($field);

        if ($id <= 0) {
            return null;
        }

        return WarehouseLocation::query()
            ->where('is_active', true)
            ->whereHas('warehouse', fn ($query) => $query->whereIn('work_location_id', $permittedLocationIds))
            ->find($id);
    }

    private function warehouseLocationOptions(Request $request, int $workLocationId): JsonResponse
    {
        $search = trim((string) $request->query('q'));
        $perPage = $request->integer('per_page', 20);
        $locations = WarehouseLocation::query()
            ->where('is_active', true)
            ->whereHas('warehouse', fn ($query) => $query->where('work_location_id', $workLocationId))
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search): void {
                $inner->where('full_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            }))
            ->orderBy('full_code')
            ->paginate($perPage);

        return response()->json([
            'results' => $locations->getCollection()->map(fn (WarehouseLocation $location): array => [
                'id' => $location->id,
                'text' => $location->full_code.' — '.$location->name,
            ])->values(),
            'pagination' => ['more' => $locations->hasMorePages()],
        ]);
    }

    private function productOptions(Request $request, int $workLocationId, int $warehouseLocationId): JsonResponse
    {
        $search = trim((string) $request->query('q'));
        $perPage = $request->integer('per_page', 20);
        $stocks = Stock::query()
            ->select('stocks.*')
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->with('product.baseUnit')
            ->where('stocks.work_location_id', $workLocationId)
            ->where('stocks.warehouse_location_id', $warehouseLocationId)
            ->where('products.status', 'active')
            ->whereRaw('(stocks.quantity_on_hand - stocks.quantity_reserved - stocks.quantity_damaged) > 0')
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search): void {
                $inner->where('products.sku', 'like', "%{$search}%")
                    ->orWhere('products.name', 'like', "%{$search}%");
            }))
            ->orderBy('products.name')
            ->paginate($perPage);

        return response()->json([
            'results' => $stocks->getCollection()->map(fn (Stock $stock): array => [
                'id' => $stock->product_id,
                'text' => $stock->product->sku.' — '.$stock->product->name.' (Tersedia: '.qty($stock->available_quantity).' '.$stock->product->baseUnit?->symbol.')',
            ])->values(),
            'pagination' => ['more' => $stocks->hasMorePages()],
        ]);
    }

    private function ensureWarehouseLocationBelongsToWorkLocation(int $warehouseLocationId, int $workLocationId): void
    {
        $belongs = WarehouseLocation::query()
            ->whereKey($warehouseLocationId)
            ->where('is_active', true)
            ->whereHas('warehouse', fn ($query) => $query->where('work_location_id', $workLocationId))
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'warehouse_location_id' => 'Zona/Rak/Bin tidak sesuai dengan lokasi kerja yang dipilih.',
            ]);
        }
    }
}
