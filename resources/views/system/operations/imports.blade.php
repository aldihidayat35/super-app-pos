@extends('layouts.metronic.app')

@section('title', 'Import Data Awal')
@section('page_title', 'Import Data Awal')

@section('content')
    <x-metronic.page-title title="Import Data Awal" description="OPS-03 — Wizard template, mapping, preview, validasi dry-run, rekonsiliasi, rollback plan, dan sign-off owner." />

    <x-metronic.card title="Template Import">
        <div class="row g-3">
            @foreach ($templates as $type => $template)
                <div class="col-md-4">
                    <div class="border rounded p-4 h-100">
                        <div class="fw-bold mb-2">{{ $template['label'] }}</div>
                        <div class="text-muted fs-8 mb-3">{{ implode(', ', $template['columns']) }}</div>
                        <a href="{{ route('admin.system.imports.templates.download', $type) }}" class="btn btn-sm btn-light-primary">Download CSV</a>
                    </div>
                </div>
            @endforeach
        </div>
    </x-metronic.card>

    <x-metronic.card title="Preview dan Dry Run" class="mt-5">
        <form method="POST" action="{{ route('admin.system.imports.preview') }}" enctype="multipart/form-data" class="row g-3">
            @csrf
            <div class="col-md-3">
                <label class="form-label">Jenis Data</label>
                <select name="type" class="form-select" required>
                    @foreach ($templates as $type => $template)
                        <option value="{{ $type }}">{{ $template['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">File CSV</label>
                <input type="file" name="file" class="form-control" accept=".csv,.txt" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <input type="hidden" name="dry_run" value="1">
                <button class="btn btn-primary w-100">Preview</button>
            </div>
        </form>
    </x-metronic.card>

    @if ($preview)
        <x-metronic.card title="Hasil Preview {{ $preview['label'] }}" class="mt-5">
            <div class="row g-5 mb-5">
                <div class="col-md-3"><div class="text-muted">Rows</div><div class="fs-3 fw-bold">{{ $preview['totals']['rows'] }}</div></div>
                <div class="col-md-3"><div class="text-muted">Valid Rows</div><div class="fs-3 fw-bold">{{ $preview['totals']['valid_rows'] }}</div></div>
                <div class="col-md-3"><div class="text-muted">Error</div><div class="fs-3 fw-bold text-danger">{{ $preview['totals']['invalid_rows'] }}</div></div>
                <div class="col-md-3"><div class="text-muted">Mode</div><div class="fs-3 fw-bold">Dry-run</div></div>
            </div>
            @if ($preview['errors'])
                <div class="alert alert-warning"><div class="fw-bold mb-2">Error Validasi</div><ul class="mb-0">@foreach ($preview['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @else
                <div class="alert alert-success">Preview valid. Commit production harus dilakukan pada maintenance window setelah backup dan sign-off owner.</div>
            @endif
            <div class="table-responsive">
                <table class="table table-row-dashed">
                    <thead><tr>@foreach ($preview['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead>
                    <tbody>@foreach ($preview['rows'] as $row)<tr>@foreach ($preview['headers'] as $header)<td>{{ $row[$header] ?? '' }}</td>@endforeach</tr>@endforeach</tbody>
                </table>
            </div>
        </x-metronic.card>
    @endif

    <x-metronic.card title="Aturan Opening Stock" class="mt-5">
        <p class="mb-0">Opening stock tidak boleh insert saldo langsung. Setelah preview valid, lakukan opening stock opname dan posting melalui InventoryService agar menghasilkan dokumen khusus dan stock_mutations append-only.</p>
    </x-metronic.card>
@endsection
