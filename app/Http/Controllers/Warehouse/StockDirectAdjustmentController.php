<?php

namespace App\Http\Controllers\Warehouse;

use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\DecideStockDirectAdjustmentRequest;
use App\Http\Requests\Warehouse\StoreStockDirectAdjustmentRequest;
use App\Models\Stock;
use App\Models\StockAdjustmentAccess;
use App\Models\StockDirectAdjustment;
use App\Models\WorkLocation;
use App\Services\Warehouse\StockDirectAdjustmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockDirectAdjustmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockDirectAdjustment::class);
        $locationIds = $request->user()->permittedWorkLocationIds();
        $locations = WorkLocation::query()->whereIn('id', $locationIds)->whereIn('type', ['warehouse', 'branch'])->where('is_active', true)->orderBy('name')->get();
        $selectedLocationId = $request->integer('work_location_id') ?: ($locations->isNotEmpty() ? (int) $locations->first()->id : 0);

        return view('warehouse.stock-adjustments.index', [
            'adjustments' => $this->query($request)->paginate(20)->withQueryString(),
            'locations' => $locations,
            'selectedLocationId' => $selectedLocationId,
            'stocks' => Stock::query()->with(['product.category', 'product.baseUnit', 'warehouseLocation', 'batches' => fn ($query) => $query->available()->orderBy('received_at')->orderBy('id')])->where('work_location_id', $selectedLocationId)->orderBy('product_id')->get(),
            'access' => StockAdjustmentAccess::query()->where('work_location_id', $selectedLocationId)->first(),
            'filters' => $request->only(['work_location_id', 'status']),
        ]);
    }

    public function store(StoreStockDirectAdjustmentRequest $request, StockDirectAdjustmentService $service): RedirectResponse
    {
        try {
            $adjustment = $service->create($request->validated(), $request->user(), $request);
        } catch (ServiceException $exception) {
            return back()->withInput()->withErrors(['adjustment' => $exception->getMessage()]);
        }

        return redirect()->route('warehouse.stock-adjustments.show', $adjustment)->with('notification', ['type' => 'success', 'message' => 'Draft koreksi stok berhasil dibuat.']);
    }

    public function show(StockDirectAdjustment $stockAdjustment): View
    {
        $this->authorize('view', $stockAdjustment);

        return view('warehouse.stock-adjustments.show', [
            'adjustment' => $stockAdjustment->load(['workLocation', 'creator', 'approver', 'items.product', 'items.warehouseLocation', 'approvals.approver']),
            'stocks' => Stock::query()->with(['product', 'warehouseLocation'])->where('work_location_id', $stockAdjustment->work_location_id)->orderBy('product_id')->get(),
            'access' => StockAdjustmentAccess::query()->where('work_location_id', $stockAdjustment->work_location_id)->first(),
        ]);
    }

    public function update(StoreStockDirectAdjustmentRequest $request, StockDirectAdjustment $stockAdjustment, StockDirectAdjustmentService $service): RedirectResponse
    {
        $this->authorize('update', $stockAdjustment);
        try {
            $service->update($stockAdjustment, $request->validated(), $request->user(), $request);
        } catch (ServiceException $exception) {
            return back()->withInput()->withErrors(['adjustment' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Draft koreksi stok diperbarui.']);
    }

    public function submit(Request $request, StockDirectAdjustment $stockAdjustment, StockDirectAdjustmentService $service): RedirectResponse
    {
        $this->authorize('update', $stockAdjustment);

        return $this->run(fn () => $service->submit($stockAdjustment, $request->user(), $request), 'Koreksi stok diajukan untuk persetujuan.');
    }

    public function approve(DecideStockDirectAdjustmentRequest $request, StockDirectAdjustment $stockAdjustment, StockDirectAdjustmentService $service): RedirectResponse
    {
        $this->authorize('decide', $stockAdjustment);
        try {
            $result = $service->approve($stockAdjustment, $request->user(), $request->validated('notes'), $request);
        } catch (ServiceException $exception) {
            return back()->withErrors(['adjustment' => $exception->getMessage()]);
        }
        if ($result->getAttribute('was_stale')) {
            return back()->withErrors(['adjustment' => 'Stok berubah setelah pengajuan. Snapshot telah diperbarui; pembuat harus memeriksa dan mengajukan ulang.']);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Koreksi disetujui dan stok berhasil diperbarui.']);
    }

    public function reject(DecideStockDirectAdjustmentRequest $request, StockDirectAdjustment $stockAdjustment, StockDirectAdjustmentService $service): RedirectResponse
    {
        $this->authorize('decide', $stockAdjustment);

        return $this->run(fn () => $service->reject($stockAdjustment, $request->user(), $request->validated('notes'), $request), 'Koreksi stok ditolak.');
    }

    public function cancel(DecideStockDirectAdjustmentRequest $request, StockDirectAdjustment $stockAdjustment, StockDirectAdjustmentService $service): RedirectResponse
    {
        return $this->run(fn () => $service->cancel($stockAdjustment, $request->user(), $request->validated('notes'), $request), 'Koreksi stok dibatalkan.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('stock_direct_adjustments.export'), 403);
        $rows = $this->query($request)->with(['workLocation', 'creator', 'approver', 'items'])->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Nomor', 'Lokasi', 'Status', 'Pembuat', 'Penyetuju', 'SKU', 'Produk', 'Stok Sistem', 'Stok Fisik', 'Selisih', 'Nilai Selisih', 'Catatan']);
            foreach ($rows as $row) {
                foreach ($row->items as $item) {
                    fputcsv($handle, [$row->number, $row->workLocation?->name, $row->status, $row->creator?->name, $row->approver?->name, $item->product_sku_snapshot, $item->product_name_snapshot, $item->system_qty_snapshot, $item->physical_qty, $item->difference_qty, $item->difference_value, $item->note]);
                }
            }
            fclose($handle);
        }, 'koreksi-stok-'.now()->format('Ymd-His').'.csv');
    }

    private function query(Request $request): mixed
    {
        return StockDirectAdjustment::query()->where('purpose', 'operational_correction')->with(['workLocation', 'creator', 'approver', 'items'])
            ->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())
            ->when($request->integer('work_location_id'), fn ($query, $id) => $query->where('work_location_id', $id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))->latest('id');
    }

    private function run(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (ServiceException $exception) {
            return back()->withErrors(['adjustment' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => $message]);
    }
}
