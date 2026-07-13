<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\InventoryLoss;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LossReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->can('reports.view') || $user->can('losses.view'), 403);

        $base = InventoryLoss::query()
            ->with(['workLocation', 'product'])
            ->whereIn('work_location_id', $user->permittedWorkLocationIds())
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('reported_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('reported_at', '<=', $request->query('date_to')));

        return view('reports.losses', [
            'losses' => (clone $base)->latest('reported_at')->paginate(15)->withQueryString(),
            'byReason' => (clone $base)->select('loss_type', DB::raw('SUM(loss_value) as total_value'), DB::raw('SUM(quantity) as total_qty'))->groupBy('loss_type')->orderByDesc('total_value')->get(),
            'filters' => $request->only(['date_from', 'date_to']),
        ]);
    }
}
