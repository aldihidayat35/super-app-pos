<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\ProductStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\DecideStockDirectAdjustmentRequest;
use App\Http\Requests\Warehouse\StoreOpeningStockRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Stock;
use App\Models\StockAdjustmentAccess;
use App\Models\StockDirectAdjustment;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Warehouse\OpeningStockService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpeningStockController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('opening_stocks.view'), 403);
        $locations = WorkLocation::query()->whereIn('id', $request->user()->permittedWorkLocationIds())
            ->whereIn('type', ['warehouse', 'branch'])->where('is_active', true)->orderBy('name')->get();
        $locationId = $request->integer('work_location_id') ?: ($locations->isEmpty() ? 0 : (int) $locations->firstOrFail()->id);
        abort_if($locationId && ! $locations->contains('id', $locationId), 403);
        $binIds = $this->binIds($locationId);

        $products = Product::query()->with(['baseUnit', 'category', 'images'])
            ->where('status', ProductStatus::ACTIVE->value)
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = '%'.addcslashes($request->string('q')->toString(), '%_').'%';
                $query->where(fn (Builder $q) => $q->where('sku', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->when($request->integer('category_id'), fn (Builder $query, int $id) => $query->where('category_id', $id))
            ->when($request->string('count_status')->toString() === 'posted', fn (Builder $query) => $this->filterPostedProducts($query, $locationId, false))
            ->when($request->string('count_status')->toString() === 'not_posted', fn (Builder $query) => $this->filterPostedProducts($query, $locationId, true))
            ->orderBy('name')->get();

        $stockByProduct = Stock::query()->where('work_location_id', $locationId)->whereIn('warehouse_location_id', $binIds)
            ->get()->groupBy('product_id');
        $documentQuery = $this->documents($request, $locationId);

        return view('warehouse.opening-stocks.index', [
            'locations' => $locations,
            'selectedLocationId' => $locationId,
            'products' => $products,
            'categories' => ProductCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'bins' => WarehouseLocation::query()->whereIn('id', $binIds)->where('is_active', true)->orderBy('full_code')->get(),
            'stockByProduct' => $stockByProduct,
            'access' => StockAdjustmentAccess::query()->where('work_location_id', $locationId)->first(),
            'documents' => (clone $documentQuery)->latest('id')->paginate(15, ['*'], 'documents_page')->withQueryString(),
            'draftCount' => (clone $documentQuery)->where('status', 'draft')->count(),
            'pendingCount' => (clone $documentQuery)->where('status', 'pending_approval')->count(),
            'completedProductCount' => DB::table('stock_direct_adjustment_items as items')
                ->join('stock_direct_adjustments as documents', 'documents.id', '=', 'items.stock_direct_adjustment_id')
                ->where('documents.purpose', 'opening_balance')->where('documents.work_location_id', $locationId)->where('documents.status', 'completed')
                ->distinct()->count('items.product_id'),
            'activeProductCount' => Product::query()->where('status', ProductStatus::ACTIVE->value)->count(),
        ]);
    }

    public function store(StoreOpeningStockRequest $request, OpeningStockService $service): RedirectResponse
    {
        return $this->run(function () use ($request, $service): RedirectResponse {
            $document = $service->create($request->validated(), $request->user(), $request);

            return redirect()->route('warehouse.opening-stocks.show', $document)->with('notification', ['type' => 'success', 'message' => 'Draft pendataan stok berhasil disimpan.']);
        });
    }

    public function show(Request $request, StockDirectAdjustment $openingStock): View
    {
        $this->assertView($request, $openingStock);

        return view('warehouse.opening-stocks.show', [
            'document' => $openingStock->load(['workLocation', 'creator', 'approver', 'items.product.baseUnit', 'items.warehouseLocation', 'approvals.approver']),
            'bins' => WarehouseLocation::query()->whereIn('id', $this->binIds((int) $openingStock->work_location_id))->where('is_active', true)->orderBy('full_code')->get(),
            'access' => StockAdjustmentAccess::query()->where('work_location_id', $openingStock->work_location_id)->first(),
        ]);
    }

    public function update(StoreOpeningStockRequest $request, StockDirectAdjustment $openingStock, OpeningStockService $service): RedirectResponse
    {
        $this->assertView($request, $openingStock);
        abort_unless((int) $openingStock->created_by === (int) $request->user()->id, 403);

        return $this->run(fn () => $service->update($openingStock, $request->validated(), $request->user(), $request), 'Draft pendataan stok diperbarui.');
    }

    public function submit(Request $request, StockDirectAdjustment $openingStock, OpeningStockService $service): RedirectResponse
    {
        $this->assertView($request, $openingStock);

        return $this->run(fn () => $service->submit($openingStock, $request->user(), $request), 'Pendataan diajukan kepada kepala lokasi.');
    }

    public function approve(DecideStockDirectAdjustmentRequest $request, StockDirectAdjustment $openingStock, OpeningStockService $service): RedirectResponse
    {
        $this->assertView($request, $openingStock);

        return $this->run(function () use ($request, $openingStock, $service): RedirectResponse {
            $result = $service->approve($openingStock, $request->user(), $request->validated('notes'), $request);
            if ($result->getAttribute('was_stale')) {
                return back()->withErrors(['opening_stock' => 'Stok berubah setelah pengajuan. Snapshot diperbarui; periksa dan ajukan ulang.']);
            }

            return back()->with('notification', ['type' => 'success', 'message' => 'Pendataan berhasil diposting sebagai saldo stok lokasi.']);
        });
    }

    public function reject(DecideStockDirectAdjustmentRequest $request, StockDirectAdjustment $openingStock, OpeningStockService $service): RedirectResponse
    {
        $this->assertView($request, $openingStock);

        return $this->run(fn () => $service->reject($openingStock, $request->user(), $request->validated('notes'), $request), 'Pendataan stok ditolak.');
    }

    public function cancel(DecideStockDirectAdjustmentRequest $request, StockDirectAdjustment $openingStock, OpeningStockService $service): RedirectResponse
    {
        $this->assertView($request, $openingStock);

        return $this->run(fn () => $service->cancel($openingStock, $request->user(), $request->validated('notes'), $request), 'Pendataan stok dibatalkan.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('opening_stocks.export'), 403);
        $locationId = $request->integer('work_location_id');
        if ($locationId) {
            abort_unless($request->user()->canAccessWorkLocation($locationId), 403);
        }
        $rows = $this->documents($request, $locationId)->with(['workLocation', 'creator', 'approver', 'items'])->latest('id')->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Nomor', 'Lokasi', 'Status', 'Pembuat', 'Penyetuju', 'SKU', 'Produk', 'Jumlah Sistem', 'Jumlah Fisik', 'Selisih', 'HPP Snapshot', 'Nilai Selisih', 'Rak', 'Catatan']);
            foreach ($rows as $row) {
                foreach ($row->items as $item) {
                    fputcsv($handle, [$row->number, $row->workLocation->name, $row->status, $row->creator->name, $row->approver?->name, $item->product_sku_snapshot, $item->product_name_snapshot, $item->system_qty_snapshot, $item->physical_qty, $item->difference_qty, $item->unit_cost_snapshot, $item->difference_value, $item->warehouseLocation?->full_code, $item->note]);
                }
            }
            fclose($handle);
        }, 'pendataan-stok-lokasi-'.now()->format('Ymd-His').'.csv');
    }

    /** @return Builder<StockDirectAdjustment> */
    private function documents(Request $request, int $locationId = 0): Builder
    {
        return StockDirectAdjustment::query()->where('purpose', 'opening_balance')
            ->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())
            ->when($locationId, fn (Builder $query) => $query->where('work_location_id', $locationId))
            ->with(['workLocation', 'creator', 'approver', 'items']);
    }

    /** @return list<int> */
    private function binIds(int $locationId): array
    {
        return WarehouseLocation::query()->forWorkLocation($locationId)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    private function assertView(Request $request, StockDirectAdjustment $document): void
    {
        abort_unless($request->user()?->can('opening_stocks.view') && $document->isOpeningBalance() && $request->user()->canAccessWorkLocation((int) $document->work_location_id), 403);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function filterPostedProducts(Builder $query, int $locationId, bool $negate): Builder
    {
        $constraint = fn ($subquery) => $subquery->selectRaw('1')
            ->from('stock_direct_adjustment_items as counted_items')
            ->join('stock_direct_adjustments as counted_documents', 'counted_documents.id', '=', 'counted_items.stock_direct_adjustment_id')
            ->whereColumn('counted_items.product_id', 'products.id')
            ->where('counted_documents.purpose', 'opening_balance')
            ->where('counted_documents.status', 'completed')
            ->where('counted_documents.work_location_id', $locationId);

        return $negate ? $query->whereNotExists($constraint) : $query->whereExists($constraint);
    }

    private function run(callable $action, ?string $message = null): RedirectResponse
    {
        try {
            $result = $action();
            if ($result instanceof RedirectResponse) {
                return $result;
            }
        } catch (ServiceException $exception) {
            return back()->withInput()->withErrors(['opening_stock' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => $message]);
    }
}
