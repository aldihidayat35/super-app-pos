<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\PreviewInitialImportRequest;
use App\Http\Requests\System\RunMaintenanceActionRequest;
use App\Services\Control\AuditLogService;
use App\Services\System\ApplicationLogService;
use App\Services\System\BackupCatalogService;
use App\Services\System\HealthCheckService;
use App\Services\System\InitialDataImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsController extends Controller
{
    public function backups(BackupCatalogService $backups): View
    {
        return view('system.operations.backups', [
            'backups' => $backups->list(),
            'disk' => $backups->disk(),
            'path' => $backups->path(),
            'retentionDays' => config('security.backup.retention_days', 14),
            'backupEnabled' => (bool) config('security.backup.enabled'),
        ]);
    }

    public function runBackup(Request $request, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);

        $exitCode = Artisan::call('system:encrypted-backup', [
            '--connection' => 'mysql',
            '--dry-run' => ! config('security.backup.enabled'),
        ]);

        $audit->record('ops.backup.run', 'operations', $request->user(), null, [], [
            'exit_code' => $exitCode,
            'dry_run' => ! config('security.backup.enabled'),
        ], $exitCode === 0 ? 'info' : 'warning');

        return back()->with('notification', [
            'type' => $exitCode === 0 ? 'success' : 'warning',
            'message' => $exitCode === 0 ? 'Backup command berhasil dijalankan.' : 'Backup command gagal. Periksa log aplikasi.',
        ]);
    }

    public function downloadBackup(Request $request, BackupCatalogService $backups): StreamedResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);

        $file = $backups->decode((string) $request->query('file'));
        abort_unless($file && Storage::disk($backups->disk())->exists($file), 404);

        return Storage::disk($backups->disk())->download($file);
    }

    public function logs(Request $request, ApplicationLogService $logs): View
    {
        $level = $request->string('level')->toString();
        $lines = collect($logs->recentLines())
            ->when($level !== '', fn ($collection) => $collection->where('level', $level))
            ->values()
            ->all();

        return view('system.operations.logs', [
            'lines' => $lines,
            'failedJobs' => $logs->failedJobs(),
            'level' => $level,
            'schedulerHeartbeat' => cache('system.scheduler.last_run'),
        ]);
    }

    public function retryFailedJob(Request $request, int $failedJob, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);
        abort_unless(Schema::hasTable('failed_jobs') && DB::table('failed_jobs')->where('id', $failedJob)->exists(), 404);

        Artisan::call('queue:retry', ['id' => [$failedJob]]);
        $audit->record('ops.failed_job.retry', 'operations', $request->user(), null, [], ['failed_job_id' => $failedJob]);

        return back()->with('notification', ['type' => 'success', 'message' => 'Failed job dikirim ulang ke queue.']);
    }

    public function resolveLog(Request $request, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);
        $audit->record('ops.log.resolve_marker', 'operations', $request->user(), null, [], ['note' => $data['note']]);

        return back()->with('notification', ['type' => 'success', 'message' => 'Marker resolve dicatat di audit log.']);
    }

    public function imports(InitialDataImportService $imports): View
    {
        return view('system.operations.imports', [
            'templates' => $imports->templates(),
            'preview' => session('import_preview'),
        ]);
    }

    public function downloadImportTemplate(string $type, InitialDataImportService $imports): Response
    {
        return response($imports->templateCsv($type), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template-'.$type.'.csv"',
        ]);
    }

    public function previewImport(PreviewInitialImportRequest $request, InitialDataImportService $imports, AuditLogService $audit): RedirectResponse
    {
        $preview = $imports->preview(
            (string) $request->validated('type'),
            $request->file('file'),
            $request->boolean('dry_run', true)
        );

        $audit->record('ops.initial_import.preview', 'operations', $request->user(), null, [], [
            'type' => $preview['type'],
            'rows' => $preview['totals']['rows'],
            'errors' => $preview['totals']['invalid_rows'],
            'dry_run' => true,
        ], $preview['errors'] === [] ? 'info' : 'warning');

        return back()->with('import_preview', $preview)->with('notification', [
            'type' => $preview['errors'] === [] ? 'success' : 'warning',
            'message' => $preview['errors'] === [] ? 'Preview valid. Commit production tetap harus dilakukan pada maintenance window.' : 'Preview menemukan error validasi.',
        ]);
    }

    public function maintenance(HealthCheckService $healthCheck): View
    {
        return view('system.operations.maintenance', [
            'isDown' => app()->isDownForMaintenance(),
            'checks' => $healthCheck->run(),
            'version' => config('app.version', 'local'),
            'migrationStatus' => $this->migrationStatus(),
            'checklist' => $this->goLiveChecklist(),
        ]);
    }

    public function runMaintenance(RunMaintenanceActionRequest $request, AuditLogService $audit): RedirectResponse
    {
        $action = (string) $request->validated('action');

        match ($action) {
            'up' => Artisan::call('up'),
            'down' => Artisan::call('down', ['--render' => 'errors::503', '--secret' => str()->random(32)]),
            'cache_clear' => Artisan::call('optimize:clear'),
            'optimize' => Artisan::call('optimize'),
            'queue_restart' => Artisan::call('queue:restart'),
            default => abort(422, 'Aksi maintenance tidak valid.'),
        };

        $audit->record('ops.maintenance.action', 'operations', $request->user(), null, [], [
            'action' => $action,
            'message' => $request->validated('message'),
        ], 'warning');

        return back()->with('notification', ['type' => 'success', 'message' => 'Aksi maintenance berhasil dijalankan.']);
    }

    /** @return array{ran: int, pending: int} */
    private function migrationStatus(): array
    {
        $ran = collect(DB::select('select migration from migrations'))->count();
        $files = count(glob(database_path('migrations/*.php')) ?: []);

        return ['ran' => $ran, 'pending' => max(0, $files - $ran)];
    }

    /** @return list<array{label: string, status: string}> */
    private function goLiveChecklist(): array
    {
        return [
            ['label' => 'Freeze input manual dan backup terakhir disetujui owner', 'status' => 'manual'],
            ['label' => 'Import dry-run valid dan opening stock reconcile', 'status' => 'manual'],
            ['label' => 'Smoke test owner, gudang, toko, kasir, B2B lulus', 'status' => 'manual'],
            ['label' => 'Printer receipt, barcode scanner, dan koneksi internet diuji', 'status' => 'manual'],
            ['label' => 'Queue worker, scheduler, backup, dan monitoring aktif', 'status' => 'manual'],
            ['label' => 'Support channel 7 hari pertama tersedia', 'status' => 'manual'],
        ];
    }
}
