@php
    $overviewParams = array_filter([
        'report' => 'overview',
        'work_location_id' => $selectedLocation?->id,
        'start_date' => $filters['start_date'] ?? null,
        'end_date' => $filters['end_date'] ?? null,
        'range' => $filters['range'] ?? 'monthly',
    ]);
@endphp
<div class="mb-5">
    <a href="{{ route("reports.profiles.{$profileKey}.show", $overviewParams) }}" class="btn btn-sm btn-light-primary">
        <i class="ki-outline ki-arrow-left fs-5"></i>Kembali ke Ringkasan
    </a>
</div>
