<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreProfileReportExportRequest;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\ReportProfileService;
use Illuminate\Http\RedirectResponse;

class ProfileReportExportController extends Controller
{
    public function store(
        StoreProfileReportExportRequest $request,
        ReportProfileService $profiles,
        ReportExportService $exports,
        string $profile,
        string $report,
    ): RedirectResponse {
        $actor = $request->user();
        $profiles->authorize($actor, $profile);
        $definition = $profiles->profile($profile);
        abort_unless($actor->can($definition['export_permission']), 403);
        $profiles->report($profile, $report);

        $export = $exports->request(
            "profile.{$profile}.{$report}",
            $request->validated('format'),
            $request->safe()->except('format'),
            $actor,
        );

        return back()->with('notification', [
            'type' => 'success',
            'message' => "Export #{$export->id} masuk antrean dan memakai scope laporan aktif.",
        ]);
    }
}
