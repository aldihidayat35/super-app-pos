<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\B2bOrder;
use App\Services\Sales\SalesPerformanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, SalesPerformanceService $performance): View
    {
        abort_unless($request->user()->can('sales.dashboard.view'), 403);

        $month = now()->month;
        $year = now()->year;

        return view('sales.dashboard', [
            'metrics' => $performance->forUser($request->user(), $month, $year),
            'period' => now()->translatedFormat('F Y'),
            'recentOrders' => B2bOrder::query()
                ->where('sales_user_id', $request->user()->id)
                ->with(['customer', 'latestShipment'])
                ->latest('submitted_at')
                ->limit(8)
                ->get(),
        ]);
    }
}
