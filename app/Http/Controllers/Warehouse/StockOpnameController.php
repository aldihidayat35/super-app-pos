<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\ApproveStockOpnameRequest;
use App\Http\Requests\Warehouse\CountStockOpnameItemRequest;
use App\Http\Requests\Warehouse\ImportStockOpnameCountsRequest;
use App\Http\Requests\Warehouse\StoreStockOpnameRequest;
use App\Models\ProductCategory;
use App\Models\StockMutation;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Warehouse\StockOpnameService;
use App\Support\Decimal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockOpnameController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockOpname::class);
        $permittedWorkLocationIds = $request->user()?->permittedWorkLocationIds() ?? [];

        return view('warehouse.stock-opnames.index', [
            'opnames' => $this->query($request)->paginate(15)->withQueryString(),
            'workLocations' => WorkLocation::query()->whereIn('id', $permittedWorkLocationIds)->where('is_active', true)->orderBy('name')->get(),
            'warehouseLocations' => WarehouseLocation::query()
                ->with('warehouse:id,work_location_id')
                ->where('is_active', true)
                ->whereHas('warehouse', fn ($query) => $query->whereIn('work_location_id', $permittedWorkLocationIds))
                ->orderBy('full_code')
                ->get(),
            'categories' => ProductCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->limit(100)->get(),
            'statuses' => StockOpnameStatus::options(),
            'filters' => $request->only(['work_location_id', 'status']),
        ]);
    }

    public function store(StoreStockOpnameRequest $request, StockOpnameService $service): RedirectResponse
    {
        $data = $request->validated();
        abort_unless($request->user()?->canAccessWorkLocation((int) $data['work_location_id']), 403);

        try {
            $opname = $service->create($data, $request->user());
        } catch (ServiceException $exception) {
            return back()
                ->withInput()
                ->withErrors(['work_location_id' => $exception->getMessage()])
                ->with('notification', ['type' => 'warning', 'message' => $exception->getMessage()]);
        }

        return redirect()->route('warehouse.stock-opnames.show', $opname)->with('notification', ['type' => 'success', 'message' => "Opname {$opname->number} berhasil dibuat."]);
    }

    public function show(StockOpname $stockOpname): View
    {
        $this->authorize('view', $stockOpname);

        return view('warehouse.stock-opnames.show', ['opname' => $this->loadOpname($stockOpname)]);
    }

    public function start(Request $request, StockOpname $stockOpname, StockOpnameService $service): RedirectResponse
    {
        $this->authorize('start', $stockOpname);

        try {
            $service->start($stockOpname, $request->user());
        } catch (ServiceException $exception) {
            return back()
                ->withErrors(['stock_opname' => $exception->getMessage()])
                ->with('notification', ['type' => 'warning', 'message' => $exception->getMessage()]);
        }

        return redirect()->route('warehouse.stock-opnames.count', $stockOpname)->with('notification', ['type' => 'success', 'message' => 'Acuan stok berhasil disimpan dan proses penghitungan dimulai.']);
    }

    public function count(StockOpname $stockOpname): View
    {
        $this->authorize('count', $stockOpname);

        return view('warehouse.stock-opnames.count', ['opname' => $this->loadOpname($stockOpname), 'reasons' => StockOpnameReason::options()]);
    }

    public function countItem(CountStockOpnameItemRequest $request, StockOpname $stockOpname, StockOpnameItem $item, StockOpnameService $service): RedirectResponse
    {
        $this->authorize('count', $stockOpname);
        abort_unless((int) $item->stock_opname_id === (int) $stockOpname->id, 404);
        $data = $request->validated();
        if ($request->hasFile('evidence')) {
            $data['evidence_path'] = $request->file('evidence')?->store('stock-opnames', 'public');
        }

        $service->countItem($item, $data, $request->user());

        return back()->with('notification', ['type' => 'success', 'message' => 'Jumlah fisik berhasil disimpan.']);
    }

    public function import(ImportStockOpnameCountsRequest $request, StockOpname $stockOpname, StockOpnameService $service): RedirectResponse
    {
        $this->authorize('count', $stockOpname);
        $rows = $this->parseCsv($request->file('import_file')?->getRealPath() ?: '');
        $service->importCounts($stockOpname, $rows, $request->user());

        return back()->with('notification', ['type' => 'success', 'message' => 'Import count berhasil diproses.']);
    }

    public function submit(Request $request, StockOpname $stockOpname, StockOpnameService $service): RedirectResponse
    {
        $this->authorize('submit', $stockOpname);
        $service->submit($stockOpname, $request->user());

        return redirect()->route('warehouse.stock-opnames.variance', $stockOpname)->with('notification', ['type' => 'success', 'message' => 'Opname diajukan untuk approval.']);
    }

    public function variance(StockOpname $stockOpname): View
    {
        $this->authorize('view', $stockOpname);

        $opname = $this->loadOpname($stockOpname);
        $lastMutations = StockMutation::query()
            ->where('work_location_id', $opname->work_location_id)
            ->whereIn('product_id', $opname->items->pluck('product_id'))
            ->when($opname->started_at, fn ($query) => $query->where('occurred_at', '>', $opname->started_at))
            ->latest('occurred_at')
            ->latest('id')
            ->get()
            ->unique(fn (StockMutation $mutation): string => $mutation->product_id.'|'.($mutation->warehouse_location_id ?? 'null'))
            ->keyBy(fn (StockMutation $mutation): string => $mutation->product_id.'|'.($mutation->warehouse_location_id ?? 'null'));

        return view('warehouse.stock-opnames.variance', [
            'opname' => $opname,
            'reasons' => StockOpnameReason::options(),
            'lastMutations' => $lastMutations,
            'summary' => $this->opnameReviewSummary($opname),
        ]);
    }

    public function exportVariance(StockOpname $stockOpname): StreamedResponse
    {
        $this->authorize('view', $stockOpname);
        $stockOpname->load('items.product', 'items.warehouseLocation');

        return response()->streamDownload(function () use ($stockOpname): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU', 'Produk', 'Lokasi', 'Stok Sistem', 'Jumlah Fisik', 'Selisih', 'Nilai', 'Alasan', 'Transaksi Setelah Acuan Stok']);
            foreach ($stockOpname->items as $item) {
                fputcsv($handle, [$item->product_sku_snapshot, $item->product_name_snapshot, $item->warehouseLocation?->full_code, $item->system_qty_snapshot, $item->counted_qty, $item->difference_qty, $item->estimated_value, $item->reasonEnum()?->label(), $item->has_transaction_after_snapshot ? 'Ya' : 'Tidak']);
            }
            fclose($handle);
        }, $stockOpname->number.'-variance.csv');
    }

    public function approval(StockOpname $stockOpname): View
    {
        $this->authorize('view', $stockOpname);

        $opname = $this->loadOpname($stockOpname);

        return view('warehouse.stock-opnames.approval', [
            'opname' => $opname,
            'summary' => $this->opnameReviewSummary($opname),
        ]);
    }

    public function approve(ApproveStockOpnameRequest $request, StockOpname $stockOpname, StockOpnameService $service): RedirectResponse
    {
        $this->authorize('approve', $stockOpname);
        $validated = $request->validated();
        $service->approve($stockOpname, $request->user(), $validated['notes']);

        return back()->with('notification', ['type' => 'success', 'message' => 'Opname berhasil disetujui.']);
    }

    public function reject(ApproveStockOpnameRequest $request, StockOpname $stockOpname, StockOpnameService $service): RedirectResponse
    {
        $this->authorize('approve', $stockOpname);
        $validated = $request->validated();
        $service->reject($stockOpname, $request->user(), $validated['notes']);

        return back()->with('notification', ['type' => 'success', 'message' => 'Opname ditolak.']);
    }

    public function complete(Request $request, StockOpname $stockOpname, StockOpnameService $service): RedirectResponse
    {
        $this->authorize('complete', $stockOpname);
        $service->complete($stockOpname, $request->user());

        return redirect()->route('warehouse.stock-opnames.report', $stockOpname)->with('notification', ['type' => 'success', 'message' => 'Adjustment opname selesai dibuat.']);
    }

    public function report(StockOpname $stockOpname): View
    {
        $this->authorize('view', $stockOpname);

        return view('warehouse.stock-opnames.report', ['opname' => $this->loadOpname($stockOpname)]);
    }

    private function query(Request $request): mixed
    {
        return StockOpname::query()
            ->with(['workLocation', 'warehouseLocation', 'pic', 'items'])
            ->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? [])
            ->when($request->integer('work_location_id') > 0, fn ($query) => $query->where('work_location_id', $request->integer('work_location_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->latest('scheduled_at')
            ->latest('id');
    }

    private function loadOpname(StockOpname $stockOpname): StockOpname
    {
        return $stockOpname->load([
            'workLocation', 'warehouseLocation', 'category', 'pic', 'creator', 'approver',
            'items.product', 'items.warehouseLocation', 'items.counter', 'items.counts.counter',
            'approvals.approver', 'statusHistories.actor', 'stockMutations.product', 'stockMutations.actor',
        ]);
    }

    /** @return array<string, int|string> */
    private function opnameReviewSummary(StockOpname $opname): array
    {
        $counted = $opname->items->whereNotNull('counted_qty');
        $different = $counted->filter(fn (StockOpnameItem $item): bool => Decimal::compare((string) $item->difference_qty, '0') !== 0);
        $aboveThreshold = $counted->filter(function (StockOpnameItem $item) use ($opname): bool {
            $absoluteQuantity = ltrim((string) $item->difference_qty, '-');
            $absoluteValue = ltrim((string) $item->estimated_value, '-');

            return Decimal::compare($absoluteQuantity, (string) $opname->threshold_qty) > 0
                || Decimal::compare($absoluteValue, (string) $opname->threshold_value, 2) > 0;
        });

        return [
            'total' => $opname->items->count(),
            'uncounted' => $opname->items->count() - $counted->count(),
            'matching' => $counted->count() - $different->count(),
            'different' => $different->count(),
            'above_threshold' => $aboveThreshold->count(),
            'after_reference' => $opname->items->where('has_transaction_after_snapshot', true)->count(),
            'quantity_difference' => (string) $opname->total_difference_qty,
            'value_difference' => (string) $opname->total_difference_value,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function parseCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return $rows;
        }

        $header = fgetcsv($handle) ?: [];
        while (($line = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($header as $index => $name) {
                $row[(string) $name] = $line[$index] ?? null;
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }
}
