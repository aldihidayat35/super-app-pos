@extends('layouts.metronic.app')

@section('title', 'Pendataan Stok Lokasi - ' . config('app.name'))
@section('page_title', 'Pendataan Stok Lokasi')

@section('toolbar_actions')
    @can('opening_stocks.import')
        <a href="{{ route('warehouse.opening-stocks.import.index') }}" class="btn btn-light-primary me-2">
            <i class="ki-outline ki-file-up fs-2 me-1"></i> Import Excel
        </a>
    @endcan
    <a href="{{ route('warehouse.opening-stocks.export', ['work_location_id' => $selectedLocationId]) }}" class="btn btn-light-success">
        <i class="ki-outline ki-file-down fs-2 me-1"></i> Export CSV
    </a>
@endsection

@section('page_guide')
    <x-metronic.page-guide id="opening-stock" title="Panduan Pendataan Stok Lokasi">
        <x-slot:function><p>Mencatat jumlah fisik sekarang untuk produk yang sudah ada di database sebagai alternatif import Excel.</p></x-slot:function>
        <x-slot:workflow><p>Pilih lokasi, pilih produk lewat katalog visual, tentukan rak dan isi jumlah fisik, simpan draft, lalu ajukan. Draft staf diperiksa kepala lokasi; kepala dapat memposting draftnya sendiri.</p></x-slot:workflow>
        <x-slot:warnings><div class="alert alert-warning mb-0">Jumlah yang dimasukkan adalah saldo akhir, bukan jumlah tambahan. Jika sistem 20 dan fisik 27, mutasinya hanya +7.</div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    @error('opening_stock')<div class="alert alert-danger mb-6">{{ $message }}</div>@enderror

    <div class="row g-5 mb-6">
        <div class="col-md-3"><x-metronic.card title="Produk Aktif"><div class="fs-2 fw-bold">{{ $activeProductCount }}</div><div class="text-muted">Produk dalam database</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Sudah Diposting"><div class="fs-2 fw-bold text-success">{{ $completedProductCount }}</div><div class="text-muted">Baris pendataan selesai</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Draft"><div class="fs-2 fw-bold text-warning">{{ $draftCount }}</div><div class="text-muted">Belum diajukan</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Menunggu"><div class="fs-2 fw-bold text-primary">{{ $pendingCount }}</div><div class="text-muted">Perlu diperiksa</div></x-metronic.card></div>
    </div>

    <x-metronic.card title="Pilih Lokasi dan Filter" class="mb-6">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-3">
                <label class="form-label fw-semibold">Gudang atau toko</label>
                <select name="work_location_id" class="form-select" onchange="this.form.submit()">
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected($selectedLocationId === $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label fw-semibold">Cari produk</label>
                <input name="q" value="{{ request('q') }}" class="form-control" placeholder="SKU atau nama produk">
            </div>
            <div class="col-lg-2">
                <label class="form-label fw-semibold">Kategori</label>
                <select name="category_id" class="form-select">
                    <option value="">Semua</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((int)request('category_id') === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label fw-semibold">Riwayat</label>
                <select name="count_status" class="form-select">
                    <option value="">Semua</option>
                    <option value="not_posted" @selected(request('count_status')==='not_posted')>Belum pernah diposting</option>
                    <option value="posted" @selected(request('count_status')==='posted')>Sudah pernah diposting</option>
                </select>
            </div>
            <div class="col-lg-2">
                <button class="btn btn-light-primary w-100">
                    <i class="ki-outline ki-filter fs-5 me-1"></i> Terapkan
                </button>
            </div>
        </form>
        <div class="alert {{ $access?->isCurrentlyActive() ? 'alert-success' : 'alert-warning' }} mt-4 mb-0">
            @if($access?->isCurrentlyActive()) 
                <i class="ki-outline ki-check-circle fs-4 me-1 text-success"></i> Akses pendataan aktif sampai {{ $access->active_until?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB. 
            @else 
                <i class="ki-outline ki-information-2 fs-4 me-1 text-warning"></i> Lokasi terkunci. Super Admin harus mengaktifkan Akses Koreksi Stok untuk lokasi ini. 
            @endif
        </div>
    </x-metronic.card>

    @if($access?->isCurrentlyActive() && auth()->user()->can('opening_stocks.create'))
        <x-metronic.card title="Input Stok Sekarang" class="mb-6">
            @if($bins->isEmpty())
                <div class="alert alert-warning mb-0">
                    <i class="ki-outline ki-information fs-3 me-2"></i> Lokasi ini belum mempunyai rak atau lokasi penyimpanan aktif. Lengkapi lokasi penyimpanan sebelum melakukan pendataan.
                </div>
            @else
                <form method="POST" action="{{ route('warehouse.opening-stocks.store') }}" id="opening-stock-form">
                    @csrf
                    <input type="hidden" name="work_location_id" value="{{ $selectedLocationId }}">
                    
                    <!-- Fallback & Test Compatibility Elements -->
                    <div class="d-none">
                        <select id="opening-product-picker" class="form-select form-select-solid" multiple>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" id="select-all-products">Pilih Semua Hasil Filter</button>
                    </div>
                    
                    <!-- Action Toolbar: Open Catalog Button + Bulk Bin Setter -->
                    <div class="card bg-light-subtle border border-dashed border-gray-300 p-5 mb-5 rounded">
                        <div class="row g-4 align-items-center justify-content-between">
                            <div class="col-lg-7 d-flex align-items-center">
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productCatalogModal">
                                    <i class="ki-outline ki-category fs-2 me-1"></i> Buka Katalog Produk
                                </button>
                                <div class="ms-4">
                                    <div class="fs-5 fw-bold text-gray-800" id="selected-product-count">0 produk terpilih</div>
                                    <div class="text-muted fs-8">Klik tombol di samping untuk mencari & memilih produk dari katalog visual.</div>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <label class="form-label fw-semibold fs-7 mb-1 text-gray-700">Terapkan Rak Serentak ke Semua Baris:</label>
                                <div class="input-group">
                                    <select id="bulk-bin" class="form-select form-select-sm" data-searchable-fallback="true">
                                        @foreach($bins as $bin)
                                            <option value="{{ $bin->id }}">{{ $bin->full_code }} - {{ $bin->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-sm btn-light-primary" id="apply-bulk-bin">
                                        <i class="ki-outline ki-check fs-6 me-1"></i> Terapkan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Selected Products Table -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="fw-bold fs-6 text-gray-800 d-flex align-items-center">
                            <i class="ki-outline ki-tablet-text-up text-primary fs-3 me-2"></i> Daftar Produk yang Akan Dicatat
                        </div>
                        <span class="badge badge-light-primary fw-bold" id="table-product-count">0 produk</span>
                    </div>

                    <div class="table-responsive border rounded">
                        <table class="table table-row-bordered align-middle gs-4 gy-3 mb-0">
                            <thead class="bg-light-subtle text-muted fw-bold text-uppercase fs-8">
                                <tr>
                                    <th style="min-width: 250px;">Produk</th>
                                    <th style="min-width: 190px;">Rak / Tempat Simpan <span class="text-danger">*</span></th>
                                    <th class="text-end" style="min-width: 140px;">Jumlah Sistem</th>
                                    <th style="min-width: 180px;">Jumlah Fisik Sekarang <span class="text-danger">*</span></th>
                                    <th style="min-width: 150px;">HPP / Unit <span class="text-danger">*</span></th>
                                    <th style="min-width: 160px;">Harga Jual Lokasi <span class="text-danger">*</span></th>
                                    <th style="min-width: 200px;">Catatan Item</th>
                                    <th class="text-end" style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="opening-product-rows">
                                <tr id="opening-empty-row">
                                    <td colspan="8" class="text-center text-muted py-10">
                                        <i class="ki-outline ki-handcart fs-3x text-muted mb-2 d-block"></i>
                                        <span class="fw-semibold">Belum ada produk yang dipilih.</span>
                                        <div class="fs-8 mt-1">Silakan klik tombol <strong>"Buka Katalog Produk"</strong> di atas untuk menambahkan produk ke daftar ini.</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6">
                        <label class="form-label fw-bold">Catatan Dokumen</label>
                        <textarea name="notes" class="form-control form-control-solid" rows="2" placeholder="Contoh: Pendataan awal toko sebelum mulai operasional"></textarea>
                    </div>

                    <div class="d-flex justify-content-end mt-5">
                        <button class="btn btn-primary btn-lg" id="save-opening-stock" disabled>
                            <i class="ki-outline ki-save-2 fs-3 me-1"></i> Simpan Draft Massal
                        </button>
                    </div>
                </form>
            @endif
        </x-metronic.card>
    @endif

    <x-metronic.card title="Draft, Persetujuan, dan Riwayat">
        <div class="d-flex justify-content-end mb-4">
            <a href="{{ route('warehouse.opening-stocks.export', ['work_location_id'=>$selectedLocationId]) }}" class="btn btn-light-success">
                <i class="ki-outline ki-file-down fs-4 me-1"></i> Export CSV
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-row-bordered align-middle">
                <thead>
                    <tr class="text-muted fw-bold text-uppercase fs-7">
                        <th>Nomor</th>
                        <th>Lokasi</th>
                        <th>Status</th>
                        <th>Pembuat</th>
                        <th>Produk</th>
                        <th>Waktu</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $document)
                        <tr>
                            <td class="fw-bold">{{ $document->number }}</td>
                            <td>{{ $document->workLocation?->name }}</td>
                            <td>
                                <span class="badge badge-light-primary">
                                    {{ ['draft'=>'Draft','pending_approval'=>'Menunggu Persetujuan','completed'=>'Selesai','rejected'=>'Ditolak','cancelled'=>'Dibatalkan'][$document->status] ?? $document->status }}
                                </span>
                            </td>
                            <td>{{ $document->creator?->name }}</td>
                            <td>{{ $document->items->count() }}</td>
                            <td>{{ $document->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('warehouse.opening-stocks.show', $document) }}" class="btn btn-sm btn-light-primary">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-10">Belum ada pendataan stok untuk lokasi ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $documents->links() }}
    </x-metronic.card>

    <!-- Modal Catalog (UI matching visual-catalog-modal from pricing/product-prices & stock-adjustments) -->
    @if($access?->isCurrentlyActive() && auth()->user()->can('opening_stocks.create') && $bins->isNotEmpty())
        <div class="modal fade" id="productCatalogModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg">
                    <!-- Modal Header -->
                    <div class="modal-header border-bottom py-4 px-6 bg-light-subtle">
                        <div class="d-flex align-items-center gap-3">
                            <div class="symbol symbol-40px bg-light-primary rounded-3 p-2">
                                <i class="ki-outline ki-category fs-2 text-primary"></i>
                            </div>
                            <div>
                                <h2 class="modal-title fs-4 fw-bold mb-0">Katalog Produk & Pendataan Stok</h2>
                                <div class="text-muted fs-7">Pilih produk dari katalog visual untuk dicatat saldo stok fisiknya.</div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-icon btn-light" data-bs-dismiss="modal">
                            <i class="ki-outline ki-cross fs-2"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="modal-body p-6">
                        <!-- Filter Toolbar -->
                        <div class="card bg-light-subtle border border-dashed border-gray-300 p-4 mb-5">
                            <div class="row g-3 align-items-center">
                                <div class="col-lg-6">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0">
                                            <i class="ki-outline ki-magnifier fs-4 text-muted"></i>
                                        </span>
                                        <input type="search" id="catalog-search" class="form-control border-start-0" placeholder="Ketik nama produk atau SKU...">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <select id="catalog-category-filter" class="form-select">
                                        <option value="">Semua Kategori</option>
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->name }}">{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 pt-3 border-top border-gray-300 fs-7">
                                <div class="text-muted">
                                    Menampilkan <strong id="catalog-count">{{ $products->count() }}</strong> produk
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="badge badge-light-success px-2 py-1">
                                        <span id="catalog-selected-count" class="fw-bold me-1">0</span> Terpilih
                                    </span>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-light-primary" id="catalog-select-all">Pilih Semua</button>
                                        <button type="button" class="btn btn-sm btn-light-danger" id="catalog-clear-all">Hapus Semua</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Products Grid -->
                        <div class="row g-4" id="catalog-grid">
                            @foreach($products as $product)
                                @php
                                    $productStocks = $stockByProduct->get($product->id, collect());
                                    $totalOnHand = $productStocks->sum('quantity_on_hand');
                                    $stocksMap = $productStocks->mapWithKeys(fn($stock) => [(string)$stock->warehouse_location_id => (string)$stock->quantity_on_hand]);
                                    $imgUrl = $product->main_image_url ?? \App\Models\Product::defaultImageUrl();
                                @endphp
                                <div class="col-sm-6 col-md-4 col-lg-3 catalog-item" 
                                     data-name="{{ strtolower($product->name ?? '') }}"
                                     data-sku="{{ strtolower($product->sku ?? '') }}"
                                     data-category="{{ $product->category?->name ?? 'Tanpa Kategori' }}">
                                    
                                    <div class="card h-100 border border-gray-300 catalog-card vcm-card" data-product-id="{{ $product->id }}">
                                        <!-- Selected Badge Floating on Top Right -->
                                        <div class="position-absolute top-0 end-0 m-3 vcm-selected-badge d-none" style="z-index: 2;">
                                            <span class="badge badge-circle badge-success shadow-sm" style="width: 26px; height: 26px;">
                                                <i class="ki-outline ki-check text-white fs-6"></i>
                                            </span>
                                        </div>

                                        <!-- Image Banner -->
                                        <div class="vcm-card-img-wrap">
                                            <img src="{{ $imgUrl }}" 
                                                 class="vcm-card-img" 
                                                 alt="{{ $product->name }}" 
                                                 loading="lazy" 
                                                 onerror="this.onerror=null; this.src='/assets/media/products/default-product.png';" />
                                        </div>

                                        <!-- Card Body -->
                                        <div class="card-body p-4 d-flex flex-column">
                                            <!-- Category Badge -->
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <span class="badge badge-light-primary fw-bold px-2 py-1 fs-9">
                                                    {{ $product->category?->name ?? 'Tanpa Kategori' }}
                                                </span>
                                            </div>

                                            <!-- Product Name -->
                                            <div class="fw-bold text-gray-800 fs-6 text-truncate-2 mb-1" title="{{ $product->name }}">
                                                {{ $product->name }}
                                            </div>

                                            <!-- SKU -->
                                            <div class="text-muted fs-8 mb-3 d-flex align-items-center gap-1">
                                                <span class="fw-semibold text-gray-600">{{ $product->sku }}</span>
                                            </div>

                                            <!-- Stock & Cost Data Block (Highlight) -->
                                            <div class="bg-light-subtle rounded p-2 mb-3 border border-dashed border-gray-300">
                                                <div class="d-flex align-items-center justify-content-between mb-1">
                                                    <span class="fs-8 text-muted d-flex align-items-center gap-1">
                                                        <i class="ki-outline ki-cube-2 fs-7 text-success"></i> Stok di Lokasi:
                                                    </span>
                                                    <span class="badge badge-light-success fw-bolder fs-7 px-2 py-1">
                                                        {{ qty($totalOnHand) }} {{ $product->baseUnit?->symbol ?? 'unit' }}
                                                    </span>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-between fs-8">
                                                    <span class="text-muted d-flex align-items-center gap-1">
                                                        <i class="ki-outline ki-tag fs-8 text-primary"></i> HPP Dasar:
                                                    </span>
                                                    <span class="fw-bold text-gray-700">
                                                        Rp {{ number_format($product->cost_price, 0, ',', '.') }}
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Card Bottom Action Button -->
                                            <div class="mt-auto">
                                                <button type="button" class="btn btn-sm btn-light-primary w-100 btn-select-product"
                                                        data-id="{{ $product->id }}"
                                                        data-sku="{{ $product->sku }}"
                                                        data-name="{{ $product->name }}"
                                                        data-unit="{{ $product->baseUnit?->symbol ?? $product->baseUnit?->name }}"
                                                        data-cost="{{ $product->cost_price }}"
                                                        data-image="{{ $imgUrl }}"
                                                        data-stocks='@json($stocksMap)'>
                                                    <i class="ki-outline ki-plus fs-6"></i> Pilih Produk
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            <!-- Empty State -->
                            <div class="col-12 text-center text-muted py-12 d-none" id="no-results-msg">
                                <i class="ki-outline ki-file-deleted fs-3x text-muted mb-3 d-block"></i>
                                <h5 class="fw-bold text-gray-700">Tidak ada produk yang cocok</h5>
                                <div class="text-muted fs-7">Coba ubah kata kunci pencarian atau pilih kategori lain.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="modal-footer py-3 px-6 border-top bg-light-subtle d-flex justify-content-between align-items-center">
                        <div class="text-muted fs-7">
                            <span id="footer-selected-count" class="fw-bold text-primary">0</span> produk dipilih
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal" id="apply-catalog-selection">
                                <i class="ki-outline ki-check-circle fs-5 me-1"></i> Terapkan ke Draft
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('styles')
<style>
.vcm-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    border-radius: 0.75rem;
    overflow: hidden;
    cursor: pointer;
    position: relative;
}
.vcm-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.08);
    border-color: var(--bs-primary) !important;
}
.vcm-card.is-selected {
    border-color: var(--bs-primary) !important;
    background-color: #f1faff !important;
    box-shadow: 0 0.25rem 1rem rgba(0, 158, 247, 0.18) !important;
}
.vcm-card-img-wrap {
    height: 145px;
    background: #f8f9fa;
    position: relative;
    overflow: hidden;
}
.vcm-card-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}
.vcm-card:hover .vcm-card-img {
    transform: scale(1.06);
}
.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.35;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('catalog-search');
    const categoryFilter = document.getElementById('catalog-category-filter');
    const catalogItems = document.querySelectorAll('.catalog-item');
    const noResultsMsg = document.getElementById('no-results-msg');
    const applyBtn = document.getElementById('apply-catalog-selection');
    const rowsContainer = document.getElementById('opening-product-rows');
    const selectedCountLabel = document.getElementById('selected-product-count');
    const tableCountLabel = document.getElementById('table-product-count');
    const saveBtn = document.getElementById('save-opening-stock');
    const countEl = document.getElementById('catalog-count');
    const selectedCountEl = document.getElementById('catalog-selected-count');
    const footerSelectedCountEl = document.getElementById('footer-selected-count');
    const selectAllBtn = document.getElementById('catalog-select-all');
    const clearAllBtn = document.getElementById('catalog-clear-all');
    const modalEl = document.getElementById('productCatalogModal');
    const bulkBinSelect = document.getElementById('bulk-bin');
    const applyBulkBinBtn = document.getElementById('apply-bulk-bin');

    const binOptions = @json($bins->map(fn($bin) => ['id' => $bin->id, 'label' => $bin->full_code.' - '.$bin->name])->values());
    const oldItems = @json(old('items', []));
    const numberFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 });

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // State of selected products: id => { id, sku, name, unit, cost, image, stocks, binId, physicalQty, note }
    const selectedProducts = {};

    function updateCardUI(card, isSelected) {
        if (!card) return;
        const badge = card.querySelector('.vcm-selected-badge');
        const btn = card.querySelector('.btn-select-product');
        
        if (isSelected) {
            card.classList.add('is-selected');
            if (badge) badge.classList.remove('d-none');
            if (btn) {
                btn.classList.remove('btn-light-primary');
                btn.classList.add('btn-success');
                btn.innerHTML = '<i class="ki-outline ki-check fs-6"></i> Terpilih';
            }
        } else {
            card.classList.remove('is-selected');
            if (badge) badge.classList.add('d-none');
            if (btn) {
                btn.classList.remove('btn-success');
                btn.classList.add('btn-light-primary');
                btn.innerHTML = '<i class="ki-outline ki-plus fs-6"></i> Pilih Produk';
            }
        }
    }

    function updateSelectionCounters() {
        const count = Object.keys(selectedProducts).length;
        if (selectedCountEl) selectedCountEl.textContent = count;
        if (footerSelectedCountEl) footerSelectedCountEl.textContent = count;
        if (selectedCountLabel) selectedCountLabel.textContent = count + ' produk terpilih';
        if (tableCountLabel) tableCountLabel.textContent = count + ' produk';
        if (saveBtn) saveBtn.disabled = count === 0;
    }

    function toggleProduct(card) {
        const id = card.dataset.productId;
        const btn = card.querySelector('.btn-select-product');
        if (!id || !btn) return;

        if (selectedProducts[id]) {
            delete selectedProducts[id];
            updateCardUI(card, false);
        } else {
            selectedProducts[id] = {
                id: id,
                sku: btn.dataset.sku,
                name: btn.dataset.name,
                unit: btn.dataset.unit,
                cost: btn.dataset.cost,
                image: btn.dataset.image,
                stocks: JSON.parse(btn.dataset.stocks || '{}'),
                binId: '',
                physicalQty: '',
                note: ''
            };
            updateCardUI(card, true);
        }
        updateSelectionCounters();
    }

    // Click handler on card
    document.querySelectorAll('.vcm-card').forEach(card => {
        card.addEventListener('click', function(e) {
            toggleProduct(this);
        });
    });

    // Filter functionality (Search + Category)
    function applyFilter() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const cat = categoryFilter ? categoryFilter.value : '';
        let visibleCount = 0;

        catalogItems.forEach(item => {
            const name = item.dataset.name || '';
            const sku = item.dataset.sku || '';
            const category = item.dataset.category || '';

            const matchQuery = !query || name.includes(query) || sku.includes(query);
            const matchCat = !cat || category === cat;

            if (matchQuery && matchCat) {
                item.style.display = 'block';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        if (countEl) countEl.textContent = visibleCount;
        if (noResultsMsg) {
            if (visibleCount === 0) noResultsMsg.classList.remove('d-none');
            else noResultsMsg.classList.add('d-none');
        }
    }

    if (searchInput) searchInput.addEventListener('input', applyFilter);
    if (categoryFilter) categoryFilter.addEventListener('change', applyFilter);

    // Select All visible
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            catalogItems.forEach(item => {
                if (item.style.display !== 'none') {
                    const card = item.querySelector('.vcm-card');
                    if (card) {
                        const id = card.dataset.productId;
                        const btn = card.querySelector('.btn-select-product');
                        if (id && btn && !selectedProducts[id]) {
                            selectedProducts[id] = {
                                id: id,
                                sku: btn.dataset.sku,
                                name: btn.dataset.name,
                                unit: btn.dataset.unit,
                                cost: btn.dataset.cost,
                                image: btn.dataset.image,
                                stocks: JSON.parse(btn.dataset.stocks || '{}'),
                                binId: '',
                                physicalQty: '',
                                note: ''
                            };
                            updateCardUI(card, true);
                        }
                    }
                }
            });
            updateSelectionCounters();
        });
    }

    // Clear All
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function() {
            Object.keys(selectedProducts).forEach(id => {
                delete selectedProducts[id];
            });
            document.querySelectorAll('.vcm-card').forEach(card => {
                updateCardUI(card, false);
            });
            updateSelectionCounters();
        });
    }

    // Row calculation update
    function updateRowCalc(row, product) {
        if (!row || !product) return;
        const binSelect = row.querySelector('.bin-select');
        const binId = binSelect ? binSelect.value : '';
        const systemStock = Number(product.stocks[binId] || 0);
        const physicalInput = row.querySelector('.physical-input');
        const systemQtyEl = row.querySelector('.system-qty');
        const diffPreviewEl = row.querySelector('.difference-preview');

        if (systemQtyEl) {
            systemQtyEl.textContent = numberFormatter.format(systemStock) + ' ' + (product.unit || 'unit');
        }

        if (diffPreviewEl && physicalInput) {
            if (physicalInput.value === '') {
                diffPreviewEl.textContent = 'Selisih: -';
                diffPreviewEl.className = 'small text-muted mt-1 difference-preview';
            } else {
                const diff = Number(physicalInput.value) - systemStock;
                const diffFormatted = (diff > 0 ? '+' : '') + numberFormatter.format(diff) + ' ' + (product.unit || 'unit');
                diffPreviewEl.textContent = 'Selisih: ' + diffFormatted;
                if (diff > 0) {
                    diffPreviewEl.className = 'small text-success fw-bold mt-1 difference-preview';
                } else if (diff < 0) {
                    diffPreviewEl.className = 'small text-danger fw-bold mt-1 difference-preview';
                } else {
                    diffPreviewEl.className = 'small text-primary fw-bold mt-1 difference-preview';
                }
            }
        }
    }

    // Render Table Rows
    function renderTableRows() {
        if (!rowsContainer) return;

        // Preserve current typed values
        rowsContainer.querySelectorAll('.opening-row').forEach(row => {
            const id = row.dataset.productId;
            if (selectedProducts[id]) {
                const binEl = row.querySelector('.bin-select');
                const qtyEl = row.querySelector('.physical-input');
                const noteEl = row.querySelector('.note-input');
                if (binEl) selectedProducts[id].binId = binEl.value;
                if (qtyEl) selectedProducts[id].physicalQty = qtyEl.value;
                if (noteEl) selectedProducts[id].note = noteEl.value;
            }
        });

        const items = Object.values(selectedProducts);
        rowsContainer.innerHTML = '';

        if (items.length === 0) {
            rowsContainer.innerHTML = `
                <tr id="opening-empty-row">
                    <td colspan="6" class="text-center text-muted py-10">
                        <i class="ki-outline ki-handcart fs-3x text-muted mb-2 d-block"></i>
                        <span class="fw-semibold">Belum ada produk yang dipilih.</span>
                        <div class="fs-8 mt-1">Silakan klik tombol <strong>"Buka Katalog Produk"</strong> di atas untuk menambahkan produk ke daftar ini.</div>
                    </td>
                </tr>
            `;
            updateSelectionCounters();
            return;
        }

        items.forEach(product => {
            const row = document.createElement('tr');
            row.className = 'opening-row';
            row.dataset.productId = product.id;

            let binOptionsHtml = '';
            binOptions.forEach(b => {
                const isSelected = String(product.binId) === String(b.id) ? 'selected' : '';
                binOptionsHtml += `<option value="${escapeHtml(b.id)}" ${isSelected}>${escapeHtml(b.label)}</option>`;
            });

            const escapedName = escapeHtml(product.name);
            const escapedSku = escapeHtml(product.sku);
            const escapedUnit = escapeHtml(product.unit || 'unit');
            const escapedImg = escapeHtml(product.image || '/assets/media/products/default-product.png');
            const escapedPhysicalQty = escapeHtml(product.physicalQty !== undefined && product.physicalQty !== null ? product.physicalQty : '');
            const escapedUnitCost = escapeHtml(product.unitCost || product.cost || '');
            const escapedSalePrice = escapeHtml(product.salePrice || product.cost || '');
            const escapedNote = escapeHtml(product.note || '');

            row.innerHTML = `
                <td>
                    <input type="hidden" name="items[${escapeHtml(product.id)}][product_id]" value="${escapeHtml(product.id)}">
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-50px me-3 flex-shrink-0 bg-light rounded" style="width: 50px; height: 50px; min-width: 50px; min-height: 50px;">
                            <img src="${escapedImg}" 
                                 alt="${escapedName}" 
                                 class="rounded object-fit-cover border border-gray-200 cursor-pointer" 
                                 style="width: 50px; height: 50px; min-width: 50px; min-height: 50px; object-fit: cover; display: block;"
                                 data-bs-toggle="modal" 
                                 data-bs-target="#globalImagePreviewModal" 
                                 onclick="const img=document.getElementById('globalPreviewImage'); if(img){ img.src=this.src; } const t=document.getElementById('globalPreviewTitle'); if(t){ t.innerText=this.alt; }" 
                                 onerror="this.onerror=null; this.src='/assets/media/products/default-product.png';" 
                                 loading="lazy" 
                                 title="Klik untuk memperbesar">
                        </div>
                        <div class="d-flex flex-column">
                            <span class="fw-bold text-gray-800 fs-6 text-truncate" style="max-width: 280px;" title="${escapedName}">${escapedName}</span>
                            <span class="text-muted fs-8">SKU: <strong class="text-gray-700">${escapedSku}</strong> &bull; Satuan: ${escapedUnit}</span>
                        </div>
                    </div>
                </td>
                <td>
                    <select name="items[${escapeHtml(product.id)}][warehouse_location_id]" class="form-select form-select-sm bin-select" required>
                        ${binOptionsHtml}
                    </select>
                </td>
                <td class="text-end">
                    <span class="system-qty fw-bold text-gray-800 fs-7">0</span>
                </td>
                <td>
                    <input type="number" step="any" min="0" name="items[${escapeHtml(product.id)}][physical_qty]" class="form-control form-control-sm physical-input" value="${escapedPhysicalQty}" placeholder="0" required>
                    <div class="small text-muted mt-1 difference-preview">Selisih: -</div>
                </td>
                <td><input type="number" step="0.01" min="0.01" name="items[${escapeHtml(product.id)}][unit_cost_snapshot]" class="form-control form-control-sm" value="${escapedUnitCost}" required></td>
                <td><input type="number" step="0.01" min="0.01" name="items[${escapeHtml(product.id)}][sale_price_snapshot]" class="form-control form-control-sm" value="${escapedSalePrice}" required></td>
                <td>
                    <input name="items[${escapeHtml(product.id)}][note]" class="form-control form-control-sm note-input" value="${escapedNote}" placeholder="Opsional (kondisi/keterangan)">
                </td>
                <td class="text-end">
                    <button type="button" class="btn btn-icon btn-sm btn-light-danger remove-product" title="Hapus dari daftar">
                        <i class="ki-outline ki-trash fs-5"></i>
                    </button>
                </td>
            `;

            rowsContainer.appendChild(row);

            const binSelect = row.querySelector('.bin-select');
            const physicalInput = row.querySelector('.physical-input');

            if (binSelect) {
                binSelect.addEventListener('change', () => {
                    product.binId = binSelect.value;
                    updateRowCalc(row, product);
                });
            }

            if (physicalInput) {
                physicalInput.addEventListener('input', () => {
                    product.physicalQty = physicalInput.value;
                    updateRowCalc(row, product);
                });
            }

            updateRowCalc(row, product);
        });

        updateSelectionCounters();
    }

    // Apply selection button inside modal
    if (applyBtn) {
        applyBtn.addEventListener('click', function() {
            renderTableRows();
        });
    }

    // Remove row event listener
    if (rowsContainer) {
        rowsContainer.addEventListener('click', function(e) {
            const removeBtn = e.target.closest('.remove-product');
            if (removeBtn) {
                const row = removeBtn.closest('.opening-row');
                const id = row.dataset.productId;
                if (id) {
                    delete selectedProducts[id];
                    const card = document.querySelector(`.vcm-card[data-product-id="${id}"]`);
                    if (card) updateCardUI(card, false);
                    renderTableRows();
                }
            }
        });
    }

    // Apply bulk bin
    if (applyBulkBinBtn && bulkBinSelect) {
        applyBulkBinBtn.addEventListener('click', function() {
            const targetBinId = bulkBinSelect.value;
            rowsContainer.querySelectorAll('.opening-row').forEach(row => {
                const id = row.dataset.productId;
                const binSelect = row.querySelector('.bin-select');
                if (binSelect && selectedProducts[id]) {
                    binSelect.value = targetBinId;
                    selectedProducts[id].binId = targetBinId;
                    updateRowCalc(row, selectedProducts[id]);
                }
            });
        });
    }

    // Modal show sync
    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', function() {
            document.querySelectorAll('.vcm-card').forEach(card => {
                const id = card.dataset.productId;
                updateCardUI(card, !!selectedProducts[id]);
            });
            updateSelectionCounters();
        });
    }

    // Initialize with old values if validation failed previously
    if (oldItems && typeof oldItems === 'object') {
        Object.keys(oldItems).forEach(key => {
            const item = oldItems[key];
            const pId = item.product_id || key;
            const card = document.querySelector(`.vcm-card[data-product-id="${pId}"]`);
            if (card) {
                const btn = card.querySelector('.btn-select-product');
                if (btn) {
                    selectedProducts[pId] = {
                        id: pId,
                        sku: btn.dataset.sku,
                        name: btn.dataset.name,
                        unit: btn.dataset.unit,
                        cost: btn.dataset.cost,
                        image: btn.dataset.image,
                        stocks: JSON.parse(btn.dataset.stocks || '{}'),
                        binId: item.warehouse_location_id || '',
                        physicalQty: item.physical_qty || '',
                        unitCost: item.unit_cost_snapshot || btn.dataset.cost,
                        salePrice: item.sale_price_snapshot || btn.dataset.cost,
                        note: item.note || ''
                    };
                    updateCardUI(card, true);
                }
            }
        });
        if (Object.keys(selectedProducts).length > 0) {
            renderTableRows();
        }
    }
});
</script>
@endpush
