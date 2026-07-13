<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductBarcodeController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('printBarcode', Product::class);

        $products = Product::query()
            ->with(['barcodes', 'baseUnit'])
            ->when($request->query('product_id'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('name')
            ->limit(100)
            ->get();

        return view('admin.products.barcodes', [
            'products' => $products,
            'selectedProductId' => $request->query('product_id'),
            'labelCount' => (int) $request->query('label_count', 1),
            'paperSize' => $request->query('paper_size', 'A4'),
        ]);
    }

    public function pdf(Request $request): Response
    {
        $this->authorize('printBarcode', Product::class);

        $products = Product::query()
            ->with(['barcodes', 'baseUnit'])
            ->when($request->query('product_id'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('name')
            ->limit(100)
            ->get();

        return Pdf::loadView('admin.products.barcodes-pdf', [
            'products' => $products,
            'labelCount' => max(1, min(100, (int) $request->query('label_count', 1))),
        ])->stream('barcode-produk.pdf');
    }
}
