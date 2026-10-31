<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewProductImportRequest;
use App\Models\ProductBrand;
use App\Services\Product\ProductBrandImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductBrandImportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('import', ProductBrand::class);
        $preview = $request->session()->get('product_brand_import_preview');

        if (is_array($preview)) {
            $request->session()->keep('product_brand_import_preview');
        }

        return view('admin.product-brands.import', [
            'preview' => $preview,
            'result' => $request->session()->get('product_brand_import_result'),
        ]);
    }

    public function preview(PreviewProductImportRequest $request, ProductBrandImportService $service): RedirectResponse
    {
        $this->authorize('import', ProductBrand::class);
        $preview = $service->preview($request->file('file'));

        return redirect()->route('admin.product-brands.import.index')->with('product_brand_import_preview', $preview);
    }

    public function commit(Request $request, ProductBrandImportService $service): RedirectResponse
    {
        $this->authorize('import', ProductBrand::class);
        $preview = $request->session()->get('product_brand_import_preview');

        if (! is_array($preview) || filled($preview['errors'] ?? [])) {
            return back()->with('notification', ['type' => 'danger', 'message' => 'Import merek belum dapat diproses karena masih ada error validasi.']);
        }

        $result = $service->commit($preview['rows'] ?? []);
        activity()->causedBy($request->user())->log('product_brand.import.committed');
        $request->session()->forget('product_brand_import_preview');

        return redirect()->route('admin.product-brands.import.index')
            ->with('product_brand_import_result', $result)
            ->with('notification', ['type' => 'success', 'message' => 'Import merek produk berhasil diproses.']);
    }

    public function template(): StreamedResponse
    {
        $this->authorize('export', ProductBrand::class);

        return response()->streamDownload(function (): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->fromArray([
                ['code', 'name', 'description', 'is_active'],
                ['BRD-TORABIKA', 'Torabika', 'Produk kopi Torabika Mayora', '1'],
                ['BRD-INDOMIE', 'Indomie', 'Produk mie instan Indomie Indofood', '1'],
                ['BRD-KAPALAPI', 'Kapal Api', 'Kopi bubuk Kapal Api Santos Jaya Abadi', '1'],
            ]);

            foreach (range('A', 'D') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 'template-import-merek.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
