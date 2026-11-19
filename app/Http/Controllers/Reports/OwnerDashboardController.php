<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use App\Services\Reports\ReportMetricService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class OwnerDashboardController extends Controller
{
    public function __invoke(ReportFilterRequest $request, ReportMetricService $reports): View|RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'owner_viewer', 'owner_approver', 'super_admin']) || $request->user()->can('reports.view'), 403);

        if ($request->user()->hasAnyRole(['owner_viewer', 'owner_approver'])) {
            return redirect()->route('reports.profiles.owner.show', ['report' => 'overview']);
        }

        $filters = $reports->filters($request->user(), $request->validated());

        return view('dashboards.owner', [
            'filters' => $filters,
            'dashboard' => $reports->ownerDashboard($request->user(), $filters),
            'definitions' => $reports->definitions('daily'),
            'workLocations' => DB::table('work_locations')
                ->whereIn('id', $request->user()->permittedWorkLocationIds())
                ->where('is_active', true)
                ->orderBy('type')
                ->orderBy('name')
                ->get(['id', 'name', 'type']),
        ]);
    }
}
