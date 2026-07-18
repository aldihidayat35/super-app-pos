<?php

namespace App\Http\Controllers\Warehouse;

use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreLocationTransferRequest;
use App\Models\Product;
use App\Models\StockMutation;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LocationTransferController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockMutation::class);

        return view('warehouse.location-transfers.index', [
            'transfers' => StockMutation::query()
                ->with(['product', 'workLocation', 'warehouseLocation', 'actor'])
                ->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? [])
                ->whereIn('mutation_type', ['transfer_out', 'transfer_in'])
                ->latest('occurred_at')
                ->paginate(20),
            'products' => Product::query()->where('status', 'active')->orderBy('name')->limit(200)->get(),
            'workLocations' => WorkLocation::query()->whereIn('id', $request->user()?->permittedWorkLocationIds() ?? [])->where('is_active', true)->orderBy('name')->get(),
            'warehouseLocations' => WarehouseLocation::query()
                ->with('warehouse:id,work_location_id')
                ->where('is_active', true)
                ->whereHas('warehouse', fn ($query) => $query->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? []))
                ->orderBy('full_code')
                ->limit(300)
                ->get(),
        ]);
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
}
