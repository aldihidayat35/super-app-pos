<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BarcodePaperSize;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductBarcodePrintRequest;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Milon\Barcode\DNS1D;
use Milon\Barcode\DNS2D;
use Symfony\Component\HttpFoundation\Response;

class ProductBarcodeController extends Controller
{
    public function index(ProductBarcodePrintRequest $request): View
    {
        $this->authorize('printBarcode', Product::class);
        $data = $request->validated();
        $paperSize = BarcodePaperSize::from($data['paper_size']);
        $labelCount = (int) $data['label_count'];
        $selectedProductId = isset($data['product_id']) ? (int) $data['product_id'] : null;

        $products = $this->products($selectedProductId);
        $labels = $this->labels($products, $labelCount);

        return view('admin.products.barcode-print', [
            'products' => $products,
            'productOptions' => Product::query()->where('status', 'active')->orderBy('name')->limit(200)->get(['id', 'sku', 'name']),
            'selectedProductId' => $selectedProductId,
            'labelCount' => $labelCount,
            'paperSize' => $paperSize,
            'paperOptions' => BarcodePaperSize::options(),
            'labelPages' => array_chunk($labels, $paperSize->labelsPerPage()),
            'totalLabels' => count($labels),
        ]);
    }

    public function pdf(ProductBarcodePrintRequest $request): Response
    {
        $this->authorize('printBarcode', Product::class);
        $data = $request->validated();
        $paperSize = BarcodePaperSize::from($data['paper_size']);
        $labelCount = (int) $data['label_count'];
        $selectedProductId = isset($data['product_id']) ? (int) $data['product_id'] : null;
        $labels = $this->labels($this->products($selectedProductId), $labelCount);

        return Pdf::loadView('admin.products.barcodes-pdf', [
            'paperSize' => $paperSize,
            'labelPages' => array_chunk($labels, $paperSize->labelsPerPage()),
        ])
            ->setPaper($paperSize->dompdfPaper(), 'portrait')
            ->setOption(['dpi' => 96, 'isHtml5ParserEnabled' => true])
            ->stream('barcode-produk-'.$paperSize->value.'.pdf');
    }

    /** @return Collection<int, Product> */
    private function products(?int $productId): Collection
    {
        return Product::query()
            ->with([
                'barcodes' => fn ($query) => $query->with('productUnit.unit')->orderByDesc('is_primary')->orderBy('id'),
                'baseUnit',
            ])
            ->where('status', 'active')
            ->when($productId !== null, fn ($query) => $query->whereKey($productId))
            ->orderBy('name')
            ->limit(100)
            ->get();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array{product_name: string, sku: string, code: string, unit: string, is_qr: bool, image: string}>
     */
    private function labels(Collection $products, int $labelCount): array
    {
        $labels = [];

        foreach ($products as $product) {
            $barcode = $product->barcodes->first();
            $code = (string) ($barcode?->code ?: $product->sku);
            $isQr = $barcode?->type === 'qr';
            $image = $isQr
                ? (new DNS2D)->getBarcodePNG($code, 'QRCODE', 4, 4)
                : (new DNS1D)->getBarcodePNG($code, 'C128', 2, 54);
            $label = [
                'product_name' => $product->name,
                'sku' => $product->sku,
                'code' => $code,
                'unit' => (string) ($barcode?->productUnit?->unit?->symbol ?: $product->baseUnit?->symbol ?: '-'),
                'is_qr' => $isQr,
                'image' => (string) $image,
            ];

            for ($index = 0; $index < $labelCount; $index++) {
                $labels[] = $label;
            }
        }

        return $labels;
    }
}
