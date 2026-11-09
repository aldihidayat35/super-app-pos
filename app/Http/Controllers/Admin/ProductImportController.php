<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewProductImportRequest;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Services\Product\ProductImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('import', Product::class);

        if ($request->boolean('clear')) {
            $request->session()->forget('product_import_preview');
        }

        $preview = $request->session()->get('product_import_preview');

        $categories = ProductCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
        $units = Unit::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'symbol']);
        $brands = ProductBrand::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);

        return view('admin.products.import', [
            'preview' => $preview,
            'result' => $request->session()->get('product_import_result'),
            'categories' => $categories,
            'units' => $units,
            'brands' => $brands,
        ]);
    }

    public function preview(PreviewProductImportRequest $request, ProductImportService $service): RedirectResponse
    {
        $preview = $service->preview($request->file('file'));
        $request->session()->put('product_import_preview', $preview);

        return redirect()->route('admin.products.import.index');
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

            // Sheet 1: Template Data Import
            $sheet1 = $spreadsheet->getActiveSheet();
            $sheet1->setTitle('Data Import');

            $headers = ['name', 'sku', 'barcode', 'category_code', 'brand_code', 'base_unit_code', 'minimum_order', 'minimum_stock', 'safety_stock'];
            $sheet1->fromArray([
                $headers,
                ['Piring Keramik Putih 8 Inch', '', '', 'UMUM', '', 'PCS', '0', '0', '0'],
            ]);

            // Styling header sheet 1
            $sheet1->getStyle('A1:I1')->getFont()->setBold(true);
            $sheet1->getStyle('A1:I1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E8F4FD');

            foreach (range('A', 'I') as $col) {
                $sheet1->getColumnDimension($col)->setAutoSize(true);
            }

            // Sheet 2: Lembar Referensi & Panduan Sistem
            $sheet2 = $spreadsheet->createSheet();
            $sheet2->setTitle('Referensi Sistem');

            $sheet2->setCellValue('A1', 'PANDUAN & DAFTAR REFERENSI SISTEM GUDANGTOKO');
            $sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(13);

            $sheet2->setCellValue('A2', 'Gunakan kode dari tabel di bawah ini untuk mengisi kolom pada Sheet "Data Import".');
            $sheet2->getStyle('A2')->getFont()->setItalic(true);

            // Ringkasan Aturan Kolom
            $sheet2->setCellValue('A4', 'RINGKASAN ATURAN PENGISIAN KOLOM');
            $sheet2->getStyle('A4')->getFont()->setBold(true);

            $rulesData = [
                ['Nama Kolom', 'Kewajiban', 'Nilai Default / Catatan'],
                ['name', 'Wajib Diisi', 'Nama lengkap produk'],
                ['sku', 'Opsional', 'Kosongkan jika ingin dibuatkan otomatis oleh sistem (format: PRD{mY}{0001})'],
                ['barcode', 'Opsional', 'Kode barcode unik (EAN-13, UPC, dsb) untuk scan kasir/gudang'],
                ['category_code', 'Opsional', 'Kode/nama kategori. Jika dikosongkan otomatis diisi kategori default "UMUM"'],
                ['brand_code', 'Opsional', 'Kode/nama merek. Boleh dikosongkan jika produk belum bermerek'],
                ['base_unit_code', 'Opsional', 'Kode satuan dasar. Jika dikosongkan otomatis diisi satuan dasar "PCS"'],
                ['minimum_order', 'Opsional', 'Default 0'],
                ['minimum_stock', 'Opsional', 'Default 0'],
                ['safety_stock', 'Opsional', 'Default 0'],
            ];
            $sheet2->fromArray($rulesData, null, 'A5');
            $sheet2->getStyle('A5:C5')->getFont()->setBold(true);
            $sheet2->getStyle('A5:C5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F0F0');

            // Tabel Kategori Aktif
            $catStartRow = 17;
            $sheet2->setCellValue("A{$catStartRow}", 'DAFTAR KATEGORI AKTIF DI SISTEM');
            $sheet2->getStyle("A{$catStartRow}")->getFont()->setBold(true);

            $categories = ProductCategory::query()->where('is_active', true)->orderBy('name')->get(['code', 'name']);
            $catHeaderRow = $catStartRow + 1;
            $sheet2->fromArray([['Kode Kategori', 'Nama Kategori']], null, "A{$catHeaderRow}");
            $sheet2->getStyle("A{$catHeaderRow}:B{$catHeaderRow}")->getFont()->setBold(true);
            $sheet2->getStyle("A{$catHeaderRow}:B{$catHeaderRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F4FD');

            $catRows = $categories->map(fn ($c) => [$c->code, $c->name])->toArray();
            if (empty($catRows)) {
                $catRows = [['UMUM', 'Umum']];
            }
            $sheet2->fromArray($catRows, null, 'A'.($catHeaderRow + 1));

            // Tabel Satuan Aktif
            $unitStartRow = $catHeaderRow + count($catRows) + 3;
            $sheet2->setCellValue("A{$unitStartRow}", 'DAFTAR SATUAN AKTIF DI SISTEM');
            $sheet2->getStyle("A{$unitStartRow}")->getFont()->setBold(true);

            $units = Unit::query()->where('is_active', true)->orderBy('name')->get(['code', 'name', 'symbol']);
            $unitHeaderRow = $unitStartRow + 1;
            $sheet2->fromArray([['Kode Satuan', 'Nama Satuan', 'Simbol']], null, "A{$unitHeaderRow}");
            $sheet2->getStyle("A{$unitHeaderRow}:C{$unitHeaderRow}")->getFont()->setBold(true);
            $sheet2->getStyle("A{$unitHeaderRow}:C{$unitHeaderRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F4FD');

            $unitRows = $units->map(fn ($u) => [$u->code, $u->name, $u->symbol])->toArray();
            if (empty($unitRows)) {
                $unitRows = [['PCS', 'Pieces', 'pcs']];
            }
            $sheet2->fromArray($unitRows, null, 'A'.($unitHeaderRow + 1));

            // Tabel Merek Aktif
            $brandStartRow = $unitHeaderRow + count($unitRows) + 3;
            $sheet2->setCellValue("A{$brandStartRow}", 'DAFTAR MEREK AKTIF DI SISTEM');
            $sheet2->getStyle("A{$brandStartRow}")->getFont()->setBold(true);

            $brands = ProductBrand::query()->where('is_active', true)->orderBy('name')->get(['code', 'name']);
            $brandHeaderRow = $brandStartRow + 1;
            $sheet2->fromArray([['Kode Merek', 'Nama Merek']], null, "A{$brandHeaderRow}");
            $sheet2->getStyle("A{$brandHeaderRow}:B{$brandHeaderRow}")->getFont()->setBold(true);
            $sheet2->getStyle("A{$brandHeaderRow}:B{$brandHeaderRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F4FD');

            $brandRows = $brands->map(fn ($b) => [$b->code, $b->name])->toArray();
            if (empty($brandRows)) {
                $brandRows = [['(Belum ada merek terdaftar)', '-']];
            }
            $sheet2->fromArray($brandRows, null, 'A'.($brandHeaderRow + 1));

            foreach (range('A', 'C') as $col) {
                $sheet2->getColumnDimension($col)->setAutoSize(true);
            }

            // Kembalikan active sheet ke Sheet 1 agar user langsung melihat Data Import
            $spreadsheet->setActiveSheetIndex(0);

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 'template-import-produk.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
