<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewProductImportRequest;
use App\Models\ProductCategory;
use App\Services\Product\ProductCategoryImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductCategoryImportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('import', ProductCategory::class);
        $preview = $request->session()->get('product_category_import_preview');

        if (is_array($preview)) {
            $request->session()->keep('product_category_import_preview');
        }

        return view('admin.product-categories.import', [
            'preview' => $preview,
            'result' => $request->session()->get('product_category_import_result'),
        ]);
    }

    public function preview(PreviewProductImportRequest $request, ProductCategoryImportService $service): RedirectResponse
    {
        $this->authorize('import', ProductCategory::class);
        $preview = $service->preview($request->file('file'));

        return redirect()->route('admin.product-categories.import.index')->with('product_category_import_preview', $preview);
    }

    public function commit(Request $request, ProductCategoryImportService $service): RedirectResponse
    {
        $this->authorize('import', ProductCategory::class);
        $preview = $request->session()->get('product_category_import_preview');

        if (! is_array($preview) || filled($preview['errors'] ?? [])) {
            return back()->with('notification', ['type' => 'danger', 'message' => 'Import kategori belum dapat diproses karena masih ada error validasi.']);
        }

        $result = $service->commit($preview['rows'] ?? []);
        activity()->causedBy($request->user())->log('product_category.import.committed');
        $request->session()->forget('product_category_import_preview');

        return redirect()->route('admin.product-categories.import.index')
            ->with('product_category_import_result', $result)
            ->with('notification', ['type' => 'success', 'message' => 'Import kategori produk berhasil diproses.']);
    }

    public function template(): StreamedResponse
    {
        $this->authorize('export', ProductCategory::class);

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['code', 'name', 'parent_code', 'sort_order', 'icon', 'is_active']);
            fputcsv($handle, ['KAT-MINUMAN', 'Minuman', '', '1', 'ki-cup', '1']);
            fputcsv($handle, ['KAT-KOPI', 'Kopi & Espresso', 'KAT-MINUMAN', '2', 'ki-coffee', '1']);
            fputcsv($handle, ['KAT-MAKANAN', 'Makanan Ringan', '', '3', 'ki-burger', '1']);
            fclose($handle);
        }, 'template-import-kategori.csv');
    }
}
