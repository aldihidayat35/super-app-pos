<?php

namespace App\Jobs;

use App\Enums\ReportExportStatus;
use App\Exports\ArrayReportExport;
use App\Models\ReportExport;
use App\Services\Control\AuditLogService;
use App\Services\Reports\ReportMetricService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class GenerateReportExportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $reportExportId) {}

    public function handle(ReportMetricService $reports, AuditLogService $audit): void
    {
        $export = ReportExport::query()->with('requester')->findOrFail($this->reportExportId);

        try {
            $export->forceFill([
                'status' => ReportExportStatus::PROCESSING,
                'started_at' => now(),
                'progress' => 10,
            ])->save();

            $user = $export->requester;
            if ($user === null) {
                throw new \RuntimeException('Requester export tidak ditemukan.');
            }

            $filters = $reports->filters($user, $export->filters ?? []);
            $payload = $reports->report($export->report_type, $user, $filters);
            $rows = $payload['rows'];
            $headings = $this->headings($rows);
            $directory = 'report-exports/'.now()->format('Y/m');
            $filename = $export->report_type.'-'.$export->id.'.'.$this->extension($export->format);
            $path = $directory.'/'.$filename;

            if ($export->format === 'pdf') {
                $pdf = Pdf::loadView('reports.exports.pdf', [
                    'export' => $export,
                    'payload' => $payload,
                    'headings' => $headings,
                ])->setPaper('a4', 'landscape');
                Storage::disk($export->disk)->put($path, $pdf->output());
            } elseif ($export->format === 'xlsx') {
                Excel::store(new ArrayReportExport($rows, $headings), $path, $export->disk);
            } else {
                Storage::disk($export->disk)->put($path, $this->csv($rows, $headings));
            }

            $export->forceFill([
                'status' => ReportExportStatus::COMPLETED,
                'progress' => 100,
                'row_count' => count($rows),
                'file_path' => $path,
                'finished_at' => now(),
            ])->save();

            $audit->record('reports.export.completed', 'reports', $user, $export, [], ['report_type' => $export->report_type, 'format' => $export->format, 'row_count' => count($rows)], correlationId: $export->correlation_id);
        } catch (Throwable $exception) {
            $export->forceFill([
                'status' => ReportExportStatus::FAILED,
                'progress' => 100,
                'finished_at' => now(),
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function headings(array $rows): array
    {
        if ($rows === []) {
            return ['empty'];
        }

        return array_keys($rows[0]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $headings
     */
    private function csv(array $rows, array $headings): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headings);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $heading): mixed => $row[$heading] ?? null, $headings));
        }
        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return (string) $contents;
    }

    private function extension(string $format): string
    {
        return $format === 'pdf' ? 'pdf' : ($format === 'xlsx' ? 'xlsx' : 'csv');
    }
}
