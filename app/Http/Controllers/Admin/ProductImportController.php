<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewProductImportRequest;
use App\Models\Product;
use App\Services\Product\ProductImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('import', Product::class);
        $preview = $request->session()->get('product_import_preview');

        if (is_array($preview)) {
            $request->session()->keep('product_import_preview');
        }

        return view('admin.products.import', [
            'preview' => $preview,
            'result' => $request->session()->get('product_import_result'),
        ]);
    }

    public function preview(PreviewProductImportRequest $request, ProductImportService $service): RedirectResponse
    {
        $preview = $service->preview($request->file('file'));

        return redirect()->route('admin.products.import.index')->with('product_import_preview', $preview);
    }

    public function commit(Request $request, ProductImportService $service): RedirectResponse
    {
        $this->authorize('import', Product::class);
        $preview = $request->session()->get('product_import_preview');

        if (! is_array($preview) || filled($preview['errors'] ?? [])) {
            return back()->with('notification', ['type' => 'danger', 'message' => 'Import belum dapat diproses karena masih ada error validasi.']);
        }

        $result = $service->commit($preview['rows'] ?? []);
        activity()->causedBy($request->user())->log('product.import.committed');
        $request->session()->forget('product_import_preview');

        return redirect()->route('admin.products.import.index')->with('product_import_result', $result)->with('notification', ['type' => 'success', 'message' => 'Import produk berhasil diproses.']);
    }

    public function template(): StreamedResponse
    {
        $this->authorize('export', Product::class);

        return response()->streamDownload(function (): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->fromArray([
                ['sku', 'name', 'category_code', 'brand_code', 'base_unit_code', 'minimum_order', 'minimum_stock', 'safety_stock'],
                ['PRD-CONTOH-001', 'Produk Contoh', 'UMUM', 'NO-BRAND', 'PCS', '1', '10', '5'],
            ]);

            foreach (range('A', 'H') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 'template-import-produk.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
