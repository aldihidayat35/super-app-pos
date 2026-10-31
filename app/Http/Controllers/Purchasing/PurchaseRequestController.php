<?php

namespace App\Http\Controllers\Purchasing;

use App\Enums\PurchaseRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StorePurchaseRequestRequest;
use App\Models\Branch;
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

        $branchWarehouseIds = Branch::query()
            ->whereIn('work_location_id', $locationIds)
            ->whereNotNull('primary_warehouse_id')
            ->pluck('primary_warehouse_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $baseRequestQuery = PurchaseRequest::query()
            ->where(function ($query) use ($locationIds, $branchWarehouseIds, $request): void {
                $query->whereHas('warehouse', fn ($q) => $q->whereIn('work_location_id', $locationIds));
                if (! empty($branchWarehouseIds)) {
                    $query->orWhereIn('warehouse_id', $branchWarehouseIds);
                }
                if ($request->user()) {
                    $query->orWhere('requester_user_id', $request->user()->id);
                }
            });

        $requests = (clone $baseRequestQuery)
            ->with(['warehouse', 'requester', 'items.product', 'convertedPurchaseOrder'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $recommendations = Stock::query()
            ->select([
                'stocks.product_id',
                'stocks.work_location_id',
                DB::raw('SUM(stocks.quantity_on_hand) as quantity_on_hand'),
                DB::raw('SUM(stocks.quantity_reserved) as quantity_reserved'),
                DB::raw('SUM(stocks.quantity_damaged) as quantity_damaged'),
                DB::raw('SUM(stocks.quantity_on_hand - stocks.quantity_reserved - stocks.quantity_damaged) as available_quantity'),
            ])
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->whereIn('stocks.work_location_id', $locationIds)
            ->where('products.status', 'active')
            ->groupBy('stocks.product_id', 'stocks.work_location_id', 'products.minimum_stock')
            ->havingRaw('available_quantity <= products.minimum_stock')
            ->with([
                'product.baseUnit',
                'product.category',
                'product.supplierProducts.supplier',
                'workLocation',
            ])
            ->limit(50)
            ->get();

        $stats = [
            'total' => (clone $baseRequestQuery)->count(),
            'pending' => (clone $baseRequestQuery)->where('status', PurchaseRequestStatus::SUBMITTED)->count(),
            'approved' => (clone $baseRequestQuery)->where('status', PurchaseRequestStatus::APPROVED)->count(),
            'converted' => (clone $baseRequestQuery)->where('status', PurchaseRequestStatus::CONVERTED)->count(),
            'critical' => $recommendations->count(),
        ];

        return view('purchasing.requests.index', [
            'requests' => $requests,
            'recommendations' => $recommendations,
            'stats' => $stats,
            'warehouses' => $this->warehouses($request),
            'products' => Product::query()->with(['baseUnit', 'category', 'units.unit'])->where('status', 'active')->orderBy('name')->limit(300)->get(),
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

    public function show(PurchaseRequest $purchaseRequest): View
    {
        $this->authorize('view', $purchaseRequest);

        return view('purchasing.requests.show', [
            'purchaseRequest' => $purchaseRequest->load([
                'warehouse',
                'requester',
                'items.product.baseUnit',
                'items.unit',
                'convertedPurchaseOrder.supplier',
                'statusHistories.actor',
            ]),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('approve', $purchaseRequest);

        DB::transaction(function () use ($request, $purchaseRequest): void {
            $lockedRequest = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->id);
            $this->authorize('approve', $lockedRequest);
            $from = $lockedRequest->status->value;
            $lockedRequest->forceFill([
                'status' => PurchaseRequestStatus::APPROVED,
                'approved_at' => now(),
                'approved_by' => $request->user()?->id,
            ])->save();
            $this->history($lockedRequest, $from, PurchaseRequestStatus::APPROVED->value, $request, 'Permintaan disetujui.');
        });

        return back()->with('notification', ['type' => 'success', 'message' => 'Permintaan pembelian disetujui.']);
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('approve', $purchaseRequest);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($request, $purchaseRequest, $data): void {
            $lockedRequest = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->id);
            $this->authorize('approve', $lockedRequest);
            $from = $lockedRequest->status->value;
            $lockedRequest->forceFill([
                'status' => PurchaseRequestStatus::REJECTED,
                'rejected_at' => now(),
                'rejected_by' => $request->user()?->id,
            ])->save();
            $this->history($lockedRequest, $from, PurchaseRequestStatus::REJECTED->value, $request, $data['reason']);
        });

        return back()->with('notification', ['type' => 'success', 'message' => 'Permintaan pembelian ditolak.']);
    }

    public function convert(Request $request, PurchaseRequest $purchaseRequest, PurchaseOrderService $orders): RedirectResponse
    {
        $this->authorize('convert', $purchaseRequest);
        $data = $request->validate(['supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('is_active', true)]]);

        $purchaseOrder = DB::transaction(function () use ($request, $purchaseRequest, $orders, $data) {
            $lockedRequest = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->id);
            $this->authorize('convert', $lockedRequest);
            $lockedRequest->load(['items.product.baseUnit', 'warehouse']);
            $items = $lockedRequest->items->map(fn ($item): array => [
                'product_id' => $item->product_id,
                'unit_id' => $item->unit_id ?: $item->product->base_unit_id,
                'quantity_ordered' => $item->quantity,
                'unit_price' => '0',
                'discount_amount' => '0',
                'tax_amount' => '0',
            ])->values()->all();

            $purchaseOrder = $orders->create([
                'warehouse_id' => $lockedRequest->warehouse_id,
                'supplier_id' => $data['supplier_id'],
                'purchase_request_id' => $lockedRequest->id,
                'order_date' => now()->toDateString(),
                'expected_at' => null,
                'payment_term_days' => 0,
                'notes' => 'Dikonversi dari '.$lockedRequest->number,
                'items' => $items,
            ], $request->user());

            $from = $lockedRequest->status->value;
            $lockedRequest->forceFill([
                'status' => PurchaseRequestStatus::CONVERTED,
                'converted_purchase_order_id' => $purchaseOrder->id,
            ])->save();
            $this->history($lockedRequest, $from, PurchaseRequestStatus::CONVERTED->value, $request, 'Dikonversi ke PO '.$purchaseOrder->number);

            return $purchaseOrder;
        });

        return redirect()->route('purchasing.purchase-orders.show', $purchaseOrder)->with('notification', ['type' => 'success', 'message' => 'Permintaan berhasil dikonversi menjadi PO draft.']);
    }

    private function warehouses(Request $request): mixed
    {
        $locationIds = $request->user()?->permittedWorkLocationIds() ?? [];

        $branchWarehouseIds = Branch::query()
            ->whereIn('work_location_id', $locationIds)
            ->whereNotNull('primary_warehouse_id')
            ->pluck('primary_warehouse_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return Warehouse::query()
            ->where('is_active', true)
            ->where(function ($query) use ($locationIds, $branchWarehouseIds): void {
                $query->whereIn('work_location_id', $locationIds);
                if (! empty($branchWarehouseIds)) {
                    $query->orWhereIn('id', $branchWarehouseIds);
                }
            })
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
