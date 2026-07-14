@extends('layouts.metronic.app')

@section('title', 'Pusat Export')
@section('page_title', 'Pusat Export')

@section('content')
    <x-metronic.page-title title="Pusat Export" description="RPT-10 export Excel/PDF diproses via queue, memiliki status, histori, dan masa kedaluwarsa." />

    <x-metronic.card title="Buat Export Baru">
        <form method="POST" action="{{ route('reports.exports.store') }}" class="row g-3">
            @csrf
            <div class="col-md-3">
                <label class="form-label">Jenis Laporan</label>
                <select name="report_type" class="form-select">
                    @foreach($labels as $type => $label)
                        <option value="{{ $type }}" @selected(request('report_type') === $type)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Format</label>
                <select name="format" class="form-select">
                    <option value="xlsx">Excel XLSX</option>
                    <option value="pdf">PDF</option>
                    <option value="csv">CSV</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Mulai</label>
                <input type="date" name="start_date" value="{{ request('start_date', now()->startOfMonth()->toDateString()) }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Akhir</label>
                <input type="date" name="end_date" value="{{ request('end_date', now()->toDateString()) }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Lokasi</label>
                <input type="number" name="work_location_id" value="{{ request('work_location_id') }}" class="form-control" placeholder="Opsional">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button class="btn btn-primary w-100">Queue</button>
            </div>
        </form>
    </x-metronic.card>

    <x-metronic.card title="Histori Export" class="mt-5">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>ID</th><th>Laporan</th><th>Format</th><th>Status</th><th>Progress</th><th>Rows</th><th>Kedaluwarsa</th><th class="text-end">Download</th></tr></thead>
                <tbody>
                @forelse($exports as $export)
                    <tr>
                        <td>#{{ $export->id }}</td>
                        <td>{{ $labels[$export->report_type] ?? $export->report_type }}<div class="text-muted">{{ $export->requester?->email }}</div></td>
                        <td>{{ strtoupper($export->format) }}</td>
                        <td><x-metronic.status-badge :status="$export->status->value" :label="$export->status->label()" /></td>
                        <td>{{ $export->progress }}%</td>
                        <td>{{ $export->row_count }}</td>
                        <td>{{ $export->expires_at?->format('d/m/Y H:i') }}</td>
                        <td class="text-end">
                            @if($export->status->value === 'completed' && $export->file_path)
                                <a href="{{ route('reports.exports.download', $export) }}" class="btn btn-sm btn-light-primary">Download</a>
                            @else
                                <span class="text-muted">Belum tersedia</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-metronic.empty-state title="Belum ada export" description="Buat export baru untuk melihat progress di sini." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $exports->links() }}
    </x-metronic.card>
@endsection
