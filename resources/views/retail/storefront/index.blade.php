@extends('layouts.metronic.app')

@section('title', 'Etalase Produk Toko')

@section('page_guide')
    <x-metronic.page-guide id="retail-storefront" title="Panduan Etalase Produk Toko">
        <x-slot:function>Menunjukkan produk yang tersedia di toko penugasan beserta harga jual, stok reguler, stok darurat, dan lokasi pajangnya.</x-slot:function>
        <x-slot:workflow>Cari produk atau pilih kategori. Jika melayani lebih dari satu toko, pilih toko yang sedang ditangani.</x-slot:workflow>
        <x-slot:parts>Area, rak, dan tingkat diisi kepala toko melalui tombol Atur Lokasi pada kartu produk.</x-slot:parts>
        <x-slot:impacts>Perubahan lokasi pajang tidak memindahkan stok fisik atau mengubah harga jual.</x-slot:impacts>
        <x-slot:operation>Harga mengikuti harga POS toko. Konfirmasi harga akhir dan ketersediaan sebelum transaksi di kasir.</x-slot:operation>
        <x-slot:warnings>Centang Tampilkan produk kosong untuk melihat barang tanpa stok. POS memakai stok reguler terlebih dahulu, lalu stok darurat.</x-slot:warnings>
        <x-slot:example>Cari SKU, lihat rak produk, lalu arahkan pelanggan ke area yang tertera.</x-slot:example>
    </x-metronic.page-guide>
@endsection

@push('styles')
<style>
    .storefront-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:1.25rem; }
    .storefront-card { border:1px solid var(--bs-gray-200); border-radius:1rem; overflow:hidden; background:var(--bs-body-bg); height:100%; }
    .storefront-card.out-of-stock { border:2px solid #dc3545; box-shadow: 0 0 8px rgba(220, 53, 69, 0.3); }
    .storefront-photo { height:190px; background:var(--bs-gray-100); display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .storefront-photo img { width:100%; height:100%; object-fit:contain; }
    .storefront-photo i { font-size:3rem; color:var(--bs-gray-500); }
    .storefront-card-body { padding:1.25rem; }
    .storefront-name { min-height:2.7em; line-height:1.35; }
    .storefront-location { border-top:1px solid var(--bs-gray-200); margin-top:1rem; padding-top:1rem; }
    .storefront-card details summary { cursor:pointer; color:var(--bs-primary); font-weight:600; }
    .storefront-card details[open] summary { margin-bottom:.75rem; }
    .out-of-stock-badge { display: inline-block; background: #dc3545; color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 3px; margin-left: 8px; }
    @media (max-width:575px) { .storefront-grid { grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; } .storefront-photo { height:135px; } .storefront-card-body { padding:.9rem; } }
</style>
@endpush

@section('content')
    <x-metronic.page-title title="Etalase Produk Toko" description="Bantu pelanggan menemukan produk, harga, dan posisi pajang di toko Anda." />

    @if (!$branch)
        <x-metronic.empty-state title="Belum ada toko yang ditugaskan" description="Minta admin menambahkan cabang/toko aktif pada lokasi kerja akun Anda." />
    @else
        <div class="card mb-6">
            <div class="card-body">
                <form method="GET" action="{{ route('retail.storefront.index') }}" class="row g-3 align-items-end">
                    @if ($branches->count() > 1)
                        <div class="col-lg-3 col-md-6">
                            <label for="storefront-branch" class="form-label fw-semibold">Toko</label>
                            <select id="storefront-branch" name="branch_id" class="form-select form-select-solid">
                                @foreach ($branches as $availableBranch)
                                    <option value="{{ $availableBranch->id }}" @selected($branch->id === $availableBranch->id)>{{ $availableBranch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                    @endif
                    <div class="col-lg-4 col-md-6">
                        <label for="storefront-search" class="form-label fw-semibold">Cari produk</label>
                        <input id="storefront-search" name="q" value="{{ $search }}" class="form-control form-control-solid" placeholder="Nama, SKU, atau model">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label for="storefront-category" class="form-label fw-semibold">Kategori</label>
                        <select id="storefront-category" name="category_id" class="form-select form-select-solid">
                            <option value="">Semua kategori</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label for="storefront-area" class="form-label fw-semibold">Area pajang</label>
                        <select id="storefront-area" name="area" class="form-select form-select-solid">
                            <option value="">Semua area</option>
                            @foreach ($areas as $availableArea)
                                <option value="{{ $availableArea }}" @selected($area === $availableArea)>{{ $availableArea }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6"><label class="form-check"><input class="form-check-input" type="checkbox" name="show_empty" value="1" @checked($showEmpty)><span class="form-check-label">Termasuk stok kosong</span></label></div>
                    <div class="col-lg-1 col-md-6"><button type="submit" class="btn btn-primary w-100">Cari</button></div>
                </form>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-5">
            <div>
                <div class="fw-bold fs-3">{{ $branch->name }}</div>
                <div class="text-muted">{{ $products->total() }} produk tersedia untuk dilayani</div>
            </div>
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
                        <div class="storefront-photo">
                            @if ($product->main_image_url)
                                <img src="{{ $product->main_image_url }}" alt="{{ $product->name }}" loading="lazy">
                            @else
                                <i class="ki-outline ki-package" aria-hidden="true"></i>
                            @endif
                        </div>
                        <div class="storefront-card-body">
                            <div class="text-muted fs-8 mb-2">{{ $product->category?->name ?? 'Tanpa kategori' }} · {{ $product->sku }}</div>
                            <h2 class="storefront-name fs-5 fw-bold mb-2">{{ $product->name }}</h2>
                            @if ($product->brand)<div class="text-muted fs-8 mb-2">{{ $product->brand->name }}</div>@endif
                            <div class="fw-bold fs-3 text-primary">{{ \App\Support\CurrencyFormatter::rupiah($cards[$product->id]['price']) }}</div>
                            <div class="text-muted fs-8">Total siap jual: {{ qty($cards[$product->id]['stock']) }} {{ $product->baseUnit?->symbol ?? $product->baseUnit?->name }}</div>
                            @if ($isOutOfStock)
                                <span class="out-of-stock-badge">STOK KOSONG</span>
                            @endif
                            <div class="text-muted fs-9">Reguler {{ qty($cards[$product->id]['regular_stock']) }} · Darurat {{ qty($cards[$product->id]['emergency_stock']) }}</div>
                            @can('emergency_purchases.create')<a class="btn btn-sm btn-light-warning mt-3 w-100" href="{{ route('retail.emergency.create', ['branch_id' => $branch->id, 'product_id' => $product->id]) }}">Pembelian darurat / restok</a>@endcan
                            <div class="storefront-location">
                                <div class="text-muted fs-8 fw-semibold mb-1">LOKASI PAJANG</div>
                                @if ($placement)
                                    <div class="fw-bold">{{ $placement->area }}</div>
                                    <div class="text-muted fs-8">{{ collect([$placement->rack, $placement->shelf])->filter()->implode(' · ') ?: 'Detail rak belum diisi' }}</div>
                                @else
                                    <div class="text-warning fw-semibold">Belum ditata</div>
                                @endif
                            </div>
                            @can('retail.catalog.manage')
                                <details class="mt-4">
                                    <summary>Atur lokasi pajang</summary>
                                    <form method="POST" action="{{ route('retail.storefront.placement.update', $product) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                        <label class="form-label fs-8" for="area-{{ $product->id }}">Area</label>
                                        <input id="area-{{ $product->id }}" name="area" value="{{ $placement?->area }}" required maxlength="80" class="form-control form-control-sm mb-2">
                                        <label class="form-label fs-8" for="rack-{{ $product->id }}">Rak</label>
                                        <input id="rack-{{ $product->id }}" name="rack" value="{{ $placement?->rack }}" maxlength="80" class="form-control form-control-sm mb-2">
                                        <label class="form-label fs-8" for="shelf-{{ $product->id }}">Tingkat rak</label>
                                        <input id="shelf-{{ $product->id }}" name="shelf" value="{{ $placement?->shelf }}" maxlength="80" class="form-control form-control-sm mb-3">
                                        <button type="submit" class="btn btn-sm btn-primary w-100">Simpan lokasi</button>
                                    </form>
                                </details>
                            @endcan
                        </div>
                    </article>
                @endforeach
            </div>
            {{ $products->links() }}
        @endif
    @endif
@endsection
