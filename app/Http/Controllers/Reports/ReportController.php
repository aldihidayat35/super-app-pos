<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use App\Services\Reports\ReportMetricService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function show(ReportFilterRequest $request, ReportMetricService $reports, string $type): View
    {
        abort_unless($request->user()->can('reports.view') || $this->canViewSpecialReport($request->user(), $type), 403);

        $input = $request->validated();
        if ($type === 'daily') {
            $today = now('Asia/Jakarta')->toDateString();
            $input['start_date'] ??= $today;
            $input['end_date'] ??= $today;
        }

        $filters = $reports->filters($request->user(), $input);

        $view = $type === 'daily' ? 'reports.daily' : 'reports.generic';

        return view($view, [
            'report' => $reports->report($type, $request->user(), $filters),
            'labels' => $reports->reportLabels(),
            'workLocations' => DB::table('work_locations')
                ->whereIn('id', $request->user()->permittedWorkLocationIds())
                ->where('is_active', true)
                ->orderBy('type')->orderBy('name')
                ->get(['id', 'code', 'name', 'type']),
        ]);
    }

    private function canViewSpecialReport(mixed $user, string $type): bool
    {
        return match ($type) {
            'warehouse' => $user->can('stock.view'),
            'retail' => $user->can('cash_shifts.view') || $user->can('pos.view'),
            'pricing' => $user->can('prices.view'),
            'suppliers' => $user->can('suppliers.view'),
            'attendance' => $user->can('attendance.view'),
            'receivables' => $user->can('receivables.view'),
            'audit_notifications' => $user->can('audit.view'),
            default => false,
        };
    }
}
