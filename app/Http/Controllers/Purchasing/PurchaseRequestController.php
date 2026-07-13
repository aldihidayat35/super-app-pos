<?php

namespace App\Http\Controllers\Purchasing;

use App\Enums\PurchaseRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StorePurchaseRequestRequest;
use App\Models\DocumentStatusHistory;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Organization\DocumentNumberService;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseRequestController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchaseRequest::class);
        $locationIds = $request->user()?->permittedWorkLocationIds() ?? [];
        $status = $request->query('status');

        $requests = PurchaseRequest::query()
            ->with(['warehouse', 'requester', 'items.product', 'convertedPurchaseOrder'])
            ->whereHas('warehouse', fn ($query) => $query->whereIn('work_location_id', $locationIds))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $recommendations = Stock::query()
            ->with(['product.baseUnit', 'warehouseLocation', 'workLocation'])
            ->whereIn('work_location_id', $locationIds)
            ->whereHas('product', fn ($query) => $query->whereColumn('stocks.quantity_on_hand', '<=', 'products.minimum_stock'))
            ->limit(10)
            ->get();

        return view('purchasing.requests.index', [
            'requests' => $requests,
            'recommendations' => $recommendations,
            'warehouses' => $this->warehouses($request),
            'products' => Product::query()->with('baseUnit')->where('status', 'active')->orderBy('name')->limit(200)->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => PurchaseRequestStatus::options(),
            'status' => $status,
        ]);
    }

    public function store(StorePurchaseRequestRequest $request, DocumentNumberService $numbers): RedirectResponse
    {
        $purchaseRequest = DB::transaction(function () use ($request, $numbers): PurchaseRequest {
            $data = $request->validated();
            $warehouse = Warehouse::query()->with('workLocation')->findOrFail($data['warehouse_id']);
            $this->authorize('create', PurchaseRequest::class);

            $purchaseRequest = PurchaseRequest::query()->create([
                'number' => $numbers->next('purchase_request', $warehouse->workLocation),
                'warehouse_id' => $warehouse->id,
                'requester_user_id' => $request->user()?->id,
                'priority' => $data['priority'],
                'status' => PurchaseRequestStatus::SUBMITTED,
                'reason' => $data['reason'],
                'submitted_at' => now(),
            ]);

            foreach ($data['items'] as $item) {
                $purchaseRequest->items()->create($item);
            }

            $this->history($purchaseRequest, null, PurchaseRequestStatus::SUBMITTED->value, $request, 'Permintaan pembelian diajukan.');

            return $purchaseRequest;
        });

        return redirect()->route('purchasing.requests.index')->with('notification', ['type' => 'success', 'message' => "Permintaan {$purchaseRequest->number} berhasil diajukan."]);
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('approve', $purchaseRequest);

        DB::transaction(function () use ($request, $purchaseRequest): void {
            $from = $purchaseRequest->status->value;
            $purchaseRequest->forceFill([
                'status' => PurchaseRequestStatus::APPROVED,
                'approved_at' => now(),
                'approved_by' => $request->user()?->id,
            ])->save();
            $this->history($purchaseRequest, $from, PurchaseRequestStatus::APPROVED->value, $request, 'Permintaan disetujui.');
        });

        return back()->with('notification', ['type' => 'success', 'message' => 'Permintaan pembelian disetujui.']);
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('approve', $purchaseRequest);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($request, $purchaseRequest, $data): void {
            $from = $purchaseRequest->status->value;
            $purchaseRequest->forceFill([
                'status' => PurchaseRequestStatus::REJECTED,
                'rejected_at' => now(),
                'rejected_by' => $request->user()?->id,
            ])->save();
            $this->history($purchaseRequest, $from, PurchaseRequestStatus::REJECTED->value, $request, $data['reason']);
        });

        return back()->with('notification', ['type' => 'success', 'message' => 'Permintaan pembelian ditolak.']);
    }

    public function convert(Request $request, PurchaseRequest $purchaseRequest, PurchaseOrderService $orders): RedirectResponse
    {
        $this->authorize('convert', $purchaseRequest);
        $data = $request->validate(['supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('is_active', true)]]);

        $purchaseOrder = DB::transaction(function () use ($request, $purchaseRequest, $orders, $data) {
            $purchaseRequest->load(['items.product.baseUnit', 'warehouse']);
            $items = $purchaseRequest->items->map(fn ($item): array => [
                'product_id' => $item->product_id,
                'unit_id' => $item->unit_id ?: $item->product->base_unit_id,
                'quantity_ordered' => $item->quantity,
                'unit_price' => '0',
                'discount_amount' => '0',
                'tax_amount' => '0',
            ])->values()->all();

            $purchaseOrder = $orders->create([
                'warehouse_id' => $purchaseRequest->warehouse_id,
                'supplier_id' => $data['supplier_id'],
                'purchase_request_id' => $purchaseRequest->id,
                'order_date' => now()->toDateString(),
                'expected_at' => null,
                'payment_term_days' => 0,
                'notes' => 'Dikonversi dari '.$purchaseRequest->number,
                'items' => $items,
            ], $request->user());

            $from = $purchaseRequest->status->value;
            $purchaseRequest->forceFill([
                'status' => PurchaseRequestStatus::CONVERTED,
                'converted_purchase_order_id' => $purchaseOrder->id,
            ])->save();
            $this->history($purchaseRequest, $from, PurchaseRequestStatus::CONVERTED->value, $request, 'Dikonversi ke PO '.$purchaseOrder->number);

            return $purchaseOrder;
        });

        return redirect()->route('purchasing.purchase-orders.show', $purchaseOrder)->with('notification', ['type' => 'success', 'message' => 'Permintaan berhasil dikonversi menjadi PO draft.']);
    }

    private function warehouses(Request $request): mixed
    {
        return Warehouse::query()
            ->where('is_active', true)
            ->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? [])
            ->orderBy('name')
            ->get();
    }

    private function history(PurchaseRequest $purchaseRequest, ?string $from, string $to, Request $request, ?string $notes = null): void
    {
        DocumentStatusHistory::query()->create([
            'document_type' => 'purchase_request',
            'document_id' => $purchaseRequest->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_user_id' => $request->user()?->id,
            'notes' => $notes,
        ]);
    }
}
