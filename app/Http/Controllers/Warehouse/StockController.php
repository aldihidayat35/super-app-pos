<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Stock;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Stock::class);

        $locationIds = $request->user()?->permittedWorkLocationIds() ?? [];

        return view('warehouse.stocks.index', [
            'stocks' => $this->query($request)->paginate(20)->withQueryString(),
            'products' => Product::query()->where('status', 'active')->whereHas('stocks', fn ($query) => $query->whereIn('work_location_id', $locationIds))->orderBy('name')->limit(200)->get(),
            'workLocations' => WorkLocation::query()->whereIn('id', $locationIds)->orderBy('name')->get(),
            'warehouseLocations' => WarehouseLocation::query()->where('is_active', true)->whereHas('warehouse', fn ($query) => $query->whereIn('work_location_id', $locationIds))->orderBy('full_code')->limit(300)->get(),
            'filters' => $request->only(['product_id', 'work_location_id', 'warehouse_location_id', 'status']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Stock::class);

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU', 'Produk', 'Lokasi Kerja', 'Lokasi Gudang', 'On Hand', 'Reserved', 'Damaged', 'Available', 'Minimum', 'Safety Stock', 'Nilai HPP']);

            $this->query($request)->chunk(200, function ($stocks) use ($handle): void {
                foreach ($stocks as $stock) {
                    fputcsv($handle, [
                        $stock->product?->sku,
                        $stock->product?->name,
                        $stock->workLocation?->name,
                        $stock->warehouseLocation?->full_code ?: '-',
                        $stock->quantity_on_hand,
                        $stock->quantity_reserved,
                        $stock->quantity_damaged,
                        $stock->available_quantity,
                        $stock->product?->minimum_stock,
                        $stock->product?->safety_stock,
                        $stock->cost_value,
                    ]);
                }
            });

            fclose($handle);
        }, 'saldo-stok.csv');
    }

    private function query(Request $request): mixed
    {
        $status = $request->query('status');

        return Stock::query()
            ->with(['product.category', 'workLocation', 'warehouseLocation'])
            ->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? [])
            ->when($request->integer('product_id') > 0, fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->integer('work_location_id') > 0, fn ($query) => $query->where('work_location_id', $request->integer('work_location_id')))
            ->when($request->integer('warehouse_location_id') > 0, fn ($query) => $query->where('warehouse_location_id', $request->integer('warehouse_location_id')))
            ->when($status === 'critical', fn ($query) => $query->whereHas('product', fn ($inner) => $inner->whereColumn('stocks.quantity_on_hand', '<=', 'products.minimum_stock')))
            ->when($status === 'empty', fn ($query) => $query->where('quantity_on_hand', '<=', 0))
            ->orderBy('work_location_id')
            ->orderBy('product_id');
    }
}
