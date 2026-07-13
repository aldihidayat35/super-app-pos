@extends('layouts.metronic.app')
@section('title', 'Import Produk')
@section('page_title', 'Import dan Export Produk')
@section('toolbar_actions')
    <a href="{{ route('admin.products.import.template') }}" class="btn btn-light">Download Template</a>
    <a href="{{ route('admin.products.export') }}" class="btn btn-light-primary">Export Data Produk</a>
@endsection
@section('content')
<x-metronic.card title="Upload File">
    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.products.import.preview') }}" class="row g-3 align-items-end">@csrf
        <div class="col-md-8"><x-metronic.form-group name="file" label="File XLSX/CSV" required><input type="file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".xlsx,.xls,.csv,.txt"></x-metronic.form-group></div>
        <div class="col-md-4"><button class="btn btn-primary w-100">Preview Validasi</button></div>
    </form>
</x-metronic.card>
@if($result)
    <div class="alert alert-success mt-6">Import selesai. Dibuat: {{ $result['created'] ?? 0 }}, diperbarui: {{ $result['updated'] ?? 0 }}.</div>
@endif
@if($preview)
    <x-metronic.card title="Preview Import" class="mt-6">
        @if(filled($preview['errors'] ?? []))
            <div class="alert alert-danger">Masih ada error validasi. Perbaiki file lalu upload ulang.</div>
            <ul>@foreach($preview['errors'] as $row => $errors)<li>Baris {{ $row }}: {{ implode(', ', $errors) }}</li>@endforeach</ul>
        @else
            <div class="alert alert-success">Semua baris valid. Klik commit untuk menyimpan dalam transaksi database.</div>
            <form method="POST" action="{{ route('admin.products.import.commit') }}">@csrf<button class="btn btn-success">Commit Import</button></form>
        @endif
        <div class="table-responsive mt-5"><table class="table table-row-dashed"><thead><tr><th>SKU</th><th>Nama</th><th>Kategori</th><th>Merek</th><th>Satuan</th><th>Status</th></tr></thead><tbody>@foreach(($preview['rows'] ?? []) as $row)<tr><td>{{ $row['sku'] ?? '-' }}</td><td>{{ $row['name'] ?? '-' }}</td><td>{{ $row['category_code'] ?? '-' }}</td><td>{{ $row['brand_code'] ?? '-' }}</td><td>{{ $row['base_unit_code'] ?? '-' }}</td><td>{{ $row['status'] ?? '-' }}</td></tr>@endforeach</tbody></table></div>
    </x-metronic.card>
@endif
@endsection
