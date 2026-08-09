<?php

namespace App\Http\Controllers\Retail;

use App\Enums\RestockRequestStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retail\ConvertRestockRequestRequest;
use App\Http\Requests\Retail\StoreRestockRequestRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\RestockRequest;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Warehouse\RestockRequestService;
use App\Services\Warehouse\StockTransferService;
use App\Support\Decimal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RestockRequestController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', RestockRequest::class);

        $locationIds = $request->user()->permittedWorkLocationIds();

        $branches = Branch::query()->with('workLocation')->where('is_active', true)->whereIn('work_location_id', $locationIds)->orderBy('name')->get();
        $products = Product::query()->with('baseUnit')->where('status', 'active')->orderBy('name')->limit(200)->get();
        $available = Stock::query()
            ->selectRaw('work_location_id, product_id, SUM(quantity_on_hand - quantity_reserved - quantity_damaged) as available')
            ->whereIn('work_location_id', $branches->pluck('work_location_id'))
            ->whereIn('product_id', $products->pluck('id'))
            ->groupBy('work_location_id', 'product_id')
            ->get()
            ->mapWithKeys(fn (Stock $stock): array => [
                $stock->work_location_id.'-'.$stock->product_id => (string) $stock->getAttribute('available'),
            ]);

        return view('retail.restock-requests.index', [
            'requests' => $this->query($request)->paginate(15)->withQueryString(),
            'branches' => $branches,
            'warehouses' => Warehouse::query()->with('workLocation')->where('is_active', true)->whereIn('work_location_id', $locationIds)->orderBy('name')->get(),
            'products' => $products,
            'productMetrics' => $products->mapWithKeys(fn (Product $product): array => [$product->id => [
                'sku' => $product->sku,
                'name' => $product->name,
                'unit' => $product->baseUnit?->symbol ?: $product->baseUnit?->name ?: '-',
                'minimum' => (string) $product->minimum_stock,
                'target' => Decimal::add((string) $product->minimum_stock, (string) $product->safety_stock),
                'stocks' => $branches->mapWithKeys(fn (Branch $branch): array => [
                    $branch->id => (string) $available->get($branch->work_location_id.'-'.$product->id, '0'),
                ])->all(),
            ]]),
            'statuses' => RestockRequestStatus::options(),
            'filters' => $request->only(['branch_id', 'status', 'source_reference']),
        ]);
    }

    public function store(StoreRestockRequestRequest $request, RestockRequestService $service): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureBranchScope($request, (int) $data['branch_id']);
        try {
            $restockRequest = $service->create($data, $request->user());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['restock' => $exception->getMessage()]);
        }

        return redirect()->route('retail.restock-requests.index')->with('notification', ['type' => 'success', 'message' => "Permintaan restok {$restockRequest->number} berhasil diajukan."]);
    }

    public function show(RestockRequest $restockRequest): View
    {
        $this->authorize('view', $restockRequest);

        return view('retail.restock-requests.show', [
            'restockRequest' => $restockRequest->load(['branch.workLocation', 'sourceWarehouse.workLocation', 'requester', 'approver', 'items.product.baseUnit', 'stockTransfers', 'statusHistories.actor']),
        ]);
    }

    public function approve(Request $request, RestockRequest $restockRequest, RestockRequestService $service): RedirectResponse
    {
        $this->authorize('approve', $restockRequest);
        $approved = [];
        foreach ((array) $request->input('items', []) as $id => $item) {
            if (is_array($item)) {
                $approved[(int) $id] = $item['quantity_approved'] ?? 0;
            }
        }
        try {
            $service->approve($restockRequest, $request->user(), $approved);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['restock' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Request restock disetujui.']);
    }

    public function reject(Request $request, RestockRequest $restockRequest, RestockRequestService $service): RedirectResponse
    {
        $this->authorize('approve', $restockRequest);
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        try {
            $service->reject($restockRequest, $request->user(), $request->input('reason'));
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['restock' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Request restock ditolak.']);
    }

    public function convertForm(Request $request, RestockRequest $restockRequest): View|RedirectResponse
    {
        $this->authorize('approve', $restockRequest);
        $existing = $restockRequest->stockTransfers()->first();
        if ($existing) {
            return redirect()->route('warehouse.stock-transfers.show', $existing);
        }
        if ($restockRequest->status !== RestockRequestStatus::APPROVED) {
            return redirect()->route('retail.restock-requests.index')->with('notification', ['type' => 'danger', 'message' => 'Permintaan restok belum disetujui.']);
        }

        $warehouses = Warehouse::query()
            ->with('workLocation')
            ->where('is_active', true)
            ->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())
            ->orderBy('name')
            ->get();
        $locations = WarehouseLocation::query()
            ->where('is_active', true)
            ->whereIn('warehouse_id', $warehouses->pluck('id'))
            ->orderBy('full_code')
            ->get();
        $balances = Stock::query()
            ->selectRaw('warehouse_location_id, product_id, SUM(quantity_on_hand - quantity_reserved - quantity_damaged) as available')
            ->whereIn('warehouse_location_id', $locations->pluck('id'))
            ->whereIn('product_id', $restockRequest->items()->pluck('product_id'))
            ->groupBy('warehouse_location_id', 'product_id')
            ->get()
            ->mapWithKeys(fn (Stock $stock): array => [
                $stock->warehouse_location_id.'-'.$stock->product_id => (string) $stock->getAttribute('available'),
            ]);

        return view('retail.restock-requests.convert', [
            'restockRequest' => $restockRequest->load(['branch.workLocation', 'sourceWarehouse', 'items.product.baseUnit']),
            'warehouses' => $warehouses,
            'locations' => $locations,
            'balances' => $balances,
        ]);
    }

    public function convert(ConvertRestockRequestRequest $request, RestockRequest $restockRequest, StockTransferService $service): RedirectResponse
    {
        $this->authorize('approve', $restockRequest);
        $warehouse = Warehouse::query()->findOrFail($request->integer('source_warehouse_id'));
        abort_unless($request->user()->canAccessWorkLocation((int) $warehouse->work_location_id), 403);
        try {
            $transfer = $service->createFromRestockRequest($restockRequest, $request->user(), $request->validated());
        } catch (ServiceException $exception) {
            return back()->withInput()->with('notification', ['type' => 'danger', 'message' => $exception->getMessage()]);
        }

        return redirect()->route('warehouse.stock-transfers.show', $transfer)->with('notification', ['type' => 'success', 'message' => 'Transfer stok berhasil dibuat.']);
    }

    private function query(Request $request): mixed
    {
        return RestockRequest::query()
            ->with(['branch.workLocation', 'sourceWarehouse.workLocation', 'requester', 'items.product', 'stockTransfers'])
            ->where(function ($query) use ($request): void {
                $locationIds = $request->user()?->permittedWorkLocationIds() ?? [];
                $query->whereHas('branch', fn ($branch) => $branch->whereIn('work_location_id', $locationIds))
                    ->orWhereHas('sourceWarehouse', fn ($warehouse) => $warehouse->whereIn('work_location_id', $locationIds));
            })
            ->when($request->integer('branch_id') > 0, fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('source_reference'), fn ($query) => $query->where('number', 'like', '%'.trim((string) $request->query('source_reference')).'%'))
            ->latest('created_at');
    }

    private function ensureBranchScope(Request $request, int $branchId): void
    {
        $branch = Branch::query()->findOrFail($branchId);
        abort_unless($request->user()?->canAccessWorkLocation((int) $branch->work_location_id), 403);
    }
}
