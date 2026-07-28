@extends('layouts.metronic.app')
@section('title', 'Satuan Produk')
@section('page_title', 'Satuan dan Konversi')

@section('page_guide')
    <x-metronic.page-guide id="admin-units-index" title="Panduan Halaman Satuan & Konversi">
        <x-slot:function><p>Halaman mengelola satuan produk (pcs, pack, dus, lusin, dll) yang digunakan dalam transaksi.</p></x-slot:function>
        <x-slot:workflow><ol><li>Cari satuan berdasarkan kode atau nama.</li><li>Tambah satuan baru melalui tombol toolbar.</li><li>Konversi antar satuan diatur per produk di halaman produk.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Kode/Nama/Simbol:</strong> identitas satuan.</li><li><strong>Presisi:</strong> jumlah desimal.</li><li><strong>Dipakai Produk:</strong> jumlah produk yang menggunakan satuan ini.</li><li><strong>Status:</strong> Aktif/Nonaktif.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Stok selalu disimpan dalam satuan dasar. Konversi ke satuan lain diatur per produk.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Cari satuan.</li><li>Tambah atau edit satuan.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0"><ul><li>Nonaktifkan satuan yang memiliki produk tidak dapat menghapus secara langsung.</li></ul></div></x-slot:warnings>
        <x-slot:example><p>Satuan pcs/kemasan/dos, presisi 0, dipakai 5 produk, aktif.</p></x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <x-metronic.permission-button permission="products.create" :href="route('admin.units.create')" icon="ki-outline ki-plus">Tambah Satuan</x-metronic.permission-button>
@endsection
@section('content')
<x-metronic.card>
    <form method="GET" class="d-flex justify-content-between gap-3 mb-5"><input name="q" value="{{ $search }}" class="form-control form-control-solid w-300px" placeholder="Cari kode/nama/simbol"><button class="btn btn-light-primary">Cari</button></form>
    <div class="alert alert-info">Konversi per produk diatur pada form produk. Stok selalu disimpan dalam satuan dasar.</div>
    <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Kode</th><th>Nama</th><th>Simbol</th><th>Presisi</th><th>Dipakai Produk</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
    @forelse($units as $unit)
        <tr><td class="fw-bold">{{ $unit->code }}</td><td>{{ $unit->name }}</td><td>{{ $unit->symbol }}</td><td>{{ $unit->precision }}</td><td>{{ $unit->product_units_count }}</td><td><x-metronic.status-badge :status="$unit->is_active ? 'active' : 'inactive'" :label="$unit->is_active ? 'Aktif' : 'Nonaktif'" /></td><td class="text-end">@can('update', $unit)<a href="{{ route('admin.units.edit', $unit) }}" class="btn btn-sm btn-light-primary">Edit</a> @if($unit->is_active)<form method="POST" action="{{ route('admin.units.deactivate', $unit) }}" class="d-inline">@csrf @method('PATCH')<button class="btn btn-sm btn-light-danger">Nonaktifkan</button></form>@endif @endcan</td></tr>
    @empty
        <tr><td colspan="7"><x-metronic.empty-state title="Belum ada satuan" description="Satuan pcs/pack/dus/lusin/kodi dapat ditambahkan lewat seed atau form." /></td></tr>
    @endforelse
    </tbody></table></div>{{ $units->links() }}
</x-metronic.card>
@endsection
