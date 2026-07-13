@extends('layouts.metronic.app')

@section('title', 'Kategori Produk - ' . config('app.name'))
@section('page_title', 'Kategori dan Subkategori')

@section('toolbar_actions')
    <x-metronic.permission-button permission="products.create" :href="route('admin.product-categories.create')" icon="ki-outline ki-plus">Tambah Kategori</x-metronic.permission-button>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" class="d-flex justify-content-between gap-3 mb-5">
            <input name="q" value="{{ $search }}" class="form-control form-control-solid w-300px" placeholder="Cari kode atau nama kategori">
            <button class="btn btn-light-primary" type="submit"><i class="ki-outline ki-magnifier"></i> Cari</button>
        </form>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Kode</th><th>Nama</th><th>Parent</th><th>Urutan</th><th>Ikon</th><th>Produk</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td class="fw-bold">{{ $category->code }}</td>
                        <td>{{ $category->name }}</td>
                        <td>{{ $category->parent?->name ?: '-' }}</td>
                        <td>{{ $category->sort_order }}</td>
                        <td>{{ $category->icon ?: '-' }}</td>
                        <td>{{ $category->products_count }}</td>
                        <td><x-metronic.status-badge :status="$category->is_active ? 'active' : 'inactive'" :label="$category->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                        <td class="text-end">
                            @can('update', $category)
                                <a href="{{ route('admin.product-categories.edit', $category) }}" class="btn btn-sm btn-light-primary">Edit</a>
                                @if ($category->is_active)
                                    <form method="POST" action="{{ route('admin.product-categories.deactivate', $category) }}" class="d-inline">@csrf @method('PATCH')<button class="btn btn-sm btn-light-danger">Nonaktifkan</button></form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-metronic.empty-state title="Belum ada kategori" description="Kategori produk akan tampil di sini." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $categories->links() }}
    </x-metronic.card>
@endsection
