<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewProductImportRequest;
use App\Models\Product;
use App\Services\Product\ProductImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImportController extends Controller
{
    public function index(): View
    {
        $this->authorize('import', Product::class);

        return view('admin.products.import', [
            'preview' => session('product_import_preview'),
            'result' => session('product_import_result'),
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
        $preview = session('product_import_preview');

        if (! is_array($preview) || filled($preview['errors'] ?? [])) {
            return back()->with('notification', ['type' => 'danger', 'message' => 'Import belum dapat diproses karena masih ada error validasi.']);
        }

        $result = $service->commit($preview['rows'] ?? []);
        activity()->causedBy($request->user())->log('product.import.committed');

        return redirect()->route('admin.products.import.index')->with('product_import_result', $result)->with('notification', ['type' => 'success', 'message' => 'Import produk berhasil diproses.']);
    }

    public function template(): StreamedResponse
    {
        $this->authorize('export', Product::class);

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['sku', 'name', 'category_code', 'brand_code', 'base_unit_code', 'status', 'minimum_order', 'minimum_stock', 'safety_stock']);
            fputcsv($handle, ['PRD-CONTOH-001', 'Produk Contoh', 'UMUM', 'NO-BRAND', 'PCS', 'active', '1', '10', '5']);
            fclose($handle);
        }, 'template-import-produk.csv');
    }
}
