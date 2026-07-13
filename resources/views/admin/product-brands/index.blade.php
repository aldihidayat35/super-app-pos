@extends('layouts.metronic.app')
@section('title', 'Merek Produk')
@section('page_title', 'Merek Produk')
@section('toolbar_actions')
    <x-metronic.permission-button permission="products.create" :href="route('admin.product-brands.create')" icon="ki-outline ki-plus">Tambah Merek</x-metronic.permission-button>
@endsection
@section('content')
<x-metronic.card>
    <form method="GET" class="d-flex justify-content-between gap-3 mb-5"><input name="q" value="{{ $search }}" class="form-control form-control-solid w-300px" placeholder="Cari merek"><button class="btn btn-light-primary">Cari</button></form>
    <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Kode</th><th>Nama</th><th>Deskripsi</th><th>Logo</th><th>Produk</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
    @forelse($brands as $brand)
        <tr><td class="fw-bold">{{ $brand->code }}</td><td>{{ $brand->name }}</td><td>{{ $brand->description ?: '-' }}</td><td>{{ $brand->logo_path ? 'Ada' : '-' }}</td><td>{{ $brand->products_count }}</td><td><x-metronic.status-badge :status="$brand->is_active ? 'active' : 'inactive'" :label="$brand->is_active ? 'Aktif' : 'Nonaktif'" /></td><td class="text-end">@can('update', $brand)<a href="{{ route('admin.product-brands.edit', $brand) }}" class="btn btn-sm btn-light-primary">Edit</a> @if($brand->is_active)<form method="POST" action="{{ route('admin.product-brands.deactivate', $brand) }}" class="d-inline">@csrf @method('PATCH')<button class="btn btn-sm btn-light-danger">Nonaktifkan</button></form>@endif @endcan</td></tr>
    @empty
        <tr><td colspan="7"><x-metronic.empty-state title="Belum ada merek" description="Merek produk akan tampil di sini." /></td></tr>
    @endforelse
    </tbody></table></div>{{ $brands->links() }}
</x-metronic.card>
@endsection
