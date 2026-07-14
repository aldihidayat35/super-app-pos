<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use App\Services\Reports\ReportMetricService;
use Illuminate\Contracts\View\View;

class ReportController extends Controller
{
    public function show(ReportFilterRequest $request, ReportMetricService $reports, string $type): View
    {
        abort_unless($request->user()->can('reports.view') || $this->canViewSpecialReport($request->user(), $type), 403);

        $filters = $reports->filters($request->user(), $request->validated());

        return view('reports.generic', [
            'report' => $reports->report($type, $request->user(), $filters),
            'labels' => $reports->reportLabels(),
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
