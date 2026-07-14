<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Services\Reports\ReportMetricService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class WarehouseDashboardController extends Controller
{
    public function __invoke(Request $request, ReportMetricService $reports): View
    {
        $this->authorize('viewAny', Stock::class);

        $filters = $reports->filters($request->user(), $request->query());

        return view('warehouse.dashboard', [
            'filters' => $filters,
            'dashboard' => $reports->warehouseDashboard($request->user(), $filters),
            'definitions' => $reports->definitions('warehouse'),
        ]);
    }
}
