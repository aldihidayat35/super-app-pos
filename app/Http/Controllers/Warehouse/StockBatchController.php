<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\StockBatch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StockBatchController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockBatch::class);

        $batches = StockBatch::query()
            ->with(['product', 'supplier', 'stock.workLocation', 'stock.warehouseLocation'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('q'), fn ($query) => $query->where('batch_no', 'like', '%'.$request->query('q').'%'))
            ->where(function ($query) use ($request): void {
                $ids = $request->user()?->permittedWorkLocationIds() ?? [];
                $query->whereNull('stock_id')->orWhereHas('stock', fn ($stock) => $stock->whereIn('work_location_id', $ids));
            })
            ->orderByRaw('expires_at IS NULL, expires_at')
            ->orderBy('received_at')
            ->paginate(20)
            ->withQueryString();

        return view('warehouse.batches.index', [
            'batches' => $batches,
            'status' => $request->query('status'),
            'search' => $request->query('q'),
        ]);
    }
}
