<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use App\Models\SystemSetting;
use App\Services\Reports\ReportMetricService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function show(ReportFilterRequest $request, ReportMetricService $reports, string $type): View
    {
        abort_unless($this->canViewSpecialReport($request->user(), $type), 403);

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
            'customers' => DB::table('customers')
                ->where('is_active', true)
                ->orderBy('business_name')
                ->limit(300)
                ->get(['id', 'code', 'business_name']),
        ]);
    }

    public function printDaily(ReportFilterRequest $request, ReportMetricService $reports): View
    {
        abort_unless($this->canViewSpecialReport($request->user(), 'daily'), 403);

        $input = $request->validated();
        $today = now('Asia/Jakarta')->toDateString();
        $input['start_date'] ??= $today;
        $input['end_date'] ??= $today;

        $filters = $reports->filters($request->user(), $input);
        $reportData = $reports->report('daily', $request->user(), $filters);
        $settings = SystemSetting::getPrinterSettings();
        $companyName = SystemSetting::getCompanyName();

        if ($request->query('format') === 'dotmatrix') {
            return view('reports.print-daily-dotmatrix', [
                'report' => $reportData,
                'filters' => $filters,
                'settings' => $settings,
                'companyName' => $companyName,
            ]);
        }

        return view('reports.print-daily-thermal', [
            'report' => $reportData,
            'filters' => $filters,
            'settings' => $settings,
            'companyName' => $companyName,
        ]);
    }

    private function canViewSpecialReport(mixed $user, string $type): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return match ($type) {
            'daily', 'b2b' => $user->hasAnyRole(['owner_viewer', 'owner_approver']),
            'warehouse', 'suppliers' => $user->hasAnyRole(['kepala_gudang', 'owner_viewer', 'owner_approver']),
            'retail' => $user->hasAnyRole(['kepala_toko', 'owner_viewer', 'owner_approver']),
            'pricing', 'attendance', 'receivables' => $user->hasAnyRole(['kepala_gudang', 'kepala_toko', 'owner_viewer', 'owner_approver']),
            'audit_notifications' => false,
            default => false,
        };
    }
}
