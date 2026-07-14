@extends('layouts.metronic.app')

@php
    $label = $labels[$report['type']] ?? 'Laporan';
    $summary = $report['summary'];
    $rows = $report['rows'];
    $filters = $report['filters'];
@endphp

@section('title', $label)
@section('page_title', $label)

@section('content')
    <x-metronic.page-title :title="$label" description="Laporan agregat berbasis query object, filter konsisten, dan siap export queue.">
        @can('reports.export')
            <a href="{{ route('reports.exports.index', ['report_type' => $report['type'], 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date']]) }}" class="btn btn-light-primary">Export</a>
        @endcan
    </x-metronic.page-title>

    @include('reports.partials.filter', ['filters' => $filters])

    <div class="row g-5 mb-5">
        <div class="col-lg-8">
            <x-metronic.card title="Ringkasan">
                <div class="row g-4">
                    @foreach($summary as $key => $value)
                        <div class="col-md-4">
                            <div class="border rounded p-4 h-100">
                                <div class="text-muted text-uppercase fs-8">{{ str_replace('_', ' ', $key) }}</div>
                                <div class="fw-bold fs-4">{{ is_array($value) ? count($value).' item' : $value }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-metronic.card>
        </div>
        <div class="col-lg-4">
            @include('reports.partials.definitions', ['definitions' => $report['definitions']])
        </div>
    </div>

    <x-metronic.card title="Detail Laporan">
        <div class="text-muted mb-4">Last updated: {{ $report['last_updated_at']->format('d/m/Y H:i:s') }}</div>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead>
                    <tr class="text-muted fw-bold text-uppercase fs-7">
                        @foreach(array_keys($rows[0] ?? ['empty' => 'Tidak ada data']) as $heading)
                            <th>{{ str_replace('_', ' ', $heading) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            @foreach($row as $value)
                                <td>{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-metronic.empty-state title="Tidak ada data" description="Ubah filter atau tunggu transaksi tersedia." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-metronic.card>
@endsection
