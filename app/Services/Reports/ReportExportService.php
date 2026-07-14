<?php

namespace App\Services\Reports;

use App\Enums\ReportExportStatus;
use App\Jobs\GenerateReportExportJob;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Support\Str;

class ReportExportService
{
    public function __construct(private readonly ReportMetricService $reports) {}

    /** @param array<string, mixed> $filters */
    public function request(string $reportType, string $format, array $filters, User $user): ReportExport
    {
        $export = ReportExport::query()->create([
            'report_type' => $reportType,
            'format' => $format,
            'status' => ReportExportStatus::QUEUED,
            'requested_by' => $user->id,
            'filters' => [
                'start_date' => $filters['start_date'] ?? null,
                'end_date' => $filters['end_date'] ?? null,
                'work_location_id' => $filters['work_location_id'] ?? null,
                'channel' => $filters['channel'] ?? null,
                'status' => $filters['status'] ?? null,
                'product_id' => $filters['product_id'] ?? null,
                'category_id' => $filters['category_id'] ?? null,
                'supplier_id' => $filters['supplier_id'] ?? null,
                'customer_id' => $filters['customer_id'] ?? null,
                'user_id' => $filters['user_id'] ?? null,
            ],
            'correlation_id' => (string) Str::uuid(),
            'expires_at' => now()->addDays(7),
        ]);

        GenerateReportExportJob::dispatch($export->id);

        return $export;
    }

    /** @return list<string> */
    public function allowedTypes(): array
    {
        return $this->reports->reportTypes();
    }
}
