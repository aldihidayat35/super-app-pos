<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\StockDirectAdjustment;
use App\Models\StockMutation;
use Illuminate\Contracts\View\View;

class StockMutationController extends Controller
{
    public function show(StockMutation $stockMutation): View
    {
        $this->authorize('view', $stockMutation);

        $directAdjustment = in_array($stockMutation->reference_type, ['stock_direct_adjustment', 'opening_balance_manual'], true) && $stockMutation->reference_id
            ? StockDirectAdjustment::query()->with(['creator', 'approver', 'items' => fn ($query) => $query->where('product_id', $stockMutation->product_id)->where('warehouse_location_id', $stockMutation->warehouse_location_id)])->find($stockMutation->reference_id)
            : null;

        return view('warehouse.stock-mutations.show', [
            'mutation' => $stockMutation->load(['product.baseUnit', 'stock', 'workLocation', 'warehouseLocation', 'actor']),
            'directAdjustment' => $directAdjustment,
        ]);
    }
}
