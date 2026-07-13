<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\StockTransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retail\ReceiveStockTransferRequest;
use App\Http\Requests\Warehouse\PackStockTransferRequest;
use App\Http\Requests\Warehouse\ShipStockTransferRequest;
use App\Http\Requests\Warehouse\StoreStockTransferRequest;
use App\Models\DocumentStatusHistory;
use App\Models\Product;
use App\Models\RestockRequest;
use App\Models\StockTransfer;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Warehouse\StockTransferService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockTransfer::class);

        return view('warehouse.stock-transfers.index', [
            'transfers' => $this->query($request)->paginate(15)->withQueryString(),
            'statuses' => StockTransferStatus::options(),
            'workLocations' => WorkLocation::query()->whereIn('id', $request->user()?->permittedWorkLocationIds() ?? [])->where('is_active', true)->orderBy('name')->get(),
            'filters' => $request->only(['status', 'source_work_location_id', 'destination_work_location_id']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', StockTransfer::class);

        return view('warehouse.stock-transfers.create', $this->formData($request) + [
            'transfer' => new StockTransfer(['transfer_date' => now(), 'status' => StockTransferStatus::DRAFT]),
        ]);
    }

    public function store(StoreStockTransferRequest $request, StockTransferService $service): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureLocationScope($request, (int) $data['source_work_location_id']);
        $transfer = $service->create($data, $request->user());

        return redirect()->route('warehouse.stock-transfers.show', $transfer)->with('notification', ['type' => 'success', 'message' => "Transfer {$transfer->number} berhasil disimpan."]);
    }

    public function show(StockTransfer $stockTransfer): View
    {
        $this->authorize('view', $stockTransfer);

        return view('warehouse.stock-transfers.show', [
            'transfer' => $stockTransfer->load([
                'items.product', 'items.sourceWarehouseLocation', 'items.destinationWarehouseLocation',
                'sourceWorkLocation', 'destinationWorkLocation', 'restockRequest', 'requester',
                'approver', 'shipper', 'receiver', 'packages.checker', 'receipts.items.stockTransferItem',
                'stockMutations.product', 'statusHistories.actor',
            ]),
            'timeline' => DocumentStatusHistory::query()->with('actor')->where('document_type', 'stock_transfer')->where('document_id', $stockTransfer->id)->orderBy('created_at')->get(),
        ]);
    }

    public function approve(Request $request, StockTransfer $stockTransfer, StockTransferService $service): RedirectResponse
    {
        $this->authorize('approve', $stockTransfer);
        $approved = [];
        foreach ((array) $request->input('items', []) as $id => $item) {
            if (is_array($item)) {
                $approved[(int) $id] = $item['quantity_approved'] ?? 0;
            }
        }
        $service->approve($stockTransfer, $request->user(), $approved);

        return back()->with('notification', ['type' => 'success', 'message' => 'Transfer disetujui dan stok sumber di-reserve.']);
    }

    public function packing(StockTransfer $stockTransfer): View
    {
        $this->authorize('pack', $stockTransfer);

        return view('warehouse.stock-transfers.packing', ['transfer' => $stockTransfer->load(['items.product', 'items.sourceWarehouseLocation', 'packages'])]);
    }

    public function pack(PackStockTransferRequest $request, StockTransfer $stockTransfer, StockTransferService $service): RedirectResponse
    {
        $this->authorize('pack', $stockTransfer);
        $data = $request->validated();
        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')?->store('stock-transfer-packages', 'public');
        }

        $service->pack($stockTransfer, $data, $request->user());

        return redirect()->route('warehouse.stock-transfers.show', $stockTransfer)->with('notification', ['type' => 'success', 'message' => 'Picking dan packing berhasil disimpan.']);
    }

    public function shipForm(StockTransfer $stockTransfer): View
    {
        $this->authorize('ship', $stockTransfer);

        return view('warehouse.stock-transfers.ship', ['transfer' => $stockTransfer->load(['items.product', 'sourceWorkLocation', 'destinationWorkLocation'])]);
    }

    public function ship(ShipStockTransferRequest $request, StockTransfer $stockTransfer, StockTransferService $service): RedirectResponse
    {
        $this->authorize('ship', $stockTransfer);
        $data = $request->validated();
        if ($request->hasFile('proof')) {
            $data['proof_path'] = $request->file('proof')?->store('stock-transfer-shipping', 'public');
        }

        $service->ship($stockTransfer, $data, $request->user());

        return redirect()->route('warehouse.stock-transfers.show', $stockTransfer)->with('notification', ['type' => 'success', 'message' => 'Transfer berhasil dikirim.']);
    }

    public function receiveForm(StockTransfer $stockTransfer): View
    {
        $this->authorize('receive', $stockTransfer);

        return view('retail.stock-transfers.receive', ['transfer' => $stockTransfer->load(['items.product', 'destinationWorkLocation'])]);
    }

    public function receive(ReceiveStockTransferRequest $request, StockTransfer $stockTransfer, StockTransferService $service): RedirectResponse
    {
        $this->authorize('receive', $stockTransfer);
        $data = $request->validated();
        if ($request->hasFile('proof')) {
            $data['proof_path'] = $request->file('proof')?->store('stock-transfer-receipts', 'public');
        }

        $service->receive($stockTransfer, $data, $request->user());

        return redirect()->route('warehouse.stock-transfers.show', $stockTransfer)->with('notification', ['type' => 'success', 'message' => 'Penerimaan transfer berhasil disimpan.']);
    }

    public function complete(Request $request, StockTransfer $stockTransfer, StockTransferService $service): RedirectResponse
    {
        $this->authorize('complete', $stockTransfer);
        $service->complete($stockTransfer, $request->user());

        return back()->with('notification', ['type' => 'success', 'message' => 'Transfer diselesaikan.']);
    }

    public function cancel(Request $request, StockTransfer $stockTransfer, StockTransferService $service): RedirectResponse
    {
        $this->authorize('cancel', $stockTransfer);
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $service->cancel($stockTransfer, $request->user(), $request->input('reason'));

        return back()->with('notification', ['type' => 'success', 'message' => 'Transfer dibatalkan.']);
    }

    public function print(StockTransfer $stockTransfer): View
    {
        $this->authorize('view', $stockTransfer);

        return view('warehouse.stock-transfers.print', ['transfer' => $stockTransfer->load(['items.product', 'sourceWorkLocation', 'destinationWorkLocation', 'shipper'])]);
    }

    private function query(Request $request): mixed
    {
        return StockTransfer::query()
            ->with(['sourceWorkLocation', 'destinationWorkLocation', 'requester', 'shipper', 'receiver', 'items'])
            ->where(function ($query) use ($request): void {
                $ids = $request->user()?->permittedWorkLocationIds() ?? [];
                $query->whereIn('source_work_location_id', $ids)->orWhereIn('destination_work_location_id', $ids);
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->integer('source_work_location_id') > 0, fn ($query) => $query->where('source_work_location_id', $request->integer('source_work_location_id')))
            ->when($request->integer('destination_work_location_id') > 0, fn ($query) => $query->where('destination_work_location_id', $request->integer('destination_work_location_id')))
            ->latest('transfer_date')
            ->latest('id');
    }

    /** @return array<string, mixed> */
    private function formData(Request $request): array
    {
        return [
            'workLocations' => WorkLocation::query()->whereIn('id', $request->user()?->permittedWorkLocationIds() ?? [])->where('is_active', true)->orderBy('name')->get(),
            'allWorkLocations' => WorkLocation::query()->where('is_active', true)->orderBy('type')->orderBy('name')->get(),
            'warehouseLocations' => WarehouseLocation::query()->where('is_active', true)->orderBy('full_code')->limit(300)->get(),
            'products' => Product::query()->where('status', 'active')->with('baseUnit')->orderBy('name')->limit(200)->get(),
            'restockRequests' => RestockRequest::query()->where('status', 'approved')->with(['branch', 'sourceWarehouse', 'items.product'])->latest()->limit(50)->get(),
        ];
    }

    private function ensureLocationScope(Request $request, int $workLocationId): void
    {
        abort_unless($request->user()?->canAccessWorkLocation($workLocationId), 403);
    }
}
