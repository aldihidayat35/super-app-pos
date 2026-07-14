<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use App\Services\Reports\ReportMetricService;
use Illuminate\Contracts\View\View;

class RetailDashboardController extends Controller
{
    public function __invoke(ReportFilterRequest $request, ReportMetricService $reports): View
    {
        abort_unless($request->user()->can('cash_shifts.view') || $request->user()->can('reports.view'), 403);

        $filters = $reports->filters($request->user(), $request->validated());

        return view('dashboards.retail', [
            'filters' => $filters,
            'dashboard' => $reports->retailDashboard($request->user(), $filters),
            'definitions' => $reports->definitions('retail'),
        ]);
    }
}
