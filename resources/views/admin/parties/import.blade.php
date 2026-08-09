@extends('layouts.metronic.app')
@section('title', $type === 'suppliers' ? 'Import Supplier' : 'Import Pelanggan')
@section('page_title', $type === 'suppliers' ? 'Import Supplier' : 'Import Pelanggan')

@section('page_guide')
    <x-metronic.page-guide id="admin-parties-import" title="Panduan Import Massal Supplier/Pelanggan">
        <x-slot:function><p>Halaman untuk import massal data pelanggan atau supplier melalui file XLSX/CSV.</p></x-slot:function>
        <x-slot:workflow><ol><li>Unduh template Excel.</li><li>Isi data sesuai template.</li><li>Upload file XLSX/CSV.</li><li>Pratinjau hasil validasi.</li><li>Commit import jika semua valid.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Template:</strong> struktur kolom standar untuk import.</li><li><strong>Upload File:</strong> file XLSX/CSV.</li><li><strong>Preview:</strong> hasil validasi sebelum commit.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Data diimport dalam satu transaksi database. Error validasi ditampilkan sebelum commit.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Unduh template.</li><li>Isi dan upload.</li><li>Preview lalu commit.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0"><ul><li>Jangan mengubah format kolom template.</li><li>Pastikan tidak ada duplikasi kode pelanggan/supplier.</li></ul></div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection
@section('toolbar_actions')
    <a href="{{ route('admin.parties.import.template', $type) }}" class="btn btn-light">Download Template</a>
@endsection
@section('content')
<x-metronic.card title="Upload File">
    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.parties.import.preview', $type) }}" class="row g-3 align-items-end">@csrf<div class="col-md-8"><x-metronic.form-group name="file" label="File XLSX/CSV"><input type="file" name="file" class="form-control" accept=".csv,.txt,.xlsx,.xls"></x-metronic.form-group></div><div class="col-md-4"><button class="btn btn-primary w-100">Preview Validasi</button></div></form>
</x-metronic.card>
@if($result)<div class="alert alert-success mt-6">Import selesai. Dibuat: {{ $result['created'] ?? 0 }}, diperbarui: {{ $result['updated'] ?? 0 }}.</div>@endif
@if($preview)
<x-metronic.card title="Preview Import" class="mt-6">
    @if(filled($preview['errors'] ?? []))<div class="alert alert-danger">Masih ada error validasi.</div><ul>@foreach($preview['errors'] as $row => $rowErrors)<li>Baris {{ $row }}: {{ implode(', ', $rowErrors) }}</li>@endforeach</ul>@else<div class="alert alert-success">Semua baris valid. Commit akan disimpan dalam transaksi database.</div><form method="POST" action="{{ route('admin.parties.import.commit', $type) }}">@csrf<button class="btn btn-success">Commit Import</button></form>@endif
    <div class="table-responsive mt-5"><table class="table table-row-dashed"><tbody>@foreach(($preview['rows'] ?? []) as $row)<tr>@foreach($row as $value)<td>{{ $value ?: '-' }}</td>@endforeach</tr>@endforeach</tbody></table></div>
</x-metronic.card>
@endif
@endsection
