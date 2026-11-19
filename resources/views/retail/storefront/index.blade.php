@extends('layouts.metronic.app')

@section('title', 'Etalase Produk Toko')

@section('page_guide')
    <x-metronic.page-guide id="retail-storefront" title="Panduan Etalase Produk Toko">
        <x-slot:function>Menunjukkan produk yang tersedia di toko penugasan beserta harga jual, stok reguler, stok darurat, dan lokasi pajangnya.</x-slot:function>
        <x-slot:workflow>Cari produk atau pilih kategori. Jika melayani lebih dari satu toko, pilih toko yang sedang ditangani.</x-slot:workflow>
        <x-slot:parts>Area, rak, dan tingkat otomatis terisi dari saldo stok fisik toko dan dapat disesuaikan kepala toko melalui tombol Atur Lokasi pada kartu produk.</x-slot:parts>
        <x-slot:impacts>Perubahan lokasi pajang tidak memindahkan stok fisik atau mengubah harga jual.</x-slot:impacts>
        <x-slot:operation>Harga mengikuti harga POS toko. Konfirmasi harga akhir dan ketersediaan sebelum transaksi di kasir.</x-slot:operation>
        <x-slot:warnings>Centang Tampilkan produk kosong untuk melihat barang tanpa stok. POS memakai stok reguler terlebih dahulu, lalu stok darurat.</x-slot:warnings>
        <x-slot:example>Cari SKU, lihat rak produk, lalu arahkan pelanggan ke area yang tertera.</x-slot:example>
    </x-metronic.page-guide>
@endsection

@push('styles')
<style>
    /* Storefront Grid: 2 kolom rapi di mobile, responsif di layar lebar */
    .storefront-grid { 
        display: grid; 
        grid-template-columns: repeat(2, minmax(0, 1fr)); 
        gap: 0.85rem; 
    }
    @media (min-width: 576px) {
        .storefront-grid {
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 1.15rem;
        }
    }
    @media (min-width: 992px) {
        .storefront-grid {
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 1.25rem;
        }
    }

    /* Card styling yang modern, simpel, & clean */
    .storefront-card { 
        background: var(--bs-card-bg, #ffffff);
        border: 1px solid var(--bs-gray-200, #ebedf3); 
        border-radius: 0.85rem; 
        overflow: hidden; 
        display: flex;
        flex-direction: column;
        height: 100%;
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }
    .storefront-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
        border-color: var(--bs-gray-300, #d5d7e0);
    }
    .storefront-card.out-of-stock {
        background: var(--bs-card-bg, #ffffff);
        border-color: var(--bs-gray-200, #ebedf3);
    }
    .storefront-card.out-of-stock .storefront-photo {
        opacity: 0.85;
    }

    /* Foto Produk dengan Aspect Ratio 1:1 square */
    .storefront-photo { 
        position: relative;
        width: 100%;
        aspect-ratio: 1 / 1;
        background: var(--bs-gray-100, #f8f9fa); 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        overflow: hidden; 
    }
    .storefront-photo img { 
        width: 100%; 
        height: 100%; 
        object-fit: contain; 
        padding: 0.5rem;
        transition: transform 0.25s ease;
    }
    .storefront-card:hover .storefront-photo img {
        transform: scale(1.04);
    }
    .storefront-img-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--bs-gray-100, #f8f9fa);
    }

    /* Floating Badges di Foto */
    .storefront-badge-category {
        position: absolute;
        top: 8px;
        left: 8px;
        z-index: 2;
        background: rgba(255, 255, 255, 0.92);
        backdrop-filter: blur(4px);
        color: var(--bs-gray-700, #4b5675);
        font-size: 0.68rem;
        font-weight: 600;
        padding: 2px 7px;
        border-radius: 999px;
        border: 1px solid rgba(0, 0, 0, 0.06);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        max-width: 90px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .storefront-badge-status {
        position: absolute;
        top: 8px;
        right: 8px;
        z-index: 2;
        font-size: 0.68rem;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 999px;
        line-height: 1.2;
    }
    .storefront-badge-status.status-empty {
        background: #fee2e2;
        color: #dc2626;
        border: 1px solid #fca5a5;
    }
    .storefront-badge-status.status-low {
        background: #fef3c7;
        color: #d97706;
        border: 1px solid #fde68a;
    }

    /* Card Body */
    .storefront-card-body { 
        padding: 0.75rem; 
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }
    @media (min-width: 768px) {
        .storefront-card-body {
            padding: 0.9rem;
        }
    }

    /* Metadata SKU & Brand */
    .storefront-meta {
        font-size: 0.72rem;
        color: var(--bs-gray-500, #99a1b7);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 0.25rem;
    }

    /* Judul Produk (Maks 2 baris rapi) */
    .storefront-product-title { 
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--bs-gray-900, #181c32);
        line-height: 1.35;
        margin-bottom: 0.35rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        height: 2.7em;
    }

    /* Harga Jual */
    .storefront-price {
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--bs-primary, #009ef7);
        line-height: 1.2;
        margin-bottom: 0.35rem;
    }

    /* Informasi Stok */
    .storefront-stock-info {
        font-size: 0.72rem;
        color: var(--bs-gray-600, #7e8299);
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }

    /* Strip Lokasi Pajang */
    .storefront-location-strip {
        margin-top: auto;
        padding: 0.45rem 0.55rem;
        border-radius: 0.5rem;
        border-style: solid;
        border-width: 1px;
    }
    .storefront-location-strip.has-placement {
        background-color: var(--bs-light-primary, #f1faff);
        border-color: rgba(0, 158, 247, 0.25);
    }
    .storefront-location-strip.no-placement {
        background-color: var(--bs-light-warning, #fff8dd);
        border-color: rgba(246, 192, 0, 0.35);
    }

    /* Category Pill Touch-Scroll Bar */
    .storefront-category-scroll {
        display: flex;
        gap: 0.5rem;
        overflow-x: auto;
        padding-bottom: 0.35rem;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
    }
    .storefront-category-scroll::-webkit-scrollbar {
        display: none;
    }
    .category-pill {
        padding: 0.38rem 0.85rem;
        border-radius: 999px;
        background: var(--bs-gray-100, #f4f5f8);
        color: var(--bs-gray-700, #4b5675);
        font-size: 0.78rem;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
        transition: all 0.15s ease;
        border: 1px solid transparent;
        flex-shrink: 0;
    }
    .category-pill:hover {
        background: var(--bs-gray-200, #ebedf3);
        color: var(--bs-primary, #009ef7);
    }
    .category-pill.active {
        background: var(--bs-primary, #009ef7);
        color: #ffffff !important;
        border-color: var(--bs-primary, #009ef7);
        box-shadow: 0 2px 6px rgba(0, 158, 247, 0.25);
    }

    /* Mode Gelap Support */
    [data-bs-theme='dark'] .storefront-card {
        background: var(--bs-card-bg, #1e1e2d);
        border-color: var(--bs-gray-300, #2b2b40);
    }
    [data-bs-theme='dark'] .storefront-photo {
        background: rgba(255, 255, 255, 0.03);
    }
    [data-bs-theme='dark'] .storefront-badge-category {
        background: rgba(30, 30, 45, 0.9);
        color: var(--bs-gray-300, #b5b5c3);
        border-color: rgba(255, 255, 255, 0.1);
    }
    [data-bs-theme='dark'] .category-pill {
        background: rgba(255, 255, 255, 0.06);
        color: var(--bs-gray-300, #b5b5c3);
    }
    [data-bs-theme='dark'] .category-pill.active {
        background: var(--bs-primary);
        color: #ffffff !important;
    }
</style>
@endpush

@section('toolbar_actions')
    @can('retail.catalog.manage')
        @if ($branch)
            <form method="POST" action="{{ route('retail.storefront.sync-stock') }}" class="d-inline me-2" onsubmit="return confirm('Apakah Anda yakin ingin menyinkronkan seluruh lokasi produk dari saldo stok fisik toko?');">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <button type="submit" class="btn btn-sm btn-light-primary">
                    <i class="ki-outline ki-arrows-circle fs-5 me-1"></i>Sinkronkan dari Saldo Stok
                </button>
            </form>
        @endif
    @endcan
    @can('product_requests.view')
        <a href="{{ route('retail.product-requests.index') }}" class="btn btn-sm btn-light-primary">
            <i class="ki-outline ki-plus-circle fs-5 me-1"></i>Pengajuan Produk
        </a>
    @endcan
@endsection

@section('content')
    <x-metronic.page-title title="Etalase Produk Toko" description="Bantu pelanggan menemukan produk, harga, dan posisi pajang di toko Anda.">
        @can('product_requests.view')
            <x-slot:actions>
                <a href="{{ route('retail.product-requests.index') }}" class="btn btn-sm btn-light-primary">
                    <i class="ki-outline ki-plus-circle fs-5 me-1"></i>Pengajuan Produk
                </a>
            </x-slot:actions>
        @endcan
    </x-metronic.page-title>

    @if (!$branch)
        <x-metronic.empty-state title="Belum ada toko yang ditugaskan" description="Minta admin menambahkan cabang/toko aktif pada lokasi kerja akun Anda." />
    @else
        {{-- Search, Filters, & Category Pills --}}
        <div class="card border-0 shadow-xs mb-4">
            <div class="card-body p-3 p-md-4">
                <form method="GET" action="{{ route('retail.storefront.index') }}" id="storefront-filter-form">
                    @if ($branch)
                        <input type="hidden" name="branch_id" value="{{ $branch->id }}" id="storefront-input-branch-id">
                    @endif
                    @if ($categoryId)
                        <input type="hidden" name="category_id" value="{{ $categoryId }}" id="storefront-input-category-id">
                    @endif

                    {{-- Search Row with Quick Filter Toggle --}}
                    <div class="d-flex gap-2">
                        <div class="input-group input-group-solid flex-grow-1">
                            <span class="input-group-text bg-light-subtle border-0 pe-1">
                                <i class="ki-outline ki-magnifier fs-4 text-gray-500"></i>
                            </span>
                            <input type="text" 
                                   id="storefront-search" 
                                   name="q" 
                                   value="{{ $search }}" 
                                   class="form-control form-control-solid border-0 ps-2" 
                                   placeholder="Cari nama barang, SKU, atau model...">
                            @if ($search !== '')
                                <a href="{{ route('retail.storefront.index', array_merge(request()->except(['q', 'page']))) }}" 
                                   class="btn btn-icon btn-sm btn-light border-0 my-auto me-1" 
                                   title="Hapus pencarian">
                                    <i class="ki-outline ki-cross fs-4"></i>
                                </a>
                            @endif
                        </div>
                        <button type="submit" class="btn btn-primary px-3 px-sm-4 d-flex align-items-center gap-1">
                            <i class="ki-outline ki-magnifier fs-5 d-none d-sm-inline"></i>
                            <span>Cari</span>
                        </button>
                        @php
                            $filterCount = ($area !== '' ? 1 : 0) + ($showEmpty ? 1 : 0);
                        @endphp
                        <button type="button" 
                                class="btn btn-light-primary px-3 d-flex align-items-center gap-1 {{ ($area !== '' || $showEmpty) ? 'active' : '' }}" 
                                data-bs-toggle="collapse" 
                                data-bs-target="#storefrontFilterCollapse" 
                                aria-expanded="{{ ($area !== '' || $showEmpty) ? 'true' : 'false' }}"
                                title="Filter Lanjutan">
                            <i class="ki-outline ki-filter fs-5"></i>
                            <span class="d-none d-md-inline">Filter</span>
                            @if ($filterCount > 0)
                                <span class="badge badge-circle badge-primary w-18px h-18px fs-9">{{ $filterCount }}</span>
                            @endif
                        </button>
                    </div>

                    {{-- Collapsible Filter: Area, Toko, & Kosong --}}
                    <div class="collapse {{ ($area !== '' || $showEmpty) ? 'show' : '' }} mt-3 pt-3 border-top" id="storefrontFilterCollapse">
                        <div class="row g-3 align-items-end">
                            @if ($branches->count() > 1)
                                <div class="col-12 col-md-4">
                                    <label for="storefront-branch" class="form-label fs-8 fw-semibold text-gray-700">Toko</label>
                                    <select id="storefront-branch" name="branch_id" class="form-select form-select-solid form-select-sm" onchange="this.form.submit()">
                                        @foreach ($branches as $availableBranch)
                                            <option value="{{ $availableBranch->id }}" @selected($branch->id === $availableBranch->id)>{{ $availableBranch->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="col-12 col-md-4">
                                <label for="storefront-area" class="form-label fs-8 fw-semibold text-gray-700">Area Pajang</label>
                                <select id="storefront-area" name="area" class="form-select form-select-solid form-select-sm">
                                    <option value="">Semua area</option>
                                    @foreach ($areas as $availableArea)
                                        <option value="{{ $availableArea }}" @selected($area === $availableArea)>{{ $availableArea }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 col-md-4">
                                <div class="form-check form-switch pt-2">
                                    <input class="form-check-input" type="checkbox" id="show-empty-switch" name="show_empty" value="1" @checked($showEmpty)>
                                    <label class="form-check-label fs-8 fw-semibold text-gray-700" for="show-empty-switch">
                                        Termasuk stok kosong
                                    </label>
                                </div>
                            </div>

                            <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                                <a href="{{ route('retail.storefront.index', ['branch_id' => $branch->id]) }}" class="btn btn-sm btn-light">
                                    Reset
                                </a>
                                <button type="submit" class="btn btn-sm btn-primary">
                                    Terapkan Filter
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                {{-- Category Horizontal Pills --}}
                @if ($categories->isNotEmpty())
                    <div class="storefront-category-scroll mt-3">
                        <a href="{{ route('retail.storefront.index', array_merge(request()->except(['category_id', 'page']))) }}" 
                           class="category-pill {{ empty($categoryId) ? 'active' : '' }}">
                            Semua Kategori
                        </a>
                        @foreach ($categories as $cat)
                            <a href="{{ route('retail.storefront.index', array_merge(request()->except('page'), ['category_id' => $cat->id])) }}" 
                               class="category-pill {{ $categoryId == $cat->id ? 'active' : '' }}">
                                {{ $cat->name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Toko & Status Header --}}
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 px-1">
            <div>
                <div class="fw-bold fs-4 text-gray-900">{{ $branch->name }}</div>
                <div class="text-muted fs-8">{{ $products->total() }} produk tersedia untuk dilayani</div>
            </div>
            @can('retail.catalog.manage')
                <form method="POST" action="{{ route('retail.storefront.sync-stock') }}" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menyinkronkan seluruh lokasi produk yang belum diatur dari saldo stok fisik toko?');">
                    @csrf
                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                    <button type="submit" class="btn btn-sm btn-light-primary">
                        <i class="ki-outline ki-arrows-circle fs-5 me-1"></i>Sinkronkan dari Saldo Stok
                    </button>
                </form>
            @endcan
        </div>

        @if ($products->isEmpty())
            <x-metronic.empty-state title="Produk tidak ditemukan" description="Belum ada stok siap jual di toko ini yang sesuai dengan pencarian Anda." />
        @else
            <div class="storefront-grid mb-6">
                @foreach ($products as $product)
                    @php
                        $placement = $placements->get($product->id);
                        $stockData = $cards[$product->id];
                        $isOutOfStock = floatval($stockData['regular_stock']) <= 0 && floatval($stockData['emergency_stock']) <= 0;
                        $cardClass = $isOutOfStock ? 'storefront-card out-of-stock' : 'storefront-card';
                    @endphp
                    <article class="{{ $cardClass }}">
                        {{-- Foto Produk dengan Floating Badges --}}
                        <div class="storefront-photo">
                            @if ($product->category)
                                <span class="storefront-badge-category" title="{{ $product->category->name }}">
                                    {{ $product->category->name }}
                                </span>
                            @endif

                            @if ($isOutOfStock)
                                <span class="storefront-badge-status status-empty">Stok Kosong</span>
                            @elseif (floatval($stockData['stock']) <= 2)
                                <span class="storefront-badge-status status-low">Sisa {{ qty($stockData['stock']) }}</span>
                            @endif

                            @if ($product->main_image_url)
                                <x-metronic.image-preview url="{{ $product->main_image_url }}" title="{{ $product->name }}" classes="" size="" />
                            @else
                                <div class="storefront-img-placeholder">
                                    <i class="ki-outline ki-package fs-2 text-muted" aria-hidden="true"></i>
                                </div>
                            @endif
                        </div>

                        {{-- Card Body --}}
                        <div class="storefront-card-body">
                            {{-- SKU & Brand --}}
                            <div class="storefront-meta text-truncate">
                                <span>{{ $product->sku }}</span>
                                @if ($product->brand)
                                    <span class="text-gray-400 mx-1">•</span><span class="fw-medium text-gray-700">{{ $product->brand->name }}</span>
                                @endif
                            </div>

                            {{-- Judul Produk --}}
                            <h2 class="storefront-product-title" title="{{ $product->name }}">{{ $product->name }}</h2>

                            {{-- Harga Jual --}}
                            <div class="storefront-price">
                                {{ \App\Support\CurrencyFormatter::rupiah($stockData['price']) }}
                            </div>

                            {{-- Ketersediaan Stok --}}
                            @if ($isOutOfStock)
                                <div class="storefront-stock-info text-danger">
                                    <span><i class="ki-outline ki-cross-circle fs-8 text-danger me-1"></i>Stok Kosong</span>
                                </div>
                            @else
                                <div class="storefront-stock-info">
                                    <span class="text-gray-700">
                                        <i class="ki-outline ki-check-circle fs-8 text-success me-1"></i>Stok: <strong>{{ qty($stockData['stock']) }} {{ $product->baseUnit?->symbol ?? 'PCS' }}</strong>
                                    </span>
                                    @if (floatval($stockData['emergency_stock']) > 0)
                                        <span class="badge badge-light-warning py-0 px-1 fs-9 ms-auto" title="Stok darurat toko">
                                            Drt: {{ qty($stockData['emergency_stock']) }}
                                        </span>
                                    @endif
                                </div>
                            @endif

                            {{-- Lokasi Pajang --}}
                            <div class="storefront-location-strip {{ $placement ? 'has-placement' : 'no-placement' }}">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center text-truncate me-1">
                                        <i class="ki-outline ki-geolocation {{ $placement ? 'text-primary' : 'text-warning' }} fs-7 me-1 flex-shrink-0"></i>
                                        <div class="text-truncate">
                                            @if ($placement)
                                                <span class="fs-9 fw-bold text-gray-800 d-block text-truncate">{{ $placement->area }}</span>
                                                <span class="fs-9 text-muted d-block text-truncate">
                                                    @php
                                                        $detailRack = collect([$placement->rack, $placement->shelf])->filter()->implode(' · ');
                                                        if (! $detailRack && !empty($placement->full_code)) {
                                                            $detailRack = $placement->full_code;
                                                        }
                                                    @endphp
                                                    {{ $detailRack ?: 'Area '.$placement->area }}
                                                </span>
                                            @else
                                                <span class="fs-9 fw-semibold text-warning d-block">
                                                    Belum ditata
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    @can('retail.catalog.manage')
                                        <button type="button" 
                                                class="btn btn-icon btn-xs {{ $placement ? 'btn-light-primary' : 'btn-light-warning' }} w-24px h-24px flex-shrink-0 rounded-circle btn-placement-trigger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modal-placement"
                                                data-product-id="{{ $product->id }}"
                                                data-product-name="{{ $product->name }}"
                                                data-product-sku="{{ $product->sku }}"
                                                data-area="{{ $placement?->area ?? '' }}"
                                                data-rack="{{ $placement?->rack ?? '' }}"
                                                data-shelf="{{ $placement?->shelf ?? '' }}"
                                                data-location-id="{{ $placement?->location_id ?? '' }}"
                                                title="Atur lokasi pajang">
                                            <i class="ki-outline {{ $placement ? 'ki-pencil' : 'ki-plus' }} fs-8"></i>
                                        </button>
                                    @endcan
                                </div>
                                @if ($placement && !empty($placement->is_from_stock))
                                    <div class="mt-1 fs-9 text-primary text-truncate d-flex align-items-center">
                                        <span class="badge badge-light-primary py-0 px-1 fs-9" style="font-size: 0.65rem !important;">Dari Saldo Stok</span>
                                        @if (!empty($placement->additional_locations_count) && $placement->additional_locations_count > 0)
                                            <span class="text-muted ms-1" style="font-size: 0.65rem !important;">(+{{ $placement->additional_locations_count }} lokasi lain)</span>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Tombol Beli Darurat (hanya muncul saat stok kosong/kritis) --}}
                            @can('emergency_purchases.create')
                                @if ($isOutOfStock || floatval($stockData['stock']) <= 2)
                                    <a href="{{ route('retail.emergency.create', ['branch_id' => $branch->id, 'product_id' => $product->id]) }}" 
                                       class="btn btn-xs btn-light-warning w-100 mt-2 py-1 fs-9 d-flex align-items-center justify-content-center gap-1">
                                        <i class="ki-outline ki-flash fs-8"></i> Beli Darurat
                                    </a>
                                @endif
                            @endcan
                        </div>
                    </article>
                @endforeach
            </div>
            {{ $products->links() }}
        @endif

        {{-- Modal Atur Lokasi Pajang (Satu modal reusable untuk semua produk) --}}
        @can('retail.catalog.manage')
            <div class="modal fade" id="modal-placement" tabindex="-1" aria-labelledby="modalPlacementLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form id="form-placement" method="POST" action="">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="branch_id" value="{{ $branch->id }}">

                            <div class="modal-header pb-0 border-0">
                                <h3 class="modal-title fs-5 fw-bold" id="modalPlacementLabel">
                                    <i class="ki-outline ki-geolocation text-primary me-2 fs-3"></i>Atur Lokasi Pajang
                                </h3>
                                <button type="button" class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Tutup">
                                    <i class="ki-outline ki-cross fs-2"></i>
                                </button>
                            </div>

                            <div class="modal-body py-4">
                                <div class="p-3 bg-light rounded mb-4">
                                    <div class="fw-bold text-gray-900 fs-6" id="modal-product-name">-</div>
                                    <div class="text-muted fs-8">SKU: <span id="modal-product-sku">-</span></div>
                                </div>

                                @if ($storeLocations->isNotEmpty())
                                    <div class="mb-4">
                                        <label class="form-label fs-7 fw-semibold" for="modal-location-select">Pilih dari Master Rak / Lokasi Toko:</label>
                                        <select id="modal-location-select" class="form-select form-select-solid form-select-sm">
                                            <option value="">-- Pilih Lokasi Fisik Toko --</option>
                                            @foreach ($storeLocations as $loc)
                                                <option value="{{ $loc->id }}"
                                                    data-area="{{ $loc->parent ? ($loc->parent->parent?->name ?? $loc->parent->name) : $loc->name }}"
                                                    data-rack="{{ $loc->type->value === 'rack' ? $loc->name : ($loc->parent?->type->value === 'rack' ? $loc->parent->name : '') }}"
                                                    data-shelf="{{ $loc->type->value === 'bin' ? $loc->name : '' }}">
                                                    {{ $loc->full_code }} ({{ $loc->name }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-text fs-9">Memilih lokasi fisik akan otomatis mengisi field Area, Rak, dan Tingkat di bawah.</div>
                                    </div>
                                @endif

                                <div class="mb-3">
                                    <label class="form-label fs-7 fw-semibold required" for="modal-input-area">Area Pajang</label>
                                    <input id="modal-input-area" name="area" required maxlength="80" class="form-control form-control-solid" placeholder="Contoh: Area Depan, Lantai 1, dsb.">
                                </div>

                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label fs-7 fw-semibold" for="modal-input-rack">Rak</label>
                                        <input id="modal-input-rack" name="rack" maxlength="80" class="form-control form-control-solid" placeholder="Contoh: Rak A1">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fs-7 fw-semibold" for="modal-input-shelf">Tingkat Rak</label>
                                        <input id="modal-input-shelf" name="shelf" maxlength="80" class="form-control form-control-solid" placeholder="Contoh: Tingkat 2">
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer pt-0 border-0">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary">Simpan lokasi</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endcan
    @endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const placementModal = document.getElementById('modal-placement');
        if (!placementModal) return;

        placementModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;

            const productId = button.getAttribute('data-product-id');
            const productName = button.getAttribute('data-product-name');
            const productSku = button.getAttribute('data-product-sku');
            const area = button.getAttribute('data-area') || '';
            const rack = button.getAttribute('data-rack') || '';
            const shelf = button.getAttribute('data-shelf') || '';
            const locationId = button.getAttribute('data-location-id') || '';

            const form = document.getElementById('form-placement');
            const updateUrl = "{{ route('retail.storefront.placement.update', ':id') }}";
            form.action = updateUrl.replace(':id', productId);

            document.getElementById('modal-product-name').textContent = productName;
            document.getElementById('modal-product-sku').textContent = productSku;
            document.getElementById('modal-input-area').value = area;
            document.getElementById('modal-input-rack').value = rack;
            document.getElementById('modal-input-shelf').value = shelf;

            const locSelect = document.getElementById('modal-location-select');
            if (locSelect) {
                locSelect.value = locationId;
            }
        });

        const locationSelect = document.getElementById('modal-location-select');
        if (locationSelect) {
            locationSelect.addEventListener('change', function () {
                if (this.value) {
                    const opt = this.options[this.selectedIndex];
                    document.getElementById('modal-input-area').value = opt.dataset.area || '';
                    document.getElementById('modal-input-rack').value = opt.dataset.rack || '';
                    document.getElementById('modal-input-shelf').value = opt.dataset.shelf || '';
                }
            });
        }
    });
</script>
@endpush
