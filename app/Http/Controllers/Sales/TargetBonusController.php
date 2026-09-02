<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\SalesTarget;
use App\Services\Sales\SalesPerformanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TargetBonusController extends Controller
{
    public function index(Request $request, SalesPerformanceService $performance): View
    {
        abort_unless($request->user()->can('sales.targets.view_own'), 403);

        $targets = SalesTarget::query()
            ->where('sales_user_id', $request->user()->id)
            ->latest('year')
            ->latest('month')
            ->paginate(18);

        $rows = $targets->getCollection()->map(fn (SalesTarget $target): array => [
            'target' => $target,
            'metrics' => $performance->forUser($request->user(), $target->month, $target->year),
        ]);

        return view('sales.targets.index', compact('targets', 'rows'));
    }
}
