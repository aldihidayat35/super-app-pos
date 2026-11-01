<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ProfileReportRequest;
use App\Services\Reports\ProfileReportDataService;
use App\Services\Reports\ReportProfileService;
use Illuminate\Contracts\View\View;

class ProfileReportController extends Controller
{
    public function show(
        ProfileReportRequest $request,
        ReportProfileService $profiles,
        ProfileReportDataService $data,
        string $report = 'overview',
        string $profile = '',
    ): View {
        $actor = $request->user();
        $result = $data->build($actor, $profile, $report, $request->validated());
        $profileDefinition = $result['profile'];

        return view("reports.profiles.{$profile}.{$report}", [
            'profileKey' => $profile,
            'reportKey' => $report,
            'profile' => $profileDefinition,
            'currentReport' => $result['report_definition'],
            'report' => $result['payload'],
            'filters' => $result['filters'],
            'workLocations' => $result['locations'],
            'selectedLocation' => $result['selected_location'],
            'canExport' => $actor->can($profileDefinition['export_permission']),
            'canViewSensitive' => $actor->can('margins.view_sensitive'),
        ]);
    }
}
