<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\StockMutation;
use Illuminate\Contracts\View\View;

class StockMutationController extends Controller
{
    public function show(StockMutation $stockMutation): View
    {
        $this->authorize('view', $stockMutation);

        return view('warehouse.stock-mutations.show', [
            'mutation' => $stockMutation->load(['product.baseUnit', 'stock', 'workLocation', 'warehouseLocation', 'actor']),
        ]);
    }
}
