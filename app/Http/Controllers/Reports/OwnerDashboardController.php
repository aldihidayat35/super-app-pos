<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use App\Services\Reports\ReportMetricService;
use Illuminate\Contracts\View\View;

class OwnerDashboardController extends Controller
{
    public function __invoke(ReportFilterRequest $request, ReportMetricService $reports): View
    {
        abort_unless($request->user()->hasAnyRole(['owner_viewer', 'owner_approver', 'super_admin']) || $request->user()->can('reports.view'), 403);

        $filters = $reports->filters($request->user(), $request->validated());

        return view('dashboards.owner', [
            'filters' => $filters,
            'dashboard' => $reports->ownerDashboard($request->user(), $filters),
            'definitions' => $reports->definitions('daily'),
        ]);
    }
}
