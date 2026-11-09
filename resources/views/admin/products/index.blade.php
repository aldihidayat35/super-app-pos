@extends('layouts.metronic.app')
@section('title', 'Daftar Produk')
@section('page_title', 'Daftar Produk')
@section('toolbar_actions')
    <x-metronic.permission-button permission="products.import" :href="route('admin.products.import.index')" class="btn-light" icon="ki-outline ki-file-up">Import</x-metronic.permission-button>
    <x-metronic.permission-button permission="products.export" :href="route('admin.products.export')" class="btn-light" icon="ki-outline ki-file-down">Export</x-metronic.permission-button>
    <x-metronic.permission-button permission="products.create" :href="route('admin.products.create')" icon="ki-outline ki-plus">Tambah Produk</x-metronic.permission-button>
    @if(auth()->user()?->hasRole('super_admin'))
        <form method="POST" action="{{ route('admin.products.reset-all') }}" id="form-reset-products" class="d-inline" data-no-loading="true">
            @csrf
            <button type="button" class="btn btn-light-danger" id="btn-reset-products" title="Khusus Super Admin: Reset seluruh produk & stok untuk uji coba">
                <i class="ki-outline ki-trash fs-4 me-1"></i> Reset Produk (Uji Coba)
            </button>
        </form>
    @endif
@endsection
@section('page_guide')
    <x-metronic.page-guide id="admin-products" title="Panduan Halaman Daftar Produk">
        <x-slot:function>
            <p>Halaman ini mengelola master produk yang menjadi dasar seluruh transaksi gudang dan retail. Owner, Purchasing, dan Warehouse Manager menggunakannya untuk menambah, mengedit, dan memfilter produk berdasarkan kategori, merek, status, dan stok.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Filter produk berdasarkan pencarian SKU/nama, kategori, merek, status, dan stok di bawah minimum.</li><li>Tambah produk baru atau import dari file.</li><li>Status Produk menentukan apakah produk dapat dibeli atau dikirim.</li><li>HPP dan Margin dilihat oleh user dengan permission khusus.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Cari SKU/nama:</strong> kolom pencarian produk.</li><li><strong>Filter Kategori/Merek/Status/Stok Minimum:</strong> filter produk.</li><li><strong>Import/Export:</strong> upload/download data produk.</li><li><strong>Kode (SKU):</strong> identitas unik produk.</li><li><strong>Nama:</strong> nama produk.</li><li><strong>Kategori/Merek/Satuan:</strong> atribut produk.</li><li><strong>Status:</strong> status produk aktif/nonaktif/draft.</li><li><strong>Stok Total:</strong> total on hand semua lokasi.</li><li><strong>HPP:</strong> harga per unit.</li><li><strong>Harga Minimum:</strong> batas harga jual minimum.</li><li><strong>Detail/Edit:</strong> aksi pengelolaan produk.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Produk nonaktif tidak tampil pada halaman penjualan. HPP berubah saat penerimaan barang baru. Stok total dipengaruhi mutasi stok.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Filter produk.</li><li>Klik Tambah Produk atau Import.</li><li>Detail/Edit produk.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Produk dengan histori transaksi tidak dapat dihapus.</li><li>SKU harus unik.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Produk SKU KOF-ARA Arabika, Kategori Kopi, Merek Premium, status Aktif, stok 100, HPP Rp 25.000.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection
@section('content')
<x-metronic.card>
    <form method="GET" class="d-flex flex-wrap justify-content-between gap-3 mb-5">
        <div class="d-flex flex-wrap gap-3">
            <input name="q" value="{{ $filters['q'] }}" class="form-control form-control-solid w-225px" placeholder="Cari SKU/nama">
            <select name="category_id" class="form-select form-select-solid w-200px"><option value="">Semua Kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((int) $filters['category_id'] === $category->id)>{{ $category->name }}</option>@endforeach</select>
            <select name="brand_id" class="form-select form-select-solid w-200px"><option value="">Semua Merek</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((int) $filters['brand_id'] === $brand->id)>{{ $brand->name }}</option>@endforeach</select>
            <select name="status" class="form-select form-select-solid w-175px"><option value="">Semua Status</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>@endforeach</select>
            <select name="stock_filter" class="form-select form-select-solid w-175px"><option value="">Semua Stok</option><option value="minimum" @selected($filters['stock_filter'] === 'minimum')>Di bawah minimum</option></select>
        </div>
        <button class="btn btn-light-primary">Filter</button>
    </form>
    <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Kategori</th><th>Merek</th><th>Satuan</th><th>Status</th><th class="text-end">Stok Total</th><th>HPP</th><th>Harga Minimum</th><th class="text-end">Aksi</th></tr></thead><tbody>
    @forelse($products as $product)
        <tr>
            <td>
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-35px me-3">
                        <x-metronic.image-preview url="{{ $product->main_image_url }}" title="{{ $product->name }}" />
                    </div>
                    <div>
                        <a href="{{ route('admin.products.show', $product) }}" class="fw-bold text-gray-900 text-hover-primary" data-mobile-primary>{{ $product->name }}</a>
                        <div class="text-muted fs-7" data-mobile-secondary>{{ $product->sku }}</div>
                    </div>
                </div>
            </td>
            <td><span data-mobile-subtitle>{{ $product->category?->name }}</span></td><td>{{ $product->brand?->name ?: '-' }}</td><td>{{ $product->baseUnit?->symbol }}</td><td><x-metronic.status-badge :status="$product->status->value" :label="$product->status->label()" /></td><td class="text-end fw-bold"><span data-mobile-highlight data-mobile-highlight-label="Stok Total">{{ intval($product->total_stock) }}</span></td>
            <td>{{ App\Support\CurrencyFormatter::rupiah($product->cost_price) }}</td>
            <td>{{ App\Support\CurrencyFormatter::rupiah($product->minimum_price) }}</td>
            <td class="text-end"><a href="{{ route('admin.products.show', $product) }}" class="btn btn-sm btn-light">Detail</a> @can('update', $product)<a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-light-primary">Edit</a>@endcan</td>
        </tr>
    @empty
        <tr><td colspan="9"><x-metronic.empty-state title="Belum ada produk" description="Produk akan tampil setelah dibuat atau diimport." /></td></tr>
    @endforelse
    </tbody></table></div>{{ $products->links() }}
</x-metronic.card>

@if(auth()->user()?->hasRole('super_admin'))
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const btnResetProducts = document.getElementById('btn-reset-products');
        const formResetProducts = document.getElementById('form-reset-products');

        if (btnResetProducts && formResetProducts) {
            btnResetProducts.addEventListener('click', function () {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Reset Semua Produk?',
                        html: '<div class="text-start fs-7 text-gray-700">Tindakan ini akan <strong>menghapus seluruh data master produk, barcode, satuan, harga, dan saldo stok</strong> yang ada di sistem.<br><br><span class="text-danger fw-bold"><i class="ki-outline ki-information-2 text-danger"></i> Fitur ini khusus mode uji coba untuk memudahkan pembersihan data tes.</span></div>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: '<i class="ki-outline ki-trash fs-4 me-1"></i> Ya, Reset Semua Produk',
                        cancelButtonText: 'Batal',
                        customClass: {
                            confirmButton: 'btn btn-danger fw-bold',
                            cancelButton: 'btn btn-light fw-bold'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            btnResetProducts.disabled = true;
                            btnResetProducts.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Mereset Produk...';
                            formResetProducts.submit();
                        }
                    });
                } else {
                    if (confirm('PERINGATAN: Apakah Anda yakin ingin mereset seluruh data master produk dan stok untuk keperluan uji coba?')) {
                        btnResetProducts.disabled = true;
                        formResetProducts.submit();
                    }
                }
            });
        }
    });
    </script>
    @endpush
@endif
@endsection
