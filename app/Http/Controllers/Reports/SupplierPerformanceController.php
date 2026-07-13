<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierScore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SupplierPerformanceController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', SupplierScore::class);

        $scores = SupplierScore::query()
            ->with(['supplier', 'goodsReceipt.items'])
            ->when($request->integer('supplier_id') > 0, fn ($query) => $query->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->integer('product_id') > 0, fn ($query) => $query->whereHas('goodsReceipt.items', fn ($items) => $items->where('product_id', $request->integer('product_id'))))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('received_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('received_at', '<=', $request->query('date_to')))
            ->latest('received_at')
            ->paginate(15)
            ->withQueryString();

        return view('reports.suppliers.index', [
            'scores' => $scores,
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'products' => Product::query()->orderBy('name')->limit(200)->get(),
            'filters' => $request->only(['supplier_id', 'product_id', 'date_from', 'date_to']),
        ]);
    }
}
