<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\StockMutationType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMutation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', Stock::class);

        $locationIds = $request->user()?->permittedWorkLocationIds() ?? [];
        $stockQuery = Stock::query()->whereIn('work_location_id', $locationIds);

        $totals = (clone $stockQuery)->selectRaw('
            COALESCE(SUM(quantity_on_hand), 0) as on_hand,
            COALESCE(SUM(quantity_reserved), 0) as reserved,
            COALESCE(SUM(quantity_damaged), 0) as damaged,
            COALESCE(SUM(cost_value), 0) as value
        ')->first();

        $criticalStocks = (clone $stockQuery)
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->whereColumn('stocks.quantity_on_hand', '<=', 'products.minimum_stock')
            ->count();

        $emptyStocks = (clone $stockQuery)->where('quantity_on_hand', '<=', 0)->count();

        $mutationSummary = StockMutation::query()
            ->whereIn('work_location_id', $locationIds)
            ->where('occurred_at', '>=', now()->subDays(30))
            ->select('mutation_type', DB::raw('COUNT(*) as total'))
            ->groupBy('mutation_type')
            ->pluck('total', 'mutation_type');

        $largeMutations = StockMutation::query()
            ->with(['product', 'workLocation', 'actor'])
            ->whereIn('work_location_id', $locationIds)
            ->where(function ($query): void {
                $query->where('quantity_on_hand_change', '>=', 100)
                    ->orWhere('quantity_on_hand_change', '<=', -100);
            })
            ->latest('occurred_at')
            ->limit(8)
            ->get();

        $chart = StockMutation::query()
            ->whereIn('work_location_id', $locationIds)
            ->where('occurred_at', '>=', now()->subDays(30)->startOfDay())
            ->selectRaw('DATE(occurred_at) as date, SUM(CASE WHEN quantity_on_hand_change > 0 THEN quantity_on_hand_change ELSE 0 END) as masuk, SUM(CASE WHEN quantity_on_hand_change < 0 THEN ABS(quantity_on_hand_change) ELSE 0 END) as keluar')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return view('warehouse.dashboard', [
            'totals' => $totals,
            'criticalStocks' => $criticalStocks,
            'emptyStocks' => $emptyStocks,
            'incomingCount' => (int) (($mutationSummary[StockMutationType::RECEIVE->value] ?? 0) + ($mutationSummary[StockMutationType::RETURN_IN->value] ?? 0) + ($mutationSummary[StockMutationType::TRANSFER_IN->value] ?? 0)),
            'outgoingCount' => (int) (($mutationSummary[StockMutationType::ISSUE->value] ?? 0) + ($mutationSummary[StockMutationType::RETURN_OUT->value] ?? 0) + ($mutationSummary[StockMutationType::TRANSFER_OUT->value] ?? 0)),
            'largeMutations' => $largeMutations,
            'chart' => $chart,
            'activeProductCount' => Product::query()->where('status', 'active')->count(),
        ]);
    }
}
