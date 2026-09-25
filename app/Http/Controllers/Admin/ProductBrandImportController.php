<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewProductImportRequest;
use App\Models\ProductBrand;
use App\Services\Product\ProductBrandImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['code', 'name', 'description', 'is_active']);
            fputcsv($handle, ['BRD-TORABIKA', 'Torabika', 'Produk kopi Torabika Mayora', '1']);
            fputcsv($handle, ['BRD-INDOMIE', 'Indomie', 'Produk mie instan Indomie Indofood', '1']);
            fputcsv($handle, ['BRD-KAPALAPI', 'Kapal Api', 'Kopi bubuk Kapal Api Santos Jaya Abadi', '1']);
            fclose($handle);
        }, 'template-import-merek.csv');
    }
}
