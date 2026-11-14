<?php

namespace App\Services\Reports;

use App\Models\User;

class ProfileReportDataService
{
    public function __construct(
        private readonly ReportProfileService $profiles,
        private readonly ReportMetricService $metrics,
        private readonly UserReportService $users,
        private readonly RetailOperationalReportService $retailOperational,
        private readonly WarehouseOperationalReportService $warehouseOperational,
        private readonly OwnerOperationalReportService $ownerOperational,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function build(User $actor, string $profile, string $report, array $input): array
    {
        $this->profiles->authorize($actor, $profile);
        $reportDefinition = $this->profiles->report($profile, $report);
        $input['range'] ??= 'monthly';
        $selectedLocation = $this->profiles->selectedLocation(
            $actor,
            $profile,
            isset($input['work_location_id']) && is_numeric($input['work_location_id']) ? (int) $input['work_location_id'] : null,
        );

        if ($selectedLocation !== null) {
            $input['work_location_id'] = $selectedLocation->id;
        } else {
            unset($input['work_location_id']);
        }

        $input['report_scope'] = match ($profile) {
            'warehouse' => 'warehouse',
            'retail' => 'retail',
            default => 'all',
        };
        $filters = $this->metrics->filters($actor, $input);
        $payload = $reportDefinition['metric'] === 'users'
            ? $this->users->report($actor, $filters)
            : $this->metrics->report($reportDefinition['metric'], $actor, $filters);

        if ($profile === 'retail' && $report === 'overview') {
            $payload = $this->retailOperational->build($filters, $payload, $actor->can('margins.view_sensitive'));
        }

        if ($profile === 'warehouse' && $report === 'overview') {
            $payload = $this->warehouseOperational->build($filters, $payload, $actor->can('margins.view_sensitive'));
        }

        if ($profile === 'owner' && $report === 'overview') {
            $payload = $this->ownerOperational->build($filters, $payload, $actor->can('margins.view_sensitive'));
        }

        if ($profile === 'owner' && $report === 'income') {
            $payload = $this->ownerOperational->buildIncomeStatement($filters, $payload, $actor->can('margins.view_sensitive'));
        }

        if ($profile === 'owner' && $report === 'tax') {
            $payload = $this->ownerOperational->buildTaxReport($filters, $payload, $actor->can('margins.view_sensitive'));
        }

        if ($profile === 'owner' && $report === 'locations') {
            $payload = $this->ownerOperational->buildBranchComparisonReport($filters, $payload, $actor->can('margins.view_sensitive'));
        }

        if ($profile === 'owner' && $report === 'receivables') {
            $payload = $this->ownerOperational->buildAgingReceivablesReport($filters, $payload, $actor->can('margins.view_sensitive'));
        }

        return [
            'profile' => $this->profiles->profile($profile),
            'report_definition' => $reportDefinition,
            'payload' => $payload,
            'filters' => $filters,
            'locations' => $this->profiles->locations($actor, $profile),
            'selected_location' => $selectedLocation,
        ];
    }
}
