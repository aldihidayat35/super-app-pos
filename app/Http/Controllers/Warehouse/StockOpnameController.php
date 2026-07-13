<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\ApproveStockOpnameRequest;
use App\Http\Requests\Warehouse\CountStockOpnameItemRequest;
use App\Http\Requests\Warehouse\ImportStockOpnameCountsRequest;
use App\Http\Requests\Warehouse\StoreStockOpnameRequest;
use App\Models\ProductCategory;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Warehouse\StockOpnameService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockOpnameController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockOpname::class);

        return view('warehouse.stock-opnames.index', [
            'opnames' => $this->query($request)->paginate(15)->withQueryString(),
            'workLocations' => WorkLocation::query()->whereIn('id', $request->user()?->permittedWorkLocationIds() ?? [])->where('is_active', true)->orderBy('name')->get(),
            'warehouseLocations' => WarehouseLocation::query()->where('is_active', true)->orderBy('full_code')->limit(300)->get(),
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
        $opname = $service->create($data, $request->user());

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
        $service->start($stockOpname, $request->user());

        return redirect()->route('warehouse.stock-opnames.count', $stockOpname)->with('notification', ['type' => 'success', 'message' => 'Snapshot dibuat dan counting dimulai.']);
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

        return back()->with('notification', ['type' => 'success', 'message' => 'Qty fisik berhasil disimpan.']);
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

        return view('warehouse.stock-opnames.variance', ['opname' => $this->loadOpname($stockOpname), 'reasons' => StockOpnameReason::options()]);
    }

    public function exportVariance(StockOpname $stockOpname): StreamedResponse
    {
        $this->authorize('view', $stockOpname);
        $stockOpname->load('items.product', 'items.warehouseLocation');

        return response()->streamDownload(function () use ($stockOpname): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU', 'Produk', 'Lokasi', 'Sistem', 'Fisik', 'Selisih', 'Nilai', 'Alasan', 'Transaksi Setelah Snapshot']);
            foreach ($stockOpname->items as $item) {
                fputcsv($handle, [$item->product_sku_snapshot, $item->product_name_snapshot, $item->warehouseLocation?->full_code, $item->system_qty_snapshot, $item->counted_qty, $item->difference_qty, $item->estimated_value, $item->reasonEnum()?->label(), $item->has_transaction_after_snapshot ? 'Ya' : 'Tidak']);
            }
            fclose($handle);
        }, $stockOpname->number.'-variance.csv');
    }

    public function approval(StockOpname $stockOpname): View
    {
        $this->authorize('view', $stockOpname);

        return view('warehouse.stock-opnames.approval', ['opname' => $this->loadOpname($stockOpname)]);
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
