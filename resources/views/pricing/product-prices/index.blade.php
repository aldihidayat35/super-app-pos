@extends('layouts.metronic.app')

@section('title', 'Harga Produk - ' . config('app.name'))
@section('page_title', 'Harga Produk')

@section('page_guide')
    <x-metronic.page-guide
        id="product-prices"
        title="Panduan Modul Setting Harga Jual Produk"
        description="Atur harga jual minimum produk per lokasi secara cepat tanpa approval. Dilengkapi kalkulator margin real-time dan proteksi anti-rugi.">
        <x-slot:function>
            Mengatur dan memperbarui harga jual produk aktif langsung per lokasi penugasan. Harga jual minimum disinkronkan ke seluruh channel kasir dan penjualan.
        </x-slot:function>
        <x-slot:workflow>
            1. Pilih lokasi penugasan (Super Admin bebas memilih; pengguna lain terkunci pada lokasi kerja masing-masing).<br>
            2. Periksa harga aktif sebelumnya dan HPP acuan pada daftar produk.<br>
            3. Masukkan nominal harga baru pada kolom Update Harga (margin/laba terhitung otomatis).<br>
            4. Klik tombol "Update" pada baris produk yang ingin disimpan, atau klik "Simpan Semua Perubahan" untuk menyimpan sekaligus.
        </x-slot:workflow>
        <x-slot:parts>
            - <strong>Pilihan Lokasi</strong>: Membatasi harga berlaku pada lokasi kerja aktif.<br>
            - <strong>Harga Sebelumnya</strong>: Nominal harga minimum yang aktif berlaku saat ini.<br>
            - <strong>HPP Acuan</strong>: Nilai modal produk untuk panduan batas bawah anti-rugi.<br>
            - <strong>Kolom Update Harga</strong>: Input nominal harga baru dengan kalkulator margin langsung.<br>
            - <strong>Simpan Masal</strong>: Tombol untuk menyimpan seluruh baris yang diubah secara bersamaan.
        </x-slot:parts>
        <x-slot:impacts>
            Harga langsung aktif di kasir POS dan sistem penjualan tanpa menunggu proses approval. Setiap perubahan otomatis tercatat di Histori Harga.
        </x-slot:impacts>
        <x-slot:operation>
            Ketik nominal harga baru, lalu klik tombol Update per baris atau klik tombol Simpan Semua Perubahan di atas tabel.
        </x-slot:operation>
        <x-slot:warnings>
            Pastikan harga jual berada di atas HPP modal. Sistem akan memberi peringatan jika harga diatur di bawah modal.
        </x-slot:warnings>
        <x-slot:example>
            Produk Sabun memiliki HPP Rp8.000 dan Harga Sebelumnya Rp10.000. Pengguna mengetik Rp11.000 pada kolom update harga (terlihat estimasi laba Rp3.000) lalu menekan tombol Update.
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <a href="{{ route('pricing.history.index') }}" class="btn btn-sm btn-light-info me-2">
        <i class="ki-outline ki-time fs-5 me-1"></i> Riwayat Histori Harga
    </a>
    <button type="button" class="btn btn-sm btn-light-primary me-2" data-bs-toggle="collapse" data-bs-target="#bulkPriceCollapse" aria-expanded="false" aria-controls="bulkPriceCollapse">
        <i class="ki-outline ki-element-plus fs-5 me-1"></i> Atur Banyak Produk Sekaligus
    </button>
    <a href="{{ route('pricing.product-prices.export', request()->query()) }}" class="btn btn-sm btn-light-success">
        <i class="ki-outline ki-file-down fs-5 me-1"></i> Export CSV
    </a>
@endsection

@push('styles')
<style>
    .table-pricing th, .table-pricing td { vertical-align: middle; }
    .price-input-box {
        font-weight: 700;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .price-input-box.is-changed {
        border-color: var(--bs-primary);
        background-color: rgba(var(--bs-primary-rgb), 0.04);
        box-shadow: 0 0 0 1px rgba(var(--bs-primary-rgb), 0.2);
    }
    .row-saved-flash {
        animation: rowFlash 1.5s ease-out;
    }
    @keyframes rowFlash {
        0% { background-color: rgba(40, 167, 69, 0.25); }
        100% { background-color: transparent; }
    }

    /* Product Picker Styling for Drawer */
    :root { --pp-primary-rgb: var(--bs-primary-rgb, 13, 110, 253); }
    .pp-picker-container { position: relative; width: 100%; z-index: 1050; }
    .pp-trigger {
        position: relative; width: 100%; min-height: 44px;
        background: var(--bs-gray-100); border: 1px solid var(--bs-gray-300);
        border-radius: .475rem; padding: .35rem .75rem;
        display: flex; flex-wrap: wrap; align-items: center; gap: .3rem;
        cursor: text; transition: border-color .2s, box-shadow .2s, background .15s;
    }
    .pp-trigger:hover { border-color: var(--bs-gray-400); background: #fff; }
    .pp-arrow {
        position: absolute; right: .75rem; top: 50%; transform: translateY(-50%);
        color: var(--bs-gray-500); pointer-events: none;
    }
    .pp-chip {
        display: inline-flex; align-items: center; gap: .25rem;
        background: rgba(var(--bs-primary-rgb), .12); color: var(--bs-primary);
        border: 1px solid rgba(var(--bs-primary-rgb), .25);
        border-radius: .375rem; padding: .2rem .4rem .2rem .6rem;
        font-size: .8rem; font-weight: 500;
    }
    .pp-chip-remove { background: none; border: none; color: inherit; cursor: pointer; font-size: .9rem; opacity: .6; }
    .pp-native-select { position: absolute; clip: rect(0,0,0,0); pointer-events: none; }
    .pp-placeholder { color: var(--bs-gray-400); font-size: .88rem; pointer-events: none; }
    .pp-dropdown {
        position: absolute; top: calc(100% + 6px); left: 0; right: 0;
        background: #fff; border: 1px solid var(--bs-gray-300);
        border-radius: .5rem; box-shadow: 0 .5rem 1rem rgba(0,0,0,.12);
        z-index: 1060; display: none; overflow: hidden;
    }
    .pp-dropdown.show { display: block; }
    .pp-dropdown-header { padding: .6rem .75rem; border-bottom: 1px solid var(--bs-gray-200); position: sticky; top: 0; background: #fff; }
    .pp-dropdown-search { width: 100%; border: 1px solid var(--bs-gray-300); border-radius: .4rem; padding: .5rem .75rem; font-size: .88rem; outline: none; }
    .pp-list-wrap { max-height: 280px; overflow-y: auto; }
    .pp-item { display: flex; align-items: center; gap: .6rem; padding: .5rem .75rem; cursor: pointer; border-bottom: 1px solid var(--bs-gray-100); }
    .pp-item:hover { background: var(--bs-gray-50); }
    .pp-item.selected { background: rgba(var(--bs-primary-rgb), .06); }
    .pp-item input[type="checkbox"] { accent-color: var(--bs-primary); width: 16px; height: 16px; }
    .pp-item-info { flex: 1; min-width: 0; }
    .pp-item-sku { font-size: .72rem; color: var(--bs-gray-500); font-family: monospace; }
    .pp-item-name { font-size: .85rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pp-footer { padding: .5rem .75rem; border-top: 1px solid var(--bs-gray-200); display: flex; align-items: center; justify-content: space-between; font-size: .75rem; color: var(--bs-gray-500); background: var(--bs-gray-50); }
    .pp-footer-btn { background: none; border: 1px solid var(--bs-gray-300); border-radius: .35rem; padding: .2rem .5rem; font-size: .72rem; cursor: pointer; }
    .pp-counter { display: inline-flex; align-items: center; gap: .4rem; border: 1px solid var(--bs-gray-200); border-radius: .5rem; background: var(--bs-gray-100); padding: .25rem .6rem; font-size: .75rem; color: var(--bs-gray-500); margin-top: .4rem; }
    .pp-counter.has-items { background: rgba(var(--bs-primary-rgb), .08); color: var(--bs-primary); border-color: rgba(var(--bs-primary-rgb), .2); }
    .pp-counter-dot { background: var(--bs-gray-300); border-radius: 1rem; color: #fff; font-size: .68rem; font-weight: 700; min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; }
    .pp-counter.has-items .pp-counter-dot { background: var(--bs-primary); }
</style>
@endpush

@section('content')
    {{-- Card Menu Navigasi Internal Modul Harga & Margin --}}
    <div class="row row-cols-1 row-cols-sm-2 g-4 mb-6">
        <div class="col">
            <a href="{{ route('pricing.product-prices.index') }}" class="card card-flush h-100 border border-primary border-opacity-50 bg-light-primary text-decoration-none shadow-xs">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="symbol symbol-40px symbol-circle bg-primary me-3 flex-shrink-0">
                        <span class="symbol-label"><i class="ki-outline ki-price-tag fs-2 text-white"></i></span>
                    </div>
                    <div class="overflow-hidden">
                        <div class="fs-7 fw-bold text-gray-900 text-truncate">Harga Produk</div>
                        <div class="text-muted fs-8 text-truncate">Kelola harga jual &amp; batas minimum</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="{{ route('pricing.history.index') }}" class="card card-flush h-100 hover-elevate-up border border-gray-200 bg-body text-decoration-none shadow-xs">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="symbol symbol-40px symbol-circle bg-light-info me-3 flex-shrink-0">
                        <span class="symbol-label"><i class="ki-outline ki-time fs-2 text-info"></i></span>
                    </div>
                    <div class="overflow-hidden">
                        <div class="fs-7 fw-bold text-gray-900 text-truncate">Histori Perubahan Harga</div>
                        <div class="text-muted fs-8 text-truncate">Riwayat dan log perubahan harga produk</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- 0. Collapsible Bulk Assignment Drawer (Preserved for compatibility & advanced bulk workflows) -->
    <div class="collapse mb-6" id="bulkPriceCollapse">
        <x-metronic.card title="Input Harga Masal (Banyak Produk Sekaligus)">
            <form method="POST" action="{{ route('pricing.product-prices.store') }}" class="row g-3 align-items-start" id="product-price-form">
                @csrf
                <div class="col-md-4">
                    <div class="fv-row mb-4">
                        <label for="product_ids" class="form-label required mb-2">Pilih Produk</label>
                        <div class="pp-picker-container w-100" data-product-picker="true">
                            <select id="product_ids" name="product_ids[]" class="pp-native-select" data-control="native" multiple required>
                                @foreach($allProducts as $p)
                                    <option value="{{ $p->id }}" data-sku="{{ $p->sku }}" data-name="{{ $p->name }}" data-category="{{ $p->category?->name ?? '' }}">{{ $p->sku }} — {{ $p->name }}</option>
                                @endforeach
                            </select>
                            <div class="pp-trigger">
                                <span class="pp-placeholder">Belum ada produk dipilih</span>
                                <span class="pp-arrow"><i class="ki-outline ki-square-down fs-6"></i></span>
                            </div>
                            <div class="pp-dropdown">
                                <div class="pp-dropdown-header"><input type="text" class="pp-dropdown-search" placeholder="Cari berdasarkan SKU atau nama..."></div>
                                <div class="pp-list-wrap"><div class="pp-list-inner"></div></div>
                                <div class="pp-footer"></div>
                            </div>
                            <div class="pp-counter"><span class="pp-counter-dot">0</span><span>Belum ada produk dipilih</span></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <x-metronic.form-group name="channel" label="Channel" required>
                        <select name="channel" class="form-select">
                            <option value="retail">Retail</option>
                            <option value="b2b">B2B</option>
                            <option value="pos">POS</option>
                            <option value="all">Semua</option>
                        </select>
                    </x-metronic.form-group>
                </div>
                <div class="col-md-2">
                    <x-metronic.form-group name="price_ring" label="Ring" required>
                        <select name="price_ring" class="form-select">
                            <option value="Ring Retail">Ring Retail</option>
                            <option value="Ring B2B">Ring B2B</option>
                            <option value="Ring POS">Ring POS</option>
                            <option value="ring_1">Ring 1</option>
                        </select>
                    </x-metronic.form-group>
                </div>
                <div class="col-md-4">
                    <x-metronic.form-group name="branch_id" label="Cabang" help="Kosongkan untuk memberlakukan harga pada semua cabang.">
                        <select name="branch_id" class="form-select" data-control="select2" data-placeholder="Pilih cabang" data-allow-clear="true" @disabled(! $isSuperAdmin)>
                            <option value=""></option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->code }} — {{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>
                </div>
                <div class="col-md-4">
                    <x-metronic.form-group name="recommended_price" label="Harga Jual" required>
                        <input type="number" step="any" min="0" name="recommended_price" class="form-control" placeholder="0" required>
                    </x-metronic.form-group>
                </div>
                <div class="col-md-4">
                    <x-metronic.form-group name="starts_at" label="Mulai Berlaku">
                        <input type="date" name="starts_at" value="{{ now()->toDateString() }}" class="form-control">
                    </x-metronic.form-group>
                </div>
                <div class="col-md-4">
                    <x-metronic.form-group name="notes" label="Catatan">
                        <input type="text" name="notes" class="form-control" placeholder="Update harga masal">
                    </x-metronic.form-group>
                </div>
                <div class="col-md-12 text-end">
                    <button type="submit" class="btn btn-primary" id="save-product-prices" disabled>
                        <i class="ki-outline ki-check fs-5 me-1"></i> Simpan Harga Masal
                    </button>
                </div>
            </form>
        </x-metronic.card>
    </div>

    <!-- 1. Filter Lokasi & Pencarian Produk -->
    <x-metronic.card class="mb-6">
        <form method="GET" action="{{ route('pricing.product-prices.index') }}" class="row g-3 align-items-center" id="filter-form">
            <!-- Pilihan Lokasi -->
            <div class="col-md-4">
                <label class="form-label fw-bold text-gray-700 fs-7 mb-1">
                    <i class="ki-outline ki-geolocation fs-5 text-primary me-1"></i> Lokasi Penugasan / Cabang:
                </label>
                @if($isSuperAdmin)
                    <select name="work_location_id" class="form-select form-select-solid" onchange="this.form.submit()">
                        <option value="">Semua Lokasi / Global</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" @selected($selectedLocationId == $loc->id)>
                                [{{ $loc->typeLabel() }}] {{ $loc->name }} ({{ $loc->code }})
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text fs-8 text-primary">Super Admin bebas memilih lokasi secara manual.</div>
                @elseif($locations->count() > 1)
                    <select name="work_location_id" class="form-select form-select-solid" onchange="this.form.submit()">
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" @selected($selectedLocationId == $loc->id)>
                                [{{ $loc->typeLabel() }}] {{ $loc->name }} ({{ $loc->code }})
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text fs-8 text-muted">Terkunci sesuai lokasi penugasan akun Anda ({{ $locations->count() }} lokasi).</div>
                @elseif($locations->count() === 1)
                    <div class="d-flex align-items-center bg-light-primary border border-primary border-dashed rounded px-3 py-2">
                        <i class="ki-outline ki-lock fs-4 text-primary me-2"></i>
                        <div>
                            <div class="fw-bold text-primary fs-7">
                                {{ $currentLocation ? '[' . $currentLocation->typeLabel() . '] ' . $currentLocation->name : 'Lokasi Akun' }}
                            </div>
                            <div class="text-muted fs-9">Terkunci sesuai lokasi penugasan akun Anda.</div>
                        </div>
                    </div>
                    @if($selectedLocationId)
                        <input type="hidden" name="work_location_id" value="{{ $selectedLocationId }}">
                    @endif
                @else
                    <div class="alert alert-warning py-2 px-3 fs-8 mb-0">Akun Anda belum memiliki penugasan lokasi kerja.</div>
                @endif
            </div>

            <!-- Cari Produk -->
            <div class="col-md-4">
                <label class="form-label fw-bold text-gray-700 fs-7 mb-1">Cari Produk (Nama / SKU):</label>
                <div class="position-relative">
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-solid ps-10" placeholder="Ketik nama produk atau SKU...">
                    <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-gray-500">
                        <i class="ki-outline ki-magnifier fs-5"></i>
                    </span>
                </div>
            </div>

            <!-- Kategori -->
            <div class="col-md-2">
                <label class="form-label fw-bold text-gray-700 fs-7 mb-1">Kategori:</label>
                <select name="category_id" class="form-select form-select-solid" onchange="this.form.submit()">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(($filters['category_id'] ?? '') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="col-md-2 d-flex gap-2 pt-4">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="ki-outline ki-filter fs-6 me-1"></i> Filter
                </button>
                <a href="{{ route('pricing.product-prices.index', $selectedLocationId ? ['work_location_id' => $selectedLocationId] : []) }}" class="btn btn-light btn-sm" title="Reset Pencarian">
                    <i class="ki-outline ki-arrows-circle fs-6"></i>
                </a>
            </div>
        </form>
    </x-metronic.card>

    <!-- 2. Tabel Harga Produk Sederhana -->
    <x-metronic.card>
        <!-- Header Toolbar Tabel: Info & Tombol Simpan Masal -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 pb-3 border-bottom border-gray-200">
            <div>
                <h3 class="card-title fw-bolder text-gray-800 fs-4 mb-1">Daftar Harga Produk & Jual Minimum</h3>
                <div class="text-muted fs-7">
                    Menampilkan <strong>{{ $products->total() }}</strong> produk aktif.
                    @if($currentLocation)
                        Lokasi: <span class="badge badge-light-primary fw-bold">{{ $currentLocation->name }}</span>
                    @else
                        Lokasi: <span class="badge badge-light-secondary fw-bold">Semua Lokasi (Global)</span>
                    @endif
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <span class="badge badge-light-warning fw-bold fs-7 d-none" id="pending-changes-badge">
                    <i class="ki-outline ki-information fs-6 me-1 text-warning"></i>
                    <span id="pending-count">0</span> perubahan belum disimpan
                </span>

                <button type="button" class="btn btn-sm btn-primary" id="btn-batch-save" disabled>
                    <span class="indicator-label">
                        <i class="ki-outline ki-check-circle fs-5 me-1"></i> Simpan Semua Perubahan (<span id="batch-count-btn">0</span>)
                    </span>
                    <span class="indicator-progress">
                        Menyimpan... <span class="spinner-border spinner-border-sm ms-2"></span>
                    </span>
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-300 align-middle gs-4 gy-4 table-pricing">
                <thead>
                    <tr class="fw-bold text-gray-600 text-uppercase fs-7 bg-light border-bottom border-gray-200">
                        <th class="ps-4 min-w-280px">Informasi Produk</th>
                        <th class="min-w-180px text-center">Harga Sebelumnya & HPP</th>
                        <th class="min-w-220px">Kolom Update Harga</th>
                        <th class="text-end pe-4 min-w-180px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($products as $product)
                    @php
                        $row = $itemsData[$product->id] ?? null;
                        $currentPrice = $row['current_price'] ?? null;
                        $effectiveHpp = $row['effective_hpp'] ?? '0.00';
                        $filledCount = (int) ($row['filled_rings_count'] ?? 0);
                    @endphp
                    <tr id="product-row-{{ $product->id }}" class="product-price-row" data-product-id="{{ $product->id }}">
                        <!-- 1. Kolom Informasi Produk -->
                        <td class="ps-4">
                            <div class="d-flex align-items-start">
                                <div class="symbol symbol-45px me-3 flex-shrink-0 mt-1">
                                    <x-metronic.image-preview url="{{ $product->main_image_url }}" title="{{ $product->name }}" />
                                </div>
                                <div class="d-flex flex-column min-w-0">
                                    <a href="{{ route('admin.products.show', $product) }}" class="text-gray-900 fw-bolder text-hover-primary fs-6 mb-1 text-truncate" title="{{ $product->name }}">
                                        {{ $product->name }}
                                    </a>
                                    <div class="d-flex align-items-center gap-1 flex-wrap fs-8 text-muted mb-1">
                                        <span class="badge badge-light-dark font-monospace py-0 px-2 fs-9">{{ $product->sku }}</span>
                                        @if($product->category)
                                            <span class="badge badge-light-secondary py-0 px-2 fs-9">{{ $product->category->name }}</span>
                                        @endif
                                        @if($product->baseUnit)
                                            <span class="text-gray-500 fs-9">Satuan: <strong>{{ $product->baseUnit->symbol }}</strong></span>
                                        @endif
                                    </div>

                                    <!-- Informasi Rincian HPP (termasuk HPP Bertingkat & HPP Dirahasiakan) -->
                                    @if(! ($row['hpp']['can_view'] ?? false))
                                        <div class="mt-1">
                                            <span class="badge badge-light-secondary fs-9"><i class="ki-outline ki-lock fs-9 me-1"></i> HPP Dirahasiakan</span>
                                        </div>
                                    @elseif(!empty($row['hpp']['has_layers']))
                                        <div class="mt-1 bg-light-warning rounded p-2 border border-warning border-dashed">
                                            <div class="d-flex align-items-center gap-1 text-warning-emphasis fw-bold fs-9 mb-1">
                                                <i class="ki-outline ki-layers fs-8 text-warning me-1"></i> HPP Bertingkat ({{ count($row['hpp']['tiers']) }} Lapisan):
                                            </div>
                                            <div class="d-flex flex-column gap-1">
                                                @foreach($row['hpp']['tiers'] as $tier)
                                                    <div class="fs-8 text-gray-800 d-flex align-items-center gap-1">
                                                        <span class="badge badge-sm badge-secondary py-0 px-1 text-gray-700">Tingkat {{ $loop->iteration }}</span>
                                                        <strong class="text-gray-900">{{ $tier['cost_formatted'] }}</strong>
                                                        @if($tier['total_qty'])
                                                            <span class="text-muted fs-9">({{ qty($tier['total_qty']) }} {{ $tier['unit'] }})</span>
                                                        @endif
                                                        @if(!empty($tier['batch_nos']))
                                                            <span class="text-gray-400 fs-9" title="Batch">[{{ $tier['batch_nos'] }}]</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        <div class="mt-1 fs-8 text-gray-700 d-flex align-items-center gap-1">
                                            <span class="text-muted">HPP:</span>
                                            <span class="fw-bold text-gray-900">{{ $row['hpp']['tiers'][0]['cost_formatted'] ?? CurrencyFormatter::rupiah($product->cost_price ?? 0) }}</span>
                                            @if(isset($row['hpp']['tiers'][0]['total_qty']) && $row['hpp']['tiers'][0]['total_qty'] !== null)
                                                <span class="text-muted fs-9">({{ qty($row['hpp']['tiers'][0]['total_qty']) }} {{ $row['hpp']['tiers'][0]['unit'] }})</span>
                                            @elseif(!empty($row['hpp']['is_master']))
                                                <span class="badge badge-light-secondary py-0 px-1 fs-9 ms-1" title="HPP dari Master Produk">Master</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <!-- 2. Kolom Harga Sebelumnya & HPP Acuan -->
                        <td class="text-center">
                            <div class="d-flex flex-column align-items-center justify-content-center">
                                @if($currentPrice !== null)
                                    <div class="fw-bolder fs-5 text-gray-900 previous-price-label" id="prev-price-{{ $product->id }}">
                                        Rp {{ number_format((float) $currentPrice, 0, ',', '.') }}
                                    </div>
                                    <span class="badge badge-light-success fs-9 mt-1"><i class="ki-outline ki-check-circle fs-9 me-1"></i>Harga Aktif</span>
                                @else
                                    <span class="badge badge-light-secondary fs-8 previous-price-label" id="prev-price-{{ $product->id }}">
                                        <i class="ki-outline ki-minus fs-8 me-1"></i>Belum Diset
                                    </span>
                                @endif

                                @if(($row['hpp']['can_view'] ?? false) && (float) $effectiveHpp > 0)
                                    <div class="mt-2 text-muted fs-9">
                                        Acuan Modal: <span class="fw-bold text-gray-800">Rp {{ number_format((float) $effectiveHpp, 0, ',', '.') }}</span>
                                    </div>
                                @endif
                            </div>
                        </td>

                        <!-- 3. Kolom Update Harga Baru -->
                        <td>
                            <div class="d-flex flex-column">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text fw-bold text-primary bg-light-primary">Rp</span>
                                    <input type="number" step="any" min="0"
                                        class="form-control form-control-sm text-end fs-6 price-input-box row-price-input"
                                        data-product-id="{{ $product->id }}"
                                        data-current-price="{{ $currentPrice ?? '' }}"
                                        data-hpp="{{ $effectiveHpp }}"
                                        placeholder="{{ $currentPrice ? (float) $currentPrice : 'Masukkan harga baru' }}"
                                        aria-label="Harga Jual Baru">
                                </div>
                                <div class="margin-indicator mt-1 fs-9 text-muted" id="margin-indicator-{{ $product->id }}">
                                    <span class="text-gray-400">Ketik harga baru untuk estimasi margin</span>
                                </div>
                            </div>
                        </td>

                        <!-- 4. Kolom Aksi -->
                        <td class="text-end pe-4">
                            <div class="d-flex justify-content-end align-items-center gap-1">
                                <button type="button" class="btn btn-sm btn-primary py-1 px-3 btn-save-row" data-product-id="{{ $product->id }}" title="Simpan perubahan harga untuk produk ini">
                                    <span class="indicator-label"><i class="ki-outline ki-check fs-6 me-1"></i> Update</span>
                                    <span class="indicator-progress"><span class="spinner-border spinner-border-sm"></span></span>
                                </button>
                                <button type="button" class="btn btn-sm btn-icon btn-light-danger btn-reset-row d-none" data-product-id="{{ $product->id }}" title="Batalkan perubahan">
                                    <i class="ki-outline ki-cross fs-6"></i>
                                </button>
                                @if(!empty($row['price_model']))
                                    <a href="{{ route('pricing.product-prices.edit', $row['price_model']) }}" class="btn btn-sm btn-light-info py-1 px-2" title="Kelola rincian harga"><i class="ki-outline ki-setting-2 fs-6 me-1"></i> Kelola</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-8">
                            <x-metronic.empty-state
                                title="Tidak ada produk ditemukan"
                                description="Ubah kriteria pencarian atau pilih lokasi penugasan lain." />
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $products->links() }}
        </div>
    </x-metronic.card>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
        const saveItemUrl = '{{ route("pricing.product-prices.save-item") }}';
        const batchSaveUrl = '{{ route("pricing.product-prices.batch-save") }}';
        const selectedLocationId = @json($selectedLocationId);

        const pendingChanges = new Map();

        const pendingBadge = document.getElementById('pending-changes-badge');
        const pendingCountEl = document.getElementById('pending-count');
        const btnBatchSave = document.getElementById('btn-batch-save');
        const batchCountBtn = document.getElementById('batch-count-btn');

        function formatRupiah(amount) {
            return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(amount);
        }

        function updatePendingUI() {
            const count = pendingChanges.size;
            if (pendingCountEl) pendingCountEl.textContent = count;
            if (batchCountBtn) batchCountBtn.textContent = count;

            if (count > 0) {
                pendingBadge?.classList.remove('d-none');
                if (btnBatchSave) btnBatchSave.disabled = false;
            } else {
                pendingBadge?.classList.add('d-none');
                if (btnBatchSave) btnBatchSave.disabled = true;
            }
        }

        // 1. Real-time Margin Calculation & Pending State on Input (TANPA AUTO SAVE)
        document.querySelectorAll('.row-price-input').forEach(function (input) {
            const productId = input.dataset.productId;
            const hpp = parseFloat(input.dataset.hpp) || 0;
            const currentPrice = parseFloat(input.dataset.currentPrice) || null;
            const marginIndicator = document.getElementById('margin-indicator-' + productId);
            const row = document.getElementById('product-row-' + productId);
            const resetBtn = row?.querySelector('.btn-reset-row');

            input.addEventListener('input', function () {
                const rawVal = this.value.trim();
                const numVal = parseFloat(rawVal);

                if (rawVal === '' || isNaN(numVal) || numVal <= 0) {
                    this.classList.remove('is-changed');
                    resetBtn?.classList.add('d-none');
                    pendingChanges.delete(productId);
                    if (marginIndicator) {
                        marginIndicator.innerHTML = '<span class="text-gray-400">Ketik harga baru untuk estimasi margin</span>';
                    }
                    updatePendingUI();
                    return;
                }

                // Kalkulasi Laba / Rugi vs HPP
                if (hpp > 0) {
                    const margin = numVal - hpp;
                    const pct = (margin / hpp) * 100;
                    if (margin > 0) {
                        marginIndicator.innerHTML = `<span class="text-success fw-bold"><i class="ki-outline ki-arrow-up fs-9 text-success"></i> Laba: Rp ${formatRupiah(margin)} (+${pct.toFixed(1)}%)</span>`;
                    } else if (margin < 0) {
                        marginIndicator.innerHTML = `<span class="text-danger fw-bold"><i class="ki-outline ki-cross fs-9 text-danger"></i> Di bawah modal! Rugi Rp ${formatRupiah(Math.abs(margin))}</span>`;
                    } else {
                        marginIndicator.innerHTML = `<span class="text-warning fw-bold">Impas (Sesuai HPP)</span>`;
                    }
                } else {
                    marginIndicator.innerHTML = `<span class="text-primary fw-semibold">Harga baru: Rp ${formatRupiah(numVal)}</span>`;
                }

                // Check if changed vs current
                const isChanged = (currentPrice === null) || (Math.abs(numVal - currentPrice) >= 0.01);
                if (isChanged) {
                    this.classList.add('is-changed');
                    resetBtn?.classList.remove('d-none');
                    pendingChanges.set(productId, { price: numVal, row: row });
                } else {
                    this.classList.remove('is-changed');
                    resetBtn?.classList.add('d-none');
                    pendingChanges.delete(productId);
                }

                updatePendingUI();
            });

            // Enter key trigger single row save
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    row?.querySelector('.btn-save-row')?.click();
                }
            });
        });

        // 2. Tombol Reset per baris
        document.querySelectorAll('.btn-reset-row').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const productId = this.dataset.productId;
                const row = document.getElementById('product-row-' + productId);
                const input = row?.querySelector('.row-price-input');
                if (input) {
                    input.value = '';
                    input.classList.remove('is-changed');
                    this.classList.add('d-none');
                    const marginIndicator = document.getElementById('margin-indicator-' + productId);
                    if (marginIndicator) {
                        marginIndicator.innerHTML = '<span class="text-gray-400">Ketik harga baru untuk estimasi margin</span>';
                    }
                    pendingChanges.delete(productId);
                    updatePendingUI();
                }
            });
        });

        // 3. Tombol Update per baris (Manual Single Save)
        document.querySelectorAll('.btn-save-row').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const productId = this.dataset.productId;
                const row = document.getElementById('product-row-' + productId);
                const input = row?.querySelector('.row-price-input');
                if (!input || !row) return;

                const val = input.value.trim();
                if (val === '' || isNaN(parseFloat(val)) || parseFloat(val) <= 0) {
                    input.classList.add('is-invalid');
                    input.focus();
                    return;
                }

                btn.setAttribute('data-kt-indicator', 'on');
                btn.disabled = true;

                const payload = {
                    _token: csrfToken,
                    product_id: productId,
                    work_location_id: selectedLocationId,
                    price: val,
                    unlimited: true,
                };

                fetch(saveItemUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload)
                })
                .then(function (res) {
                    return res.json().then(function (data) {
                        return { ok: res.ok, status: res.status, data: data };
                    });
                })
                .then(function (result) {
                    btn.removeAttribute('data-kt-indicator');
                    btn.disabled = false;

                    if (result.ok && result.data.success) {
                        row.classList.remove('row-saved-flash');
                        void row.offsetWidth;
                        row.classList.add('row-saved-flash');

                        const prevPriceEl = document.getElementById('prev-price-' + productId);
                        if (prevPriceEl) {
                            prevPriceEl.textContent = 'Rp ' + formatRupiah(parseFloat(val));
                            prevPriceEl.className = 'fw-bolder fs-5 text-gray-900 previous-price-label';
                        }

                        input.dataset.currentPrice = val;
                        input.placeholder = formatRupiah(parseFloat(val));
                        input.value = '';
                        input.classList.remove('is-changed');
                        row.querySelector('.btn-reset-row')?.classList.add('d-none');
                        const marginIndicator = document.getElementById('margin-indicator-' + productId);
                        if (marginIndicator) {
                            marginIndicator.innerHTML = '<span class="text-success fs-9"><i class="ki-outline ki-check-circle fs-9 text-success"></i> Berhasil disimpan</span>';
                        }

                        pendingChanges.delete(productId);
                        updatePendingUI();
                    } else {
                        const errMsg = (result.data && result.data.message) ? result.data.message : 'Gagal menyimpan harga.';
                        const marginIndicator = document.getElementById('margin-indicator-' + productId);
                        if (marginIndicator) {
                            marginIndicator.innerHTML = '<span class="text-danger fs-9 fw-bold"><i class="ki-outline ki-cross-circle fs-9 text-danger"></i> ' + errMsg + '</span>';
                        }
                    }
                })
                .catch(function (err) {
                    btn.removeAttribute('data-kt-indicator');
                    btn.disabled = false;
                    console.error('Save error:', err);
                });
            });
        });

        // 4. Tombol Simpan Masal (Batch Save)
        if (btnBatchSave) {
            btnBatchSave.addEventListener('click', function () {
                if (pendingChanges.size === 0) return;

                const items = [];
                pendingChanges.forEach(function (data, prodId) {
                    items.push({
                        product_id: parseInt(prodId, 10),
                        price: data.price,
                    });
                });

                btnBatchSave.setAttribute('data-kt-indicator', 'on');
                btnBatchSave.disabled = true;

                const payload = {
                    _token: csrfToken,
                    work_location_id: selectedLocationId,
                    items: items,
                };

                fetch(batchSaveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload)
                })
                .then(function (res) {
                    return res.json().then(function (data) {
                        return { ok: res.ok, status: res.status, data: data };
                    });
                })
                .then(function (result) {
                    btnBatchSave.removeAttribute('data-kt-indicator');
                    btnBatchSave.disabled = false;

                    if (result.ok && result.data.success) {
                        items.forEach(function (item) {
                            const pId = item.product_id.toString();
                            const pData = pendingChanges.get(pId);
                            const row = pData?.row || document.getElementById('product-row-' + pId);
                            if (row) {
                                row.classList.remove('row-saved-flash');
                                void row.offsetWidth;
                                row.classList.add('row-saved-flash');

                                const prevPriceEl = document.getElementById('prev-price-' + pId);
                                if (prevPriceEl) {
                                    prevPriceEl.textContent = 'Rp ' + formatRupiah(item.price);
                                    prevPriceEl.className = 'fw-bolder fs-5 text-gray-900 previous-price-label';
                                }

                                const input = row.querySelector('.row-price-input');
                                if (input) {
                                    input.dataset.currentPrice = item.price;
                                    input.placeholder = formatRupiah(item.price);
                                    input.value = '';
                                    input.classList.remove('is-changed');
                                }

                                row.querySelector('.btn-reset-row')?.classList.add('d-none');
                                const marginIndicator = document.getElementById('margin-indicator-' + pId);
                                if (marginIndicator) {
                                    marginIndicator.innerHTML = '<span class="text-success fs-9"><i class="ki-outline ki-check-circle fs-9 text-success"></i> Tersimpan</span>';
                                }
                            }
                        });

                        pendingChanges.clear();
                        updatePendingUI();
                    } else {
                        const errMsg = (result.data && result.data.message) ? result.data.message : 'Gagal menyimpan harga masal.';
                        console.error('Batch save error:', errMsg);
                    }
                })
                .catch(function (err) {
                    btnBatchSave.removeAttribute('data-kt-indicator');
                    btnBatchSave.disabled = false;
                    console.error('Batch save error:', err);
                });
            });
        }

        // 5. Product Picker Initialization for Drawer (Compatibility)
        const wrapper = document.querySelector('[data-product-picker="true"]');
        if (wrapper) {
            const native  = wrapper.querySelector('.pp-native-select');
            const trigger = wrapper.querySelector('.pp-trigger');
            const drop    = wrapper.querySelector('.pp-dropdown');
            const dropSearch = drop?.querySelector('.pp-dropdown-search');
            const listWrap = drop?.querySelector('.pp-list-wrap');
            const listEl   = listWrap?.querySelector('.pp-list-inner');
            const footer   = drop?.querySelector('.pp-footer');
            const counter  = wrapper?.querySelector('.pp-counter');

            if (native && trigger && drop) {
                let allProducts = [];
                native.querySelectorAll('option').forEach(function (opt) {
                    allProducts.push({
                        id: opt.value,
                        sku: opt.dataset.sku || '',
                        name: opt.dataset.name || opt.textContent,
                        category: opt.dataset.category || '',
                    });
                });

                function buildList(products) {
                    if (!listEl) return;
                    let html = '';
                    products.forEach(function (p) {
                        const isSelected = native.querySelector(`option[value="${p.id}"]`)?.selected;
                        html += `<div class="pp-item${isSelected ? ' selected' : ''}" data-id="${p.id}">
                            <input type="checkbox" ${isSelected ? 'checked' : ''} value="${p.id}" aria-label="${p.sku}">
                            <div class="pp-item-info">
                                <div class="pp-item-sku">${escapeHtml(p.sku)}</div>
                                <div class="pp-item-name">${escapeHtml(p.name)}</div>
                            </div>
                        </div>`;
                    });
                    listEl.innerHTML = html;

                    listEl.querySelectorAll('.pp-item').forEach(function (item) {
                        item.addEventListener('click', function (e) {
                            if (e.target.type === 'checkbox') return;
                            const cb = item.querySelector('input[type="checkbox"]');
                            if (cb) {
                                cb.checked = !cb.checked;
                                cb.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        });
                    });
                }

                function escapeHtml(str) {
                    const div = document.createElement('div');
                    div.textContent = str || '';
                    return div.innerHTML;
                }

                function getSelectedValues() {
                    return Array.from(native.selectedOptions).map(function (o) { return o.value; });
                }

                function renderChips() {
                    trigger.querySelectorAll('.pp-chip').forEach(function (c) { c.remove(); });
                    const selected = getSelectedValues();
                    if (selected.length === 0) return;
                    selected.forEach(function (val) {
                        const p = allProducts.find(function (item) { return item.id == val; });
                        if (!p) return;
                        const chip = document.createElement('span');
                        chip.className = 'pp-chip';
                        chip.innerHTML = `<small class="pp-item-sku">${escapeHtml(p.sku)}</small>${escapeHtml(p.name)}<button type="button" class="pp-chip-remove" data-val="${val}" title="Hapus">&times;</button>`;
                        trigger.insertBefore(chip, trigger.querySelector('.pp-placeholder') || null);
                    });
                    const ph = trigger.querySelector('.pp-placeholder');
                    if (ph && selected.length > 0) ph.remove();

                    trigger.querySelectorAll('.pp-chip-remove').forEach(function (btn) {
                        btn.addEventListener('click', function (e) {
                            e.stopPropagation();
                            const v = this.dataset.val;
                            const opt = native.querySelector(`option[value="${v}"]`);
                            if (opt) opt.selected = false;
                            native.dispatchEvent(new Event('change'));
                        });
                    });
                }

                function renderFooter() {
                    if (!footer) return;
                    const sel = getSelectedValues().length;
                    const total = allProducts.length;
                    footer.innerHTML = `
                        <span><strong>${sel}</strong> / ${total} dipilih</span>
                        <div>
                            <button type="button" class="pp-footer-btn btn-select-all" ${sel >= total ? 'disabled' : ''}>Pilih Semua</button>
                            <button type="button" class="pp-footer-btn pp-btn-deselect-all" ${sel === 0 ? 'disabled' : ''}>Hapus</button>
                        </div>
                    `;
                    footer.querySelector('.pp-btn-deselect-all')?.addEventListener('click', function () {
                        native.querySelectorAll('option').forEach(function (o) { o.selected = false; });
                        native.dispatchEvent(new Event('change'));
                    });
                    footer.querySelector('.btn-select-all')?.addEventListener('click', function () {
                        native.querySelectorAll('option').forEach(function (o) { o.selected = true; });
                        native.dispatchEvent(new Event('change'));
                    });
                }

                function updateCounter() {
                    if (!counter) return;
                    const sel = getSelectedValues().length;
                    counter.classList.toggle('has-items', sel > 0);
                    counter.innerHTML = `<span class="pp-counter-dot">${sel}</span><span>${sel === 0 ? 'Belum ada produk dipilih' : sel + ' produk terpilih'}</span>`;
                    const submitButton = document.getElementById('save-product-prices');
                    if (submitButton) submitButton.disabled = sel === 0;
                }

                buildList(allProducts);
                renderChips();
                renderFooter();
                updateCounter();

                trigger.addEventListener('click', function (e) {
                    if (e.target.closest('.pp-chip-remove')) return;
                    drop.classList.toggle('show');
                    trigger.classList.toggle('open');
                });

                native.addEventListener('change', function () {
                    renderChips();
                    renderFooter();
                    updateCounter();
                    const selected = getSelectedValues();
                    listEl?.querySelectorAll('.pp-item').forEach(function (item) {
                        const id = item.dataset.id;
                        const cb = item.querySelector('input[type="checkbox"]');
                        const isSelected = selected.includes(id);
                        item.classList.toggle('selected', isSelected);
                        if (cb) cb.checked = isSelected;
                    });
                });

                listWrap?.addEventListener('change', function (e) {
                    if (e.target.type === 'checkbox') {
                        const val = e.target.value;
                        const opt = native.querySelector(`option[value="${val}"]`);
                        if (opt) opt.selected = e.target.checked;
                        native.dispatchEvent(new Event('change'));
                    }
                });

                dropSearch?.addEventListener('input', function () {
                    const q = this.value.trim().toLowerCase();
                    const filtered = q ? allProducts.filter(function (p) {
                        return p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q);
                    }) : allProducts;
                    buildList(filtered);
                });

                document.addEventListener('click', function (e) {
                    if (!wrapper.contains(e.target)) {
                        drop.classList.remove('show');
                        trigger.classList.remove('open');
                    }
                });
            }
        }
    });
})();
</script>
@endpush
