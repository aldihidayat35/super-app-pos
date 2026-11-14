<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreProfileReportExportRequest;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\ReportProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProfileReportExportController extends Controller
{
    public function store(
        StoreProfileReportExportRequest $request,
        ReportProfileService $profiles,
        ReportExportService $exports,
        string $profile,
        string $report,
    ): StreamedResponse|RedirectResponse {
        $actor = $request->user();
        $profiles->authorize($actor, $profile);
        $definition = $profiles->profile($profile);
        abort_unless($actor->can($definition['export_permission']), 403);
        $profiles->report($profile, $report);

        try {
            $export = $exports->requestSync(
                "profile.{$profile}.{$report}",
                $request->validated('format'),
                $request->safe()->except('format'),
                $actor,
            );

            if ($export->file_path !== null && Storage::disk($export->disk)->exists($export->file_path)) {
                $ext = $export->format;
                $dateSuffix = now('Asia/Jakarta')->format('Y-m-d_His');
                $filename = "laporan_{$profile}_{$report}_{$dateSuffix}.{$ext}";

                return Storage::disk($export->disk)->download($export->file_path, $filename);
            }

            return back()->with('notification', [
                'type' => 'error',
                'message' => 'File laporan tidak dapat diunduh: '.($export->error_message ?? 'File tidak ditemukan di storage.'),
            ]);
        } catch (Throwable $e) {
            return back()->with('notification', [
                'type' => 'error',
                'message' => 'Terjadi kesalahan saat mengekspor laporan: '.$e->getMessage(),
            ]);
        }
    }
}
