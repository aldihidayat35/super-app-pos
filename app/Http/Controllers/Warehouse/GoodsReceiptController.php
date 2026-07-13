<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreGoodsReceiptRequest;
use App\Http\Requests\Warehouse\UpdateGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\WarehouseLocation;
use App\Services\Warehouse\GoodsReceiptService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GoodsReceiptController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', GoodsReceipt::class);

        return view('warehouse.goods-receipts.index', [
            'receipts' => $this->query($request)->paginate(15)->withQueryString(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => GoodsReceiptStatus::options(),
            'filters' => $request->only(['supplier_id', 'status', 'date_from', 'date_to']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', GoodsReceipt::class);

        return view('warehouse.goods-receipts.create', $this->formData($request) + [
            'receipt' => new GoodsReceipt(['received_at' => now(), 'status' => GoodsReceiptStatus::DRAFT]),
        ]);
    }

    public function store(StoreGoodsReceiptRequest $request, GoodsReceiptService $service): RedirectResponse
    {
        $data = $this->validatedWithProof($request);
        $receipt = $service->createDraft($data, $request->user());

        if ($request->input('action') === 'post') {
            $this->authorize('post', $receipt);
            $receipt = $service->post($receipt, $request->user());
        }

        return redirect()
            ->route('warehouse.goods-receipts.show', $receipt)
            ->with('notification', ['type' => 'success', 'message' => "Receipt {$receipt->number} berhasil disimpan."]);
    }

    public function show(GoodsReceipt $goodsReceipt): View
    {
        $this->authorize('view', $goodsReceipt);

        return view('warehouse.goods-receipts.show', [
            'receipt' => $goodsReceipt->load([
                'purchaseOrder.items',
                'warehouse.workLocation',
                'supplier',
                'receiver',
                'items.product',
                'items.unit',
                'items.warehouseLocation',
                'items.qcResults',
                'stockMutations.product',
                'stockMutations.actor',
                'costHistories.product',
            ]),
        ]);
    }

    public function edit(Request $request, GoodsReceipt $goodsReceipt): View
    {
        $this->authorize('update', $goodsReceipt);

        return view('warehouse.goods-receipts.edit', $this->formData($request, $goodsReceipt) + [
            'receipt' => $goodsReceipt->load('items'),
        ]);
    }

    public function update(UpdateGoodsReceiptRequest $request, GoodsReceipt $goodsReceipt, GoodsReceiptService $service): RedirectResponse
    {
        $this->authorize('update', $goodsReceipt);
        $data = $this->validatedWithProof($request);
        $receipt = $service->updateDraft($goodsReceipt, $data, $request->user());

        if ($request->input('action') === 'post') {
            $this->authorize('post', $receipt);
            $receipt = $service->post($receipt, $request->user());
        }

        return redirect()
            ->route('warehouse.goods-receipts.show', $receipt)
            ->with('notification', ['type' => 'success', 'message' => 'Receipt berhasil diperbarui.']);
    }

    public function post(Request $request, GoodsReceipt $goodsReceipt, GoodsReceiptService $service): RedirectResponse
    {
        $this->authorize('post', $goodsReceipt);
        $receipt = $service->post($goodsReceipt, $request->user());

        return redirect()
            ->route('warehouse.goods-receipts.show', $receipt)
            ->with('notification', ['type' => 'success', 'message' => 'Receipt berhasil di-posting dan stok/HPP sudah diperbarui.']);
    }

    public function print(GoodsReceipt $goodsReceipt): View
    {
        $this->authorize('view', $goodsReceipt);

        return view('warehouse.goods-receipts.print', [
            'receipt' => $goodsReceipt->load(['purchaseOrder', 'warehouse.workLocation', 'supplier', 'receiver', 'items.warehouseLocation']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', GoodsReceipt::class);

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Nomor', 'PO', 'Supplier', 'Gudang', 'Tanggal', 'Penerima', 'Accepted', 'Rejected', 'Damaged', 'Status']);

            $this->query($request)->chunk(200, function ($receipts) use ($handle): void {
                foreach ($receipts as $receipt) {
                    fputcsv($handle, [
                        $receipt->number,
                        $receipt->purchaseOrder?->number,
                        $receipt->supplier?->name,
                        $receipt->warehouse?->name,
                        optional($receipt->received_at)->format('Y-m-d'),
                        $receipt->receiver?->name,
                        $receipt->acceptedQuantity(),
                        $receipt->rejectedQuantity(),
                        $receipt->damagedQuantity(),
                        $receipt->status->label(),
                    ]);
                }
            });

            fclose($handle);
        }, 'goods-receipts.csv');
    }

    private function query(Request $request): mixed
    {
        return GoodsReceipt::query()
            ->with(['purchaseOrder', 'supplier', 'warehouse', 'receiver', 'items'])
            ->whereHas('warehouse', fn ($query) => $query->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? []))
            ->when($request->integer('supplier_id') > 0, fn ($query) => $query->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('received_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('received_at', '<=', $request->query('date_to')))
            ->latest('received_at')
            ->latest('id');
    }

    /** @return array<string, mixed> */
    private function formData(Request $request, ?GoodsReceipt $receipt = null): array
    {
        $readyStatuses = [PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::SENT_TO_SUPPLIER, PurchaseOrderStatus::PARTIALLY_RECEIVED];
        $purchaseOrders = PurchaseOrder::query()
            ->with(['supplier', 'warehouse.workLocation', 'items.product', 'items.unit'])
            ->whereIn('status', $readyStatuses)
            ->whereHas('warehouse', fn ($query) => $query->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? []))
            ->latest('order_date')
            ->limit(100)
            ->get();

        $selectedPo = $receipt?->purchaseOrder ?: ($request->integer('purchase_order_id') > 0
            ? $purchaseOrders->firstWhere('id', $request->integer('purchase_order_id'))
            : $purchaseOrders->first());

        return [
            'purchaseOrders' => $purchaseOrders,
            'selectedPo' => $selectedPo?->loadMissing(['items.product', 'items.unit', 'warehouse.workLocation']),
            'warehouseLocations' => $selectedPo
                ? WarehouseLocation::query()->where('warehouse_id', $selectedPo->warehouse_id)->where('is_active', true)->orderBy('full_code')->get()
                : collect(),
        ];
    }

    /** @return array<string, mixed> */
    private function validatedWithProof(StoreGoodsReceiptRequest|UpdateGoodsReceiptRequest $request): array
    {
        $data = $request->validated();

        if ($request->hasFile('proof')) {
            $data['proof_path'] = $request->file('proof')?->store('goods-receipts', 'public');
        }

        return $data;
    }
}
