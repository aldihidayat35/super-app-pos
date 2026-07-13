@extends('layouts.metronic.app')
@section('title', 'Satuan Produk')
@section('page_title', 'Satuan dan Konversi')
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
