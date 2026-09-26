<?php

namespace App\Http\Controllers\Warehouse;

use App\Exports\ArrayReportExport;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\System\InitialDataImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OpeningStockImportController extends Controller
{
    public function index(Request $request, InitialDataImportService $imports): View
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $templates = $imports->templates();
        $template = $templates['opening_stocks'] ?? null;
        $preview = session('opening_stock_import_preview');

        $permittedIds = $user?->permittedWorkLocationIds() ?? [];

        $workLocations = WorkLocation::query()
            ->whereIn('id', $permittedIds)
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::query()
            ->whereIn('work_location_id', $permittedIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $branches = Branch::query()
            ->whereIn('work_location_id', $permittedIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        /** @var Collection<int, WarehouseLocation> $bins */
        $bins = WarehouseLocation::query()
            ->forWorkLocationIds($permittedIds)
            ->where('type', 'bin')
            ->where('is_active', true)
            ->with(['warehouse:id,work_location_id', 'branch:id,work_location_id'])
            ->orderBy('full_code')
            ->get();

        $binsByLocation = $bins->groupBy(function (WarehouseLocation $bin): int {
            return (int) ($bin->workLocationId() ?? 0);
        })->map(function ($group) {
            return $group->map(fn (WarehouseLocation $b): array => [
                'id' => $b->id,
                'code' => $b->code,
                'full_code' => $b->full_code,
                'name' => $b->name,
            ])->values()->all();
        })->all();

        return view('warehouse.opening-stocks.import', [
            'template' => $template,
            'preview' => $preview,
            'workLocations' => $workLocations,
            'warehouses' => $warehouses,
            'branches' => $branches,
            'binsByLocation' => $binsByLocation,
        ]);
    }

    public function template(Request $request, InitialDataImportService $imports): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $template = $imports->templates()['opening_stocks'] ?? abort(404);

        return Excel::download(
            new ArrayReportExport([], array_values($template['columns'])),
            'template-import-stok-produk.xlsx',
            ExcelFormat::XLSX,
        );
    }

    public function preview(Request $request, InitialDataImportService $imports): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canCreate($user), 403);

        $request->validate([
            'work_location_id' => ['required', 'integer', 'exists:work_locations,id'],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ], [
            'work_location_id.required' => 'Pilih lokasi induk (gudang atau cabang) terlebih dahulu.',
            'work_location_id.exists' => 'Lokasi gudang atau cabang yang dipilih tidak valid.',
            'file.required' => 'Pilih file Excel XLSX terlebih dahulu.',
            'file.mimes' => 'Format file harus berupa Excel (.xlsx).',
            'file.max' => 'Ukuran file maksimal 5MB.',
        ]);

        $workLocationId = (int) $request->input('work_location_id');
        $permittedIds = $user?->permittedWorkLocationIds() ?? [];

        if (! in_array($workLocationId, $permittedIds, true)) {
            return back()->with('notification', [
                'type' => 'danger',
                'message' => 'Anda tidak memiliki hak akses ke lokasi gudang/cabang yang dipilih.',
            ])->withInput();
        }

        $preview = $imports->preview('opening_stocks', $request->file('file'), $workLocationId);

        session()->put('opening_stock_import_preview', $preview);

        return back()->with('notification', [
            'type' => $preview['errors'] === [] ? 'success' : 'warning',
            'message' => $preview['errors'] === []
                ? 'Preview valid. Silakan periksa data di bawah dan lakukan konfirmasi commit untuk menyimpan ke database.'
                : 'Preview menemukan error validasi pada beberapa baris.',
        ])->withInput();
    }

    public function commit(Request $request, InitialDataImportService $imports): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canCreate($user), 403);

        $preview = session('opening_stock_import_preview');

        if (! is_array($preview) || ($preview['type'] ?? null) !== 'opening_stocks') {
            return back()->with('notification', [
                'type' => 'warning',
                'message' => 'Hasil preview tidak tersedia atau kedaluwarsa. Silakan unggah dan preview ulang file XLSX.',
            ]);
        }

        if (($preview['errors'] ?? []) !== []) {
            return back()->with('notification', [
                'type' => 'warning',
                'message' => 'Commit dibatalkan karena preview masih memiliki error validasi.',
            ]);
        }

        $workLocationId = (int) ($preview['extra_data'] ?? 0);
        $permittedIds = $user?->permittedWorkLocationIds() ?? [];

        if (! in_array($workLocationId, $permittedIds, true)) {
            return back()->with('notification', [
                'type' => 'danger',
                'message' => 'Anda tidak memiliki hak akses ke lokasi gudang/cabang target import.',
            ]);
        }

        $result = $imports->commit($preview, $user);

        session()->forget('opening_stock_import_preview');

        activity()
            ->causedBy($user)
            ->withProperties(['result' => $result, 'work_location_id' => $workLocationId])
            ->log('warehouse.opening_stocks.imported');

        $location = WorkLocation::query()->find($workLocationId);
        $locationName = $location !== null ? $location->name : 'lokasi';

        return redirect()->route('warehouse.opening-stocks.import.index')->with('notification', [
            'type' => 'success',
            'message' => "Import stok produk ke {$locationName} berhasil disimpan! {$result['changed_rows']} baris saldo diperbarui, {$result['hpp_recorded_rows']} histori HPP dicatat, dan {$result['unchanged_rows']} baris tidak berubah.",
        ])->with('import_success_work_location_id', $workLocationId);
    }

    public function cancel(): RedirectResponse
    {
        session()->forget('opening_stock_import_preview');

        return redirect()->route('warehouse.opening-stocks.import.index')->with('notification', [
            'type' => 'info',
            'message' => 'Preview import dibatalkan. Anda dapat memilih kembali lokasi dan file.',
        ]);
    }

    private function canView(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can('opening_stocks.view')
            || $user->can('products.import')
            || $user->can('stock.view')
            || $user->can('retail.catalog.manage');
    }

    private function canCreate(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can('opening_stocks.create')
            || $user->can('products.import')
            || $user->can('stock_adjustments.create')
            || $user->can('retail.catalog.manage');
    }
}
