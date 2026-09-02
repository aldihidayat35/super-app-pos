<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Models\WorkLocation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('sales.stock.view'), 403);
        $locationIds = $request->user()->permittedWorkLocationIds();
        $filters = ['q' => trim((string) $request->query('q')), 'work_location_id' => $request->integer('work_location_id') ?: null];

        return view('sales.stocks.index', [
            'filters' => $filters,
            'workLocations' => WorkLocation::query()->whereIn('id', $locationIds)->where('is_active', true)->orderBy('name')->get(),
            'stocks' => Stock::query()
                ->with(['product', 'workLocation', 'warehouseLocation'])
                ->whereIn('work_location_id', $locationIds)
                ->when($filters['q'] !== '', function ($query) use ($filters): void {
                    $query->whereHas('product', fn ($product) => $product
                        ->where('sku', 'like', "%{$filters['q']}%")
                        ->orWhere('name', 'like', "%{$filters['q']}%"));
                })
                ->when($filters['work_location_id'], fn ($query, int $locationId) => $query->where('work_location_id', $locationId))
                ->orderBy('work_location_id')
                ->orderBy('product_id')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }
}
