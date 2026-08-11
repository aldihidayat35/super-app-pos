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
                        <a href="{{ route('admin.system.imports.templates.download', $type) }}" class="btn btn-sm btn-light-primary">Download XLSX</a>
                    </div>
                </div>
            @endforeach
        </div>
    </x-metronic.card>

    <x-metronic.card title="Preview dan Commit" class="mt-5">
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
                <label class="form-label">File Excel XLSX</label>
                <input type="file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                <div class="alert alert-success">Preview valid. Periksa seluruh data, lalu lakukan commit untuk menyimpan stok awal dan HPP langsung ke database.</div>
            @endif
            <div class="table-responsive">
                <table class="table table-row-dashed">
                    <thead><tr>@foreach ($preview['headers'] as $header)<th>{{ $preview['header_labels'][$header] ?? $header }}</th>@endforeach</tr></thead>
                    <tbody>@foreach ($preview['rows'] as $row)<tr>@foreach ($preview['headers'] as $header)<td>{{ $row[$header] ?? '' }}</td>@endforeach</tr>@endforeach</tbody>
                </table>
            </div>

            @if ($preview['errors'] === [] && $preview['type'] === 'opening_stocks')
                <div class="separator my-6"></div>
                <form method="POST" action="{{ route('admin.system.imports.commit') }}" onsubmit="return confirm('Commit akan menetapkan saldo stok dan HPP sesuai hasil preview. Lanjutkan?');">
                    @csrf
                    <input type="hidden" name="type" value="opening_stocks">
                    <div class="row align-items-end g-3">
                        <div class="col-md-7">
                            <label class="form-label fw-bold">Konfirmasi Commit</label>
                            <input type="text" name="confirmation" class="form-control" placeholder="Ketik: COMMIT STOK AWAL" required autocomplete="off">
                            <div class="form-text">Saldo akan ditetapkan sesuai kolom Jumlah Stok Awal. HPP produk dan nilai persediaan ikut diperbarui.</div>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="ki-duotone ki-check-circle fs-2"><span class="path1"></span><span class="path2"></span></i>
                                Commit ke Database
                            </button>
                        </div>
                    </div>
                </form>
            @elseif ($preview['errors'] === [] && $preview['type'] !== 'opening_stocks')
                <div class="alert alert-info mt-5 mb-0">Commit langsung saat ini tersedia untuk jenis data Stok Awal.</div>
            @endif
        </x-metronic.card>
    @endif

    <x-metronic.card title="Aturan Opening Stock" class="mt-5">
        <p class="mb-0">Commit stok awal menggunakan InventoryService, menetapkan saldo sesuai file XLSX, memperbarui HPP, dan menghasilkan stock_mutations append-only ketika saldo berubah. Baris yang saldonya sudah sama tidak membuat mutasi duplikat.</p>
    </x-metronic.card>
@endsection
