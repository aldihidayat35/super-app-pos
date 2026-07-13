<?php

namespace App\Http\Controllers\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Exports\PurchaseOrdersExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\CancelPurchaseOrderRequest;
use App\Http\Requests\Purchasing\StorePurchaseOrderRequest;
use App\Http\Requests\Purchasing\UpdatePurchaseOrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        return view('purchasing.purchase-orders.index', [
            'purchaseOrders' => $this->query($request)->paginate(15)->withQueryString(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => PurchaseOrderStatus::options(),
            'filters' => $request->only(['supplier_id', 'status', 'date_from', 'date_to']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', PurchaseOrder::class);
        $purchaseRequest = $request->integer('purchase_request_id') > 0 ? PurchaseRequest::query()->with('items.product')->find($request->integer('purchase_request_id')) : null;

        return view('purchasing.purchase-orders.create', $this->formData($request) + [
            'purchaseOrder' => new PurchaseOrder(['order_date' => now(), 'status' => PurchaseOrderStatus::DRAFT]),
            'purchaseRequest' => $purchaseRequest,
        ]);
    }

    public function store(StorePurchaseOrderRequest $request, PurchaseOrderService $service): RedirectResponse
    {
        $purchaseOrder = $service->create($request->validated(), $request->user());

        return redirect()->route('purchasing.purchase-orders.show', $purchaseOrder)->with('notification', ['type' => 'success', 'message' => "PO {$purchaseOrder->number} berhasil dibuat."]);
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('view', $purchaseOrder);

        return view('purchasing.purchase-orders.show', [
            'purchaseOrder' => $purchaseOrder->load(['warehouse', 'supplier', 'creator', 'approver', 'items.product', 'statusHistories.actor', 'approvals.approver', 'purchaseRequest']),
        ]);
    }

    public function edit(Request $request, PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('update', $purchaseOrder);

        return view('purchasing.purchase-orders.edit', $this->formData($request) + [
            'purchaseOrder' => $purchaseOrder->load('items'),
            'purchaseRequest' => $purchaseOrder->purchaseRequest,
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);
        $purchaseOrder = $service->update($purchaseOrder, $request->validated(), $request->user());

        return redirect()->route('purchasing.purchase-orders.show', $purchaseOrder)->with('notification', ['type' => 'success', 'message' => 'PO berhasil diperbarui.']);
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);
        $service->submit($purchaseOrder, $request->user());

        return back()->with('notification', ['type' => 'success', 'message' => 'PO berhasil diajukan.']);
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $this->authorize('approve', $purchaseOrder);
        $service->approve($purchaseOrder, $request->user(), $request->input('notes'));

        return back()->with('notification', ['type' => 'success', 'message' => 'PO berhasil disetujui.']);
    }

    public function send(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);
        $service->markSent($purchaseOrder, $request->user());

        return back()->with('notification', ['type' => 'success', 'message' => 'PO ditandai sudah dikirim ke supplier.']);
    }

    public function cancel(CancelPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $this->authorize('cancel', $purchaseOrder);
        $service->cancel($purchaseOrder, $request->user(), $request->validated('reason'));

        return back()->with('notification', ['type' => 'success', 'message' => 'PO berhasil dibatalkan.']);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('export', PurchaseOrder::class);

        return Excel::download(new PurchaseOrdersExport($this->query($request)->get()), 'purchase-orders.xlsx');
    }

    public function exportOne(PurchaseOrder $purchaseOrder): BinaryFileResponse
    {
        $this->authorize('print', $purchaseOrder);

        return Excel::download(new PurchaseOrdersExport(collect([$purchaseOrder->load(['supplier', 'warehouse', 'items'])])), $purchaseOrder->number.'.xlsx');
    }

    private function query(Request $request): mixed
    {
        return PurchaseOrder::query()
            ->with(['supplier', 'warehouse', 'items'])
            ->whereHas('warehouse', fn ($query) => $query->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? []))
            ->when($request->integer('supplier_id') > 0, fn ($query) => $query->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('order_date', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('order_date', '<=', $request->query('date_to')))
            ->latest('order_date')
            ->latest('id');
    }

    /** @return array<string, mixed> */
    private function formData(Request $request): array
    {
        return [
            'warehouses' => Warehouse::query()->where('is_active', true)->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? [])->orderBy('name')->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->with(['baseUnit', 'units.unit'])->where('status', 'active')->orderBy('name')->limit(200)->get(),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
