@extends('layouts.metronic.app')

@section('title', 'Kategori Produk - ' . config('app.name'))
@section('page_title', 'Kategori dan Subkategori')

@section('page_guide')
    <x-metronic.page-guide id="admin-product-categories-index" title="Panduan Kategori Produk">
        <x-slot:function><p>Halaman mengelola kategori dan subkategori produk untuk pengelompokan dan pelaporan.</p></x-slot:function>
        <x-slot:workflow><ol><li>Cari kategori berdasarkan kode atau nama.</li><li>Tambah kategori/subkategori baru.</li><li>Atur parent untuk kategori berjenjang.</li><li>Nonaktifkan kategori yang sudah tidak digunakan.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Kode/Nama:</strong> identitas kategori.</li><li><strong>Parent:</strong> kategori induk jika subkategori.</li><li><strong>Urutan:</strong> urutan tampil.</li><li><strong>Ikon:</strong> ikon visual.</li><li><strong>Produk:</strong> jumlah produk yang menggunakan.</li><li><strong>Status:</strong> Aktif/Nonaktif.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Kategori menentukan pelaporan penjualan dan pelacakan stok per kelompok produk.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Cari kategori.</li><li>Tambah/edit kategori.</li><li>Atur parent.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0"><ul><li>Kategori dengan produk tidak dapat dihapus, hanya dapat dinonaktifkan.</li></ul></div></x-slot:warnings>
        <x-slot:example><p>Kategori "Kopi", subkategori "Arabica" & "Robusta", urutan 1.</p></x-slot:example>
    </x-metronic.page-guide>
@endsection

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
