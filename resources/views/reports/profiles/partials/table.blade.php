@php
    $rows = collect($report['rows'] ?? [])->map(fn($row) => (array)$row);
    $columns = $rows->first() ? array_slice(array_keys($rows->first()), 0, 8) : [];
@endphp
<div class="table-responsive">
    <table class="table table-row-dashed align-middle mb-0">
        <thead><tr class="text-muted text-uppercase fs-8 fw-bold">@foreach($columns as $column)<th>{{ str($column)->replace('_', ' ')->title() }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse($rows as $row)
                <tr>@foreach($columns as $column)<td class="text-gray-700">{{ is_scalar($row[$column] ?? null) ? $row[$column] : json_encode($row[$column] ?? null) }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="8"><x-metronic.empty-state title="Belum ada data" description="Ubah periode atau lokasi untuk melihat data lainnya." /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
