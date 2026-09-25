<?php

namespace App\Http\Controllers\Warehouse;

use App\Exports\ArrayReportExport;
use App\Http\Controllers\Controller;
use App\Services\System\InitialDataImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OpeningStockImportController extends Controller
{
    public function index(Request $request, InitialDataImportService $imports): View
    {
        abort_unless($request->user()?->can('opening_stocks.view'), 403);

        $templates = $imports->templates();
        $template = $templates['opening_stocks'] ?? null;
        $preview = session('opening_stock_import_preview');

        return view('warehouse.opening-stocks.import', [
            'template' => $template,
            'preview' => $preview,
        ]);
    }

    public function template(Request $request, InitialDataImportService $imports): BinaryFileResponse
    {
        abort_unless($request->user()?->can('opening_stocks.view'), 403);

        $template = $imports->templates()['opening_stocks'] ?? abort(404);

        return Excel::download(
            new ArrayReportExport([], array_values($template['columns'])),
            'template-opening-stocks.xlsx',
            ExcelFormat::XLSX,
        );
    }

    public function preview(Request $request, InitialDataImportService $imports): RedirectResponse
    {
        abort_unless($request->user()?->can('opening_stocks.create'), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ], [
            'file.required' => 'Pilih file Excel XLSX terlebih dahulu.',
            'file.mimes' => 'Format file harus berupa Excel (.xlsx).',
            'file.max' => 'Ukuran file maksimal 5MB.',
        ]);

        $preview = $imports->preview('opening_stocks', $request->file('file'), true);

        session()->put('opening_stock_import_preview', $preview);

        return back()->with('notification', [
            'type' => $preview['errors'] === [] ? 'success' : 'warning',
            'message' => $preview['errors'] === []
                ? 'Preview valid. Silakan periksa data di bawah dan lakukan konfirmasi commit untuk menyimpan ke database.'
                : 'Preview menemukan error validasi pada beberapa baris.',
        ]);
    }

    public function commit(Request $request, InitialDataImportService $imports): RedirectResponse
    {
        abort_unless($request->user()?->can('opening_stocks.create'), 403);

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

        $result = $imports->commit($preview, $request->user());

        session()->forget('opening_stock_import_preview');

        activity()
            ->causedBy($request->user())
            ->withProperties(['result' => $result])
            ->log('warehouse.opening_stocks.imported');

        return redirect()->route('warehouse.opening-stocks.index')->with('notification', [
            'type' => 'success',
            'message' => "Import stok awal berhasil disimpan. {$result['changed_rows']} baris saldo diperbarui, {$result['hpp_recorded_rows']} histori HPP dicatat, dan {$result['unchanged_rows']} baris tidak berubah.",
        ]);
    }
}
