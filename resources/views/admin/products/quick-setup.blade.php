@extends('layouts.metronic.app')
@section('title', 'Setup Operasional Cepat Produk')
@section('page_title', 'Setup Operasional Cepat Produk')

@section('toolbar_actions')
    <a href="{{ route('pricing.product-prices.index', ['work_location_id' => $currentLocation->id]) }}" class="btn btn-light-info btn-sm me-2">
        <i class="ki-outline ki-tag me-1"></i> Ke Penetapan Ring Harga
    </a>
    <a href="{{ route('admin.products.import.index') }}" class="btn btn-light-primary btn-sm me-2">
        <i class="ki-outline ki-file-up me-1"></i> Import Excel Produk
    </a>
    <a href="{{ route('admin.products.index') }}" class="btn btn-light btn-sm">
        <i class="ki-outline ki-cube-2 me-1"></i> Katalog Produk
    </a>
@endsection

@section('page_guide')
    <x-metronic.page-guide id="admin-product-quick-setup" title="Panduan Setup Operasional Cepat Produk">
        <x-slot:function>
            <p>Halaman ini memudahkan pengisian parameter operasional produk pasca import Excel. Terdiri dari data produk, input HPP dengan pilihan satuan kulakan (misal Lusin atau PCS) yang otomatis dikonversi ke HPP per pieces, input stok awal dengan pilihan satuan yang otomatis dikonversi ke total pieces, serta 3 Ring Harga Jual (Retail, B2B, POS) yang 100% sinkron dengan halaman Penetapan Harga.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol>
                <li>Pilih lokasi kerja gudang atau toko di bagian atas.</li>
                <li>Pada kolom <strong>HPP Produk</strong>: pilih satuan kulakan (default: PCS, bisa diubah ke LSN/Gros/Box) dan ketik nominal HPP. Format titik ribuan otomatis muncul (misal <em>120.000</em>) dan konversi per pcs terhitung real-time.</li>
                <li>Pada kolom <strong>Stok Produk</strong>: pilih satuan dan ketik jumlah stok fisik awal. Format titik ribuan otomatis muncul dan total pieces terhitung real-time.</li>
                <li>Pada kolom <strong>Ring Harga</strong>: isi batas Min, Acuan (Rekomendasi), dan Max untuk <strong>Ring Retail</strong>, <strong>Ring B2B</strong>, dan <strong>Ring POS</strong> sesuai kebijakan penjualan.</li>
                <li>Atur masa berlaku (default <em>Unlimited</em>).</li>
                <li>Klik tombol <strong>Simpan</strong> pada baris bersangkutan atau klik <strong>Simpan Semua Perubahan</strong> di toolbar atas.</li>
            </ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul>
                <li><strong>Kolom Produk:</strong> Nama produk besar, SKU, dan badge status lengkap/belum lengkap.</li>
                <li><strong>Kolom HPP Produk:</strong> Pilihan satuan + input HPP + keterangan konversi per pcs. Pilihan satuan tersimpan permanen saat refresh.</li>
                <li><strong>Kolom Stok Produk:</strong> Pilihan satuan + input stok + keterangan konversi total pcs. Pilihan satuan tersimpan permanen saat refresh.</li>
                <li><strong>Ring Retail / B2B / POS:</strong> Tiga ring harga acuan penjualan yang langsung sinkron ke kasir, portal B2B, dan modul pricing dengan input titik ribuan otomatis.</li>
                <li><strong>Masa Berlaku:</strong> Menentukan periode aktif harga (default unlimited).</li>
            </ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Parameter harga yang disimpan di sini langsung tampil dan tersinkronisasi di halaman <code>/pricing/product-prices</code> dan sistem POS.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol>
                <li>Gunakan tab "Belum Lengkap" untuk memprioritaskan produk baru.</li>
                <li>Ketik HPP, Stok, atau Ring Harga dengan pemisah ribuan otomatis (misal ketik <code>12000</code> otomatis tampil <code>12.000</code>).</li>
                <li>Simpan baris demi baris atau sekaligus semua baris. Pilihan satuan Anda tetap tersimpan dan tidak akan kembali ke default saat refresh.</li>
            </ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0">
                <ul class="mb-0">
                    <li>Stok awal dicatat satu kali sebagai saldo awal pembukuan fisik pada lokasi kerja terkait.</li>
                    <li>Rasio konversi satuan mengikuti aturan baku master satuan (contoh: 1 Lusin = 12 pcs, 1 Gros = 144 pcs).</li>
                </ul>
            </div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Produk Kopi Arabika: HPP dipilih satuan Lsn seharga Rp 120.000 (&rarr; konversi HPP 10.000/pcs). Stok dipilih Lsn sebanyak 2 (&rarr; konversi stok 24 pcs). Ring Retail Acuan = Rp 15.000, Ring B2B Acuan = Rp 140.000, Ring POS Acuan = Rp 15.000.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
<style>
    .setup-mini-card {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 0.5rem 0.6rem;
    }
    .ring-price-cell {
        display: flex;
        flex-direction: column;
        gap: 3px;
        min-width: 160px;
        max-width: 185px;
    }
    .ring-input-wrap {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .ring-lbl {
        width: 46px;
        min-width: 46px;
        font-size: 10px;
        font-weight: 700;
        text-align: center;
        flex-shrink: 0;
        white-space: nowrap;
        user-select: none;
        letter-spacing: 0.3px;
    }
    .ring-input-box {
        font-size: 11px;
        padding: 2px 6px;
        height: 26px;
    }
    .ring-input-box.is-recommended {
        height: 28px;
        font-weight: 700;
        background-color: #fff;
    }
    .product-row.is-dirty {
        background-color: #fffdf5 !important;
        border-left: 4px solid #f59e0b !important;
    }
    .product-row.is-saving {
        opacity: 0.6;
        pointer-events: none;
    }
    .bg-light-success-subtle {
        background-color: rgba(16, 185, 129, 0.03);
    }
    .conversion-pill {
        border-radius: 0.375rem;
        padding: 0.25rem 0.5rem;
        transition: all 0.15s ease-in-out;
    }
</style>

<div class="d-flex flex-column gap-5">
    {{-- KPI & Location Selector --}}
    <div class="row g-5">
        <div class="col-12 col-md-4">
            <div class="card card-flush bg-body shadow-sm h-100 border border-gray-200">
                <div class="card-body d-flex flex-column justify-content-between p-5">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-gray-600 fw-bold fs-7 text-uppercase">Lokasi Kerja Target</span>
                        <span class="badge badge-light-primary fw-bold">{{ ucfirst($currentLocation->type) }}</span>
                    </div>
                    <div>
                        <h4 class="fw-bolder text-gray-900 mb-1">{{ $currentLocation->name }}</h4>
                        <div class="text-muted fs-8">{{ $currentLocation->city ?: 'Semua Operasional' }} · {{ $currentLocation->code }}</div>
                    </div>
                    <div class="mt-4 pt-3 border-top border-gray-100">
                        <form method="GET" id="form-change-location" class="d-flex align-items-center gap-2">
                            <input type="hidden" name="completeness" value="{{ $filters['completeness'] }}">
                            <input type="hidden" name="category_id" value="{{ $filters['category_id'] }}">
                            <input type="hidden" name="q" value="{{ $filters['q'] }}">
                            <select name="work_location_id" class="form-select form-select-sm form-select-solid" onchange="this.form.submit()">
                                @foreach($locations as $loc)
                                    <option value="{{ $loc->id }}" @selected($loc->id === $currentLocation->id)>
                                        {{ $loc->name }} ({{ ucfirst($loc->type) }})
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4">
            <div class="card card-flush bg-body shadow-sm h-100 border border-amber-200" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body d-flex flex-column justify-content-between p-5">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="text-gray-600 fw-bold fs-7">BELUM LENGKAP</span>
                        <div class="symbol symbol-35px bg-light-warning">
                            <span class="symbol-label"><i class="ki-outline ki-timer text-warning fs-3"></i></span>
                        </div>
                    </div>
                    <div class="my-2">
                        <span class="fs-2hx fw-bolder text-warning">{{ number_format($stats['incomplete_count'], 0, ',', '.') }}</span>
                        <span class="text-muted fs-7 ms-1">produk</span>
                    </div>
                    <div class="text-muted fs-8">Perlu dilengkapi HPP, Stok Awal, & Ring Harga.</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4">
            <div class="card card-flush bg-body shadow-sm h-100 border border-emerald-200" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body d-flex flex-column justify-content-between p-5">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="text-gray-600 fw-bold fs-7">LENGKAP &amp; AKTIF</span>
                        <div class="symbol symbol-35px bg-light-success">
                            <span class="symbol-label"><i class="ki-outline ki-check-circle text-success fs-3"></i></span>
                        </div>
                    </div>
                    <div class="my-2">
                        <span class="fs-2hx fw-bolder text-success">{{ number_format($stats['complete_count'], 0, ',', '.') }}</span>
                        <span class="text-muted fs-7 ms-1">produk</span>
                    </div>
                    <div class="text-muted fs-8">HPP, persediaan awal, dan ring harga siap transaksi.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Toolbar & Batch Save Header --}}
    <x-metronic.card>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
            {{-- Filter Form --}}
            <form method="GET" class="d-flex flex-wrap align-items-center gap-3">
                <input type="hidden" name="work_location_id" value="{{ $currentLocation->id }}">

                {{-- Status Kelengkapan --}}
                <div class="nav-group nav-group-fluid bg-light p-1 rounded">
                    <a href="{{ request()->fullUrlWithQuery(['completeness' => 'incomplete', 'page' => 1]) }}"
                       class="btn btn-sm btn-color-gray-600 px-4 {{ $filters['completeness'] === 'incomplete' ? 'btn-active btn-white active shadow-sm text-warning fw-bolder' : '' }}">
                        <i class="ki-outline ki-cross-circle fs-6 me-1"></i> Belum Lengkap
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['completeness' => 'all', 'page' => 1]) }}"
                       class="btn btn-sm btn-color-gray-600 px-4 {{ $filters['completeness'] === 'all' ? 'btn-active btn-white active shadow-sm fw-bolder' : '' }}">
                        Semua ({{ $stats['total_products'] }})
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['completeness' => 'complete', 'page' => 1]) }}"
                       class="btn btn-sm btn-color-gray-600 px-4 {{ $filters['completeness'] === 'complete' ? 'btn-active btn-white active shadow-sm text-success fw-bolder' : '' }}">
                        <i class="ki-outline ki-check-circle fs-6 me-1"></i> Lengkap
                    </a>
                </div>

                {{-- Kategori --}}
                <select name="category_id" class="form-select form-select-sm form-select-solid w-175px" onchange="this.form.submit()">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) $filters['category_id'] === $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>

                {{-- Pencarian --}}
                <div class="position-relative">
                    <i class="ki-outline ki-magnifier fs-5 position-absolute top-50 translate-middle-y ms-3 text-gray-500"></i>
                    <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm form-control-solid ps-9 w-200px" placeholder="Cari SKU atau nama...">
                </div>

                <button type="submit" class="btn btn-sm btn-light-primary">
                    <i class="ki-outline ki-filter me-1"></i> Filter
                </button>
                @if($filters['q'] !== '' || $filters['category_id'] || $filters['completeness'] !== 'incomplete')
                    <a href="{{ route('admin.products.quick-setup.index', ['work_location_id' => $currentLocation->id]) }}" class="btn btn-sm btn-light-danger">
                        Reset
                    </a>
                @endif
            </form>

            {{-- Batch Actions --}}
            <div class="d-flex align-items-center gap-2">
                <span class="badge badge-light-warning fw-bold d-none" id="dirty-badge">
                    <span id="dirty-count">0</span> baris diubah
                </span>
                <button type="button" class="btn btn-sm btn-primary fw-bold" id="btn-save-all" onclick="saveAllRows()">
                    <i class="ki-outline ki-check fs-5 me-1"></i> Simpan Semua Perubahan
                </button>
            </div>
        </div>

        {{-- Spreadsheet Table Remake --}}
        <div class="table-responsive">
            <table class="table table-row-bordered table-row-gray-300 align-middle gs-3 gy-3" id="table-quick-setup">
                <thead class="bg-light">
                    <tr class="fw-bolder text-gray-800 fs-8 text-uppercase">
                        {{-- Kolom 1: Produk --}}
                        <th style="min-width: 230px;" class="ps-3">
                            <span class="text-gray-900 fw-bolder">Produk</span>
                            <div class="text-muted fs-9 fw-normal">Nama · SKU · Status</div>
                        </th>
                        {{-- Kolom 2: HPP Produk --}}
                        <th style="min-width: 230px; background-color: rgba(var(--bs-primary-rgb), 0.03);">
                            <span class="text-gray-900 fw-bolder">HPP Produk</span>
                            <div class="text-muted fs-9 fw-normal">Satuan Kulakan &rarr; Per Pcs</div>
                        </th>
                        {{-- Kolom 3: Stok Produk --}}
                        <th style="min-width: 220px;">
                            <span class="text-gray-900 fw-bolder">Stok Produk</span>
                            <div class="text-muted fs-9 fw-normal">Fisik Awal &rarr; Total Pcs</div>
                        </th>
                        {{-- Kolom 4: Ring Retail --}}
                        <th class="text-center" style="min-width: 170px; background-color: rgba(var(--bs-primary-rgb), 0.03);">
                            <span class="text-primary fw-bolder">Ring Retail</span>
                            <div class="text-muted fs-9 fw-normal">Min · Acuan · Max</div>
                        </th>
                        {{-- Kolom 5: Ring B2B --}}
                        <th class="text-center" style="min-width: 170px;">
                            <span class="text-dark fw-bolder">Ring B2B</span>
                            <div class="text-muted fs-9 fw-normal">Min · Acuan · Max</div>
                        </th>
                        {{-- Kolom 6: Ring POS --}}
                        <th class="text-center" style="min-width: 170px; background-color: rgba(var(--bs-success-rgb), 0.03);">
                            <span class="text-success fw-bolder">Ring POS</span>
                            <div class="text-muted fs-9 fw-normal">Min · Acuan · Max</div>
                        </th>
                        {{-- Kolom 7: Masa Berlaku --}}
                        <th class="text-center" style="min-width: 120px;">
                            <span class="fw-bolder">Masa Berlaku</span>
                            <div class="text-muted fs-9 fw-normal">Default: Unlimited</div>
                        </th>
                        {{-- Kolom 8: Aksi --}}
                        <th class="text-center pe-3" style="min-width: 75px;">Aksi</th>
                    </tr>
                </thead>
                <tbody class="fs-7">
                    @forelse($products as $product)
                        @php
                            // Satuan Dasar & Standar Produk
                            $baseUnit = $product->baseUnit ?? $units->firstWhere('code', 'PCS') ?? $units->first();
                            $pcsUnit = $units->firstWhere('code', 'PCS') ?? $baseUnit;
                            
                            // Ambil data harga aktif pada lokasi terpilih
                            $pricesCollection = $product->prices;
                            $retailPriceModel = $pricesCollection->first(fn($p) => $p->price_ring === 'Ring Retail' || $p->channel === 'retail');
                            $b2bPriceModel = $pricesCollection->first(fn($p) => $p->price_ring === 'Ring B2B' || $p->channel === 'b2b');
                            $posPriceModel = $pricesCollection->first(fn($p) => $p->price_ring === 'Ring POS' || $p->channel === 'pos');

                            // HPP Eceran saat ini (per pcs)
                            $retailCostPrice = (float) $product->cost_price;

                            // Stok Awal Existing
                            $openingBatch = $product->stockBatches->first();
                            $hasExistingOpeningStock = (bool) $openingBatch;
                            $existingStockOnHand = $openingBatch ? (float) $openingBatch->quantity_on_hand : 0;

                            // Konfigurasi tersimpan dari quick_setup (jika sudah pernah disimpan sebelumnya)
                            $productAttrs = (array) ($product->attributes ?? []);
                            $quickSetupAttrs = (array) ($productAttrs['quick_setup'] ?? []);
                            $locationsAttrs = (array) ($quickSetupAttrs['locations'] ?? []);
                            $qsData = $locationsAttrs[$currentLocation->id] ?? ($quickSetupAttrs['hpp_unit_id'] ?? null ? $quickSetupAttrs : null);

                            // 1. Tentukan Satuan HPP & Nominal HPP (Mempertahankan pilihan user secara permanen)
                            if ($qsData && !empty($qsData['hpp_unit_id']) && $units->contains('id', (int) $qsData['hpp_unit_id'])) {
                                $defaultHppUnit = $units->firstWhere('id', (int) $qsData['hpp_unit_id']);
                                $defaultHppFactor = match(strtoupper((string) $defaultHppUnit->code)) {
                                    'PCS' => 1,
                                    'LSN' => 12,
                                    'GROS' => 144,
                                    default => (float) ($product->units->firstWhere('unit_id', $defaultHppUnit->id)?->conversion_factor ?? 1),
                                };
                                $defaultHppAmount = isset($qsData['hpp_amount']) ? (float) $qsData['hpp_amount'] : ($retailCostPrice * $defaultHppFactor);
                            } else {
                                // Default produk baru: PCS (Satuan Dasar Produk)
                                $defaultHppUnit = $baseUnit;
                                $defaultHppFactor = 1;
                                $defaultHppAmount = $retailCostPrice;
                            }

                            // 2. Tentukan Satuan Stok & Jumlah Stok (Mempertahankan pilihan user secara permanen)
                            if ($qsData && !empty($qsData['stock_unit_id']) && $units->contains('id', (int) $qsData['stock_unit_id'])) {
                                $defaultStockUnit = $units->firstWhere('id', (int) $qsData['stock_unit_id']);
                                $defaultStockFactor = match(strtoupper((string) $defaultStockUnit->code)) {
                                    'PCS' => 1,
                                    'LSN' => 12,
                                    'GROS' => 144,
                                    default => (float) ($product->units->firstWhere('unit_id', $defaultStockUnit->id)?->conversion_factor ?? 1),
                                };
                                $defaultStockQty = $hasExistingOpeningStock 
                                    ? ($existingStockOnHand / ($defaultStockFactor ?: 1)) 
                                    : (float) ($qsData['stock_qty'] ?? 0);
                            } else {
                                // Default produk baru: PCS (Satuan Dasar Produk)
                                $defaultStockUnit = $baseUnit;
                                $defaultStockFactor = 1;
                                $defaultStockQty = $existingStockOnHand;
                            }

                            // Status Kelengkapan
                            $hasPrices = $retailPriceModel && (float) $retailPriceModel->recommended_price > 0;
                            $isComplete = $hasExistingOpeningStock && $hasPrices && $retailCostPrice > 0;

                            // Masa berlaku
                            $firstPrice = $retailPriceModel ?: ($b2bPriceModel ?: $posPriceModel);
                            $isUnlimited = empty($firstPrice?->ends_at);
                            $startsAt = $firstPrice?->starts_at?->toDateString() ?: now()->toDateString();
                            $endsAt = $firstPrice?->ends_at?->toDateString();
                        @endphp
                        <tr class="product-row {{ $isComplete ? 'bg-light-success-subtle' : '' }}" 
                            data-product-id="{{ $product->id }}" 
                            data-product-name="{{ $product->name }}"
                            data-has-existing-stock="{{ $hasExistingOpeningStock ? '1' : '0' }}">
                            
                            {{-- 1. Data Produk: Nama Besar, SKU, Status --}}
                            <td class="ps-3 align-top">
                                <div class="d-flex flex-column">
                                    <span class="fw-bolder text-gray-900 fs-6 line-clamp-2 mb-1" title="{{ $product->name }}">{{ $product->name }}</span>
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="badge badge-light-dark font-monospace fs-8 px-2 py-1">{{ $product->sku }}</span>
                                        <span class="text-muted fs-8">{{ $product->category?->name ?: 'Umum' }}</span>
                                    </div>
                                    <div>
                                        <span class="badge {{ $isComplete ? 'badge-light-success text-success' : 'badge-light-warning text-warning' }} fs-9 fw-bold row-status-badge">
                                            <i class="ki-outline {{ $isComplete ? 'ki-check-circle text-success' : 'ki-cross-circle text-warning' }} fs-9 me-1"></i>
                                            {{ $isComplete ? 'Lengkap' : 'Belum Lengkap' }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            {{-- 2. HPP Produk: Mini-Card Rapi (Satuan + Input HPP Otomatis Titik + Real-Time Conversion) --}}
                            <td class="align-top" style="background-color: rgba(var(--bs-primary-rgb), 0.02);">
                                <div class="setup-mini-card d-flex flex-column gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="d-flex flex-column" style="width: 85px;">
                                            <label class="text-gray-600 fs-9 fw-bolder text-uppercase mb-1">Satuan:</label>
                                            <select class="form-select form-select-sm form-select-solid input-hpp-unit px-2 py-1 fs-8 fw-semibold" style="height: 32px;">
                                                @foreach($units as $unit)
                                                    @php
                                                        $factor = match(strtoupper((string) $unit->code)) {
                                                            'PCS' => 1,
                                                            'LSN' => 12,
                                                            'GROS' => 144,
                                                            default => 1,
                                                        };
                                                        $customUnit = $product->units->firstWhere('unit_id', $unit->id);
                                                        if ($customUnit && (float) $customUnit->conversion_factor > 0) {
                                                            $factor = (float) $customUnit->conversion_factor;
                                                        }
                                                    @endphp
                                                    <option value="{{ $unit->id }}" 
                                                            data-code="{{ strtolower($unit->code) }}" 
                                                            data-factor="{{ $factor }}"
                                                            @selected($unit->id === $defaultHppUnit->id)>
                                                        {{ strtolower($unit->code) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="d-flex flex-column flex-grow-1">
                                            <label class="text-gray-600 fs-9 fw-bolder text-uppercase mb-1">Input HPP:</label>
                                            <div class="input-group input-group-sm input-group-solid" style="height: 32px;">
                                                <span class="input-group-text px-2 fs-9 text-gray-600 fw-bold">Rp</span>
                                                <input type="text" 
                                                       inputmode="numeric" 
                                                       class="form-control form-control-sm form-control-solid input-hpp-amount format-thousand text-end fw-bold fs-7 py-1" 
                                                       value="{{ $defaultHppAmount > 0 ? number_format($defaultHppAmount, 0, ',', '.') : '' }}"
                                                       placeholder="0">
                                            </div>
                                        </div>
                                    </div>
                                    {{-- Hasil Konversi Real-Time HPP --}}
                                    <div class="conversion-pill d-flex align-items-center justify-content-between bg-light-success border border-success border-opacity-25">
                                        <span class="fs-9 text-gray-700 fw-semibold">
                                            <i class="ki-outline ki-arrows-circle fs-9 text-success me-1"></i>Konversi HPP:
                                        </span>
                                        <span class="fs-8 fw-bolder text-success">
                                            Rp <span class="val-hpp-pcs">{{ number_format($retailCostPrice, 0, ',', '.') }}</span><span class="text-muted fw-normal fs-9"> /pcs</span>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            {{-- 3. Stok Produk: Mini-Card Rapi (Satuan + Input Stok Otomatis Titik + Real-Time Conversion) --}}
                            <td class="align-top">
                                <div class="setup-mini-card d-flex flex-column gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="d-flex flex-column" style="width: 85px;">
                                            <label class="text-gray-600 fs-9 fw-bolder text-uppercase mb-1">Satuan:</label>
                                            <select class="form-select form-select-sm form-select-solid input-stock-unit px-2 py-1 fs-8 fw-semibold" 
                                                    style="height: 32px;"
                                                    {{ $hasExistingOpeningStock ? 'disabled' : '' }}>
                                                @foreach($units as $unit)
                                                    @php
                                                        $factor = match(strtoupper((string) $unit->code)) {
                                                            'PCS' => 1,
                                                            'LSN' => 12,
                                                            'GROS' => 144,
                                                            default => 1,
                                                        };
                                                        $customUnit = $product->units->firstWhere('unit_id', $unit->id);
                                                        if ($customUnit && (float) $customUnit->conversion_factor > 0) {
                                                            $factor = (float) $customUnit->conversion_factor;
                                                        }
                                                    @endphp
                                                    <option value="{{ $unit->id }}" 
                                                            data-code="{{ strtolower($unit->code) }}" 
                                                            data-factor="{{ $factor }}"
                                                            @selected($unit->id === $defaultStockUnit->id)>
                                                        {{ strtolower($unit->code) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="d-flex flex-column flex-grow-1">
                                            <label class="text-gray-600 fs-9 fw-bolder text-uppercase mb-1">Input Stok:</label>
                                            <input type="text" 
                                                   inputmode="numeric" 
                                                   class="form-control form-control-sm form-control-solid input-stock-qty format-thousand text-end fw-bold fs-7 py-1 {{ $hasExistingOpeningStock ? 'bg-light text-muted border-dashed' : '' }}" 
                                                   style="height: 32px;"
                                                   value="{{ $defaultStockQty > 0 ? number_format($defaultStockQty, 0, ',', '.') : '' }}"
                                                   placeholder="0"
                                                   {{ $hasExistingOpeningStock ? 'readonly' : '' }}>
                                        </div>
                                    </div>
                                    {{-- Hasil Konversi Real-Time Stok --}}
                                    <div class="conversion-stock-wrap">
                                        @if($hasExistingOpeningStock)
                                            <div class="conversion-pill d-flex align-items-center justify-content-between bg-light-success border border-success border-opacity-25">
                                                <span class="badge badge-light-success fs-9 text-success fw-bold p-0">
                                                    <i class="ki-outline ki-lock fs-9 text-success me-1"></i> Terkunci
                                                </span>
                                                <span class="fs-8 fw-bolder text-success">
                                                    {{ number_format($existingStockOnHand, 0, ',', '.') }} <span class="text-muted fw-normal fs-9">pcs</span>
                                                </span>
                                            </div>
                                        @else
                                            <div class="conversion-pill d-flex align-items-center justify-content-between bg-light-primary border border-primary border-opacity-25">
                                                <span class="fs-9 text-gray-700 fw-semibold">
                                                    <i class="ki-outline ki-arrows-circle fs-9 text-primary me-1"></i>Konversi Stok:
                                                </span>
                                                <span class="fs-8 fw-bolder text-primary">
                                                    <span class="val-stock-pcs">{{ number_format($defaultStockQty * $defaultStockFactor, 0, ',', '.') }}</span><span class="text-muted fw-normal fs-9"> pcs</span>
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- 4. Ring Retail: Min · Acuan · Max --}}
                            <td class="text-center align-top" style="background-color: rgba(var(--bs-primary-rgb), 0.02);">
                                <div class="ring-price-cell mx-auto" data-ring="ring_retail">
                                    <div class="ring-input-wrap" title="Batas Minimum Ring Retail">
                                        <span class="ring-lbl text-muted">MIN</span>
                                        <div class="input-group input-group-sm flex-grow-1">
                                            <span class="input-group-text py-0 px-1 fs-9 text-muted">Rp</span>
                                            <input type="text" inputmode="numeric" 
                                                class="form-control form-control-sm text-end ring-input-box format-thousand price-input-min"
                                                value="{{ $retailPriceModel?->min_price ? number_format((float) $retailPriceModel->min_price, 0, ',', '.') : '' }}"
                                                placeholder="{{ $retailPriceModel?->recommended_price ? number_format((float) $retailPriceModel->recommended_price, 0, ',', '.') : '0' }}">
                                        </div>
                                    </div>
                                    <div class="ring-input-wrap bg-light-primary rounded p-1 border border-primary border-opacity-25" title="Harga Acuan/Rekomendasi Ring Retail (Muncul Otomatis di Kasir/Order)">
                                        <span class="ring-lbl text-primary fw-bolder">ACUAN</span>
                                        <div class="input-group input-group-sm flex-grow-1">
                                            <span class="input-group-text py-0 px-1 fs-8 text-primary fw-bold">Rp</span>
                                            <input type="text" inputmode="numeric" 
                                                class="form-control form-control-sm text-end ring-input-box is-recommended border-primary format-thousand price-input-rec"
                                                value="{{ $retailPriceModel?->recommended_price ? number_format((float) $retailPriceModel->recommended_price, 0, ',', '.') : '' }}"
                                                placeholder="0">
                                        </div>
                                    </div>
                                    <div class="ring-input-wrap" title="Batas Maksimum Ring Retail">
                                        <span class="ring-lbl text-muted">MAX</span>
                                        <div class="input-group input-group-sm flex-grow-1">
                                            <span class="input-group-text py-0 px-1 fs-9 text-muted">Rp</span>
                                            <input type="text" inputmode="numeric" 
                                                class="form-control form-control-sm text-end ring-input-box format-thousand price-input-max"
                                                value="{{ $retailPriceModel?->max_price ? number_format((float) $retailPriceModel->max_price, 0, ',', '.') : '' }}"
                                                placeholder="{{ $retailPriceModel?->recommended_price ? number_format((float) $retailPriceModel->recommended_price, 0, ',', '.') : '0' }}">
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- 5. Ring B2B: Min · Acuan · Max --}}
                            <td class="text-center align-top">
                                <div class="ring-price-cell mx-auto" data-ring="ring_b2b">
                                    <div class="ring-input-wrap" title="Batas Minimum Ring B2B">
                                        <span class="ring-lbl text-muted">MIN</span>
                                        <div class="input-group input-group-sm flex-grow-1">
                                            <span class="input-group-text py-0 px-1 fs-9 text-muted">Rp</span>
                                            <input type="text" inputmode="numeric" 
                                                class="form-control form-control-sm text-end ring-input-box format-thousand price-input-min"
                                                value="{{ $b2bPriceModel?->min_price ? number_format((float) $b2bPriceModel->min_price, 0, ',', '.') : '' }}"
                                                placeholder="{{ $b2bPriceModel?->recommended_price ? number_format((float) $b2bPriceModel->recommended_price, 0, ',', '.') : '0' }}">
                                        </div>
                                    </div>
                                    <div class="ring-input-wrap bg-light-dark rounded p-1 border border-dark border-opacity-25" title="Harga Acuan/Rekomendasi Ring B2B">
                                        <span class="ring-lbl text-gray-800 fw-bolder">ACUAN</span>
                                        <div class="input-group input-group-sm flex-grow-1">
                                            <span class="input-group-text py-0 px-1 fs-8 text-gray-800 fw-bold">Rp</span>
                                            <input type="text" inputmode="numeric" 
                                                class="form-control form-control-sm text-end ring-input-box is-recommended border-gray-600 format-thousand price-input-rec"
                                                value="{{ $b2bPriceModel?->recommended_price ? number_format((float) $b2bPriceModel->recommended_price, 0, ',', '.') : '' }}"
                                                placeholder="0">
                                        </div>
                                    </div>
                                    <div class="ring-input-wrap" title="Batas Maksimum Ring B2B">
                                        <span class="ring-lbl text-muted">MAX</span>
                                        <div class="input-group input-group-sm flex-grow-1">
                                            <span class="input-group-text py-0 px-1 fs-9 text-muted">Rp</span>
                                            <input type="text" inputmode="numeric" 
                                                class="form-control form-control-sm text-end ring-input-box format-thousand price-input-max"
                                                value="{{ $b2bPriceModel?->max_price ? number_format((float) $b2bPriceModel->max_price, 0, ',', '.') : '' }}"
                                                placeholder="{{ $b2bPriceModel?->recommended_price ? number_format((float) $b2bPriceModel->recommended_price, 0, ',', '.') : '0' }}">
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- 6. Ring POS: Min · Acuan · Max --}}
                            <td class="text-center align-top" style="background-color: rgba(var(--bs-success-rgb), 0.02);">
                                <div class="ring-price-cell mx-auto" data-ring="ring_pos">
                                    <div class="ring-input-wrap" title="Batas Minimum Ring POS">
                                        <span class="ring-lbl text-muted">MIN</span>
                                        <div class="input-group input-group-sm flex-grow-1">
                                            <span class="input-group-text py-0 px-1 fs-9 text-muted">Rp</span>
                                            <input type="text" inputmode="numeric" 
                                                class="form-control form-control-sm text-end ring-input-box format-thousand price-input-min"
                                                value="{{ $posPriceModel?->min_price ? number_format((float) $posPriceModel->min_price, 0, ',', '.') : '' }}"
                                                placeholder="{{ $posPriceModel?->recommended_price ? number_format((float) $posPriceModel->recommended_price, 0, ',', '.') : '0' }}">
                                        </div>
                                    </div>
                                    <div class="ring-input-wrap bg-light-success rounded p-1 border border-success border-opacity-25" title="Harga Acuan/Rekomendasi Ring POS (Otomatis Masuk Keranjang Kasir)">
                                        <span class="ring-lbl text-success fw-bolder">ACUAN</span>
                                        <div class="input-group input-group-sm flex-grow-1">
                                            <span class="input-group-text py-0 px-1 fs-8 text-success fw-bold">Rp</span>
                                            <input type="text" inputmode="numeric" 
                                                class="form-control form-control-sm text-end ring-input-box is-recommended border-success format-thousand price-input-rec"
                                                value="{{ $posPriceModel?->recommended_price ? number_format((float) $posPriceModel->recommended_price, 0, ',', '.') : '' }}"
                                                placeholder="0">
                                        </div>
                                    </div>
                                    <div class="ring-input-wrap" title="Batas Maksimum Ring POS">
                                        <span class="ring-lbl text-muted">MAX</span>
                                        <div class="input-group input-group-sm flex-grow-1">
                                            <span class="input-group-text py-0 px-1 fs-9 text-muted">Rp</span>
                                            <input type="text" inputmode="numeric" 
                                                class="form-control form-control-sm text-end ring-input-box format-thousand price-input-max"
                                                value="{{ $posPriceModel?->max_price ? number_format((float) $posPriceModel->max_price, 0, ',', '.') : '' }}"
                                                placeholder="{{ $posPriceModel?->recommended_price ? number_format((float) $posPriceModel->recommended_price, 0, ',', '.') : '0' }}">
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- 7. Masa Berlaku: Unlimited / Tanggal --}}
                            <td class="text-center align-top">
                                <div class="d-flex flex-column align-items-center gap-1 pt-1">
                                    <div class="form-check form-check-custom form-check-solid form-check-sm">
                                        <input class="form-check-input unlimited-toggle" type="checkbox"
                                            id="unlimited_{{ $product->id }}"
                                            name="unlimited"
                                            value="1"
                                            @checked($isUnlimited)>
                                        <label class="form-check-label fs-8 fw-semibold text-gray-700 cursor-pointer" for="unlimited_{{ $product->id }}">
                                            Unlimited
                                        </label>
                                    </div>
                                    <div class="date-range-container d-flex flex-column gap-1 {{ $isUnlimited ? 'd-none' : '' }}" style="width: 110px;">
                                        <input type="date" name="starts_at" value="{{ $startsAt }}" class="form-control form-control-sm fs-9 py-1 px-1 input-starts-at" title="Mulai Berlaku">
                                        <input type="date" name="ends_at" value="{{ $endsAt }}" class="form-control form-control-sm fs-9 py-1 px-1 input-ends-at" placeholder="Selesai" title="Tanggal Selesai">
                                    </div>
                                </div>
                            </td>

                            {{-- 8. Aksi: Simpan Baris --}}
                            <td class="text-center pe-3 align-top">
                                <button type="button" 
                                        class="btn btn-icon btn-sm btn-light-primary btn-save-row mt-1" 
                                        title="Simpan baris produk ini"
                                        onclick="saveSingleRow(this)">
                                    <i class="ki-outline ki-check fs-3"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-10">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="ki-outline ki-check-circle text-success fs-3x mb-3"></i>
                                    <h5 class="fw-bolder text-gray-800">Semua Produk Sudah Lengkap!</h5>
                                    <p class="text-muted fs-7 mb-3">Tidak ada produk yang perlu disetup pada filter ini.</p>
                                    <a href="{{ request()->fullUrlWithQuery(['completeness' => 'all']) }}" class="btn btn-sm btn-light-primary">
                                        Lihat Semua Produk
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($products->hasPages())
            <div class="d-flex justify-content-between align-items-center mt-5">
                <div class="text-muted fs-7">
                    Menampilkan {{ $products->firstItem() }} - {{ $products->lastItem() }} dari total {{ $products->total() }} produk
                </div>
                {{ $products->links() }}
            </div>
        @endif
    </x-metronic.card>
</div>

{{-- Inline Interactive Calculation Script & AJAX Save --}}
<script>
    const WORK_LOCATION_ID = {{ (int) $currentLocation->id }};
    const CSRF_TOKEN = '{{ csrf_token() }}';
    const SAVE_ROW_URL = '{{ route("admin.products.quick-setup.save-row") }}';
    const BATCH_SAVE_URL = '{{ route("admin.products.quick-setup.batch-save") }}';

    // Formatter angka standar Indonesia (tampilan)
    function formatNumber(num) {
        if (num === null || num === undefined || isNaN(num)) return '0';
        return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(num);
    }

    // Parser input angka tangguh: membersihkan titik ribuan dan format teks menjadi angka murni
    function parseNumericValue(val) {
        if (val === null || val === undefined) return 0;
        let str = String(val).trim();
        if (str === '') return 0;

        // Hilangkan spasi dan prefix Rp
        str = str.replace(/rp/gi, '').trim();

        // Tangani pemisah ribuan titik
        if (str.includes('.') && str.includes(',')) {
            str = str.replace(/\./g, '').replace(',', '.');
        } else if (str.includes('.') && !str.includes(',')) {
            const parts = str.split('.');
            if (parts.length > 2) {
                str = str.replace(/\./g, '');
            } else if (parts.length === 2 && parts[1].length === 3 && parseFloat(parts[0]) >= 1) {
                str = str.replace(/\./g, '');
            }
        } else if (str.includes(',') && !str.includes('.')) {
            const parts = str.split(',');
            if (parts.length > 2) {
                str = str.replace(/,/g, '');
            } else if (parts.length === 2 && parts[1].length === 3) {
                str = str.replace(/,/g, '');
            } else {
                str = str.replace(',', '.');
            }
        }

        // Ambil hanya angka, minus, dan titik
        str = str.replace(/[^0-9.-]/g, '');
        const num = parseFloat(str);
        return isNaN(num) ? 0 : num;
    }

    // Auto-formatting titik ribuan secara real-time dengan mempertahankan posisi kursor
    function handleThousandFormat(input) {
        const originalVal = input.value;
        const cursorPos = input.selectionStart || 0;
        
        // Hitung berapa banyak digit sebelum kursor
        const digitsBeforeCursor = originalVal.slice(0, cursorPos).replace(/\D/g, '').length;
        
        // Ambil hanya digit angka
        const rawDigits = originalVal.replace(/\D/g, '');
        if (rawDigits === '') {
            input.value = '';
            return;
        }

        // Format dengan titik setiap 3 digit
        const formatted = rawDigits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        input.value = formatted;

        // Hitung kembali posisi kursor agar tetap di belakang digit yang diketik
        let newCursorPos = 0;
        let countedDigits = 0;
        for (let i = 0; i < formatted.length; i++) {
            if (/\d/.test(formatted[i])) {
                countedDigits++;
            }
            if (countedDigits === digitsBeforeCursor) {
                newCursorPos = i + 1;
                break;
            }
        }

        if (countedDigits < digitsBeforeCursor || formatted === '') {
            newCursorPos = formatted.length;
        }

        try {
            input.setSelectionRange(newCursorPos, newCursorPos);
        } catch (e) {}
    }

    // Kalkulasi Konversi HPP & Stok per baris secara real-time
    function calculateRowConversions(row) {
        if (!row) return;

        // 1. Kalkulasi HPP per pcs: Nominal HPP / Faktor Satuan
        const hppUnitSelect = row.querySelector('.input-hpp-unit');
        const hppAmountInput = row.querySelector('.input-hpp-amount');
        if (hppUnitSelect && hppAmountInput) {
            const selectedOpt = hppUnitSelect.options[hppUnitSelect.selectedIndex];
            const hppFactor = selectedOpt ? (parseFloat(selectedOpt.getAttribute('data-factor')) || 1) : 1;
            const hppAmount = parseNumericValue(hppAmountInput.value);
            const hppPerPcs = hppFactor > 0 ? (hppAmount / hppFactor) : hppAmount;

            const valHppPcsEl = row.querySelector('.val-hpp-pcs');
            if (valHppPcsEl) {
                valHppPcsEl.textContent = formatNumber(hppPerPcs);
            }
        }

        // 2. Kalkulasi Stok Total pcs: Input Stok * Faktor Satuan
        const hasExistingStock = row.getAttribute('data-has-existing-stock') === '1';
        if (!hasExistingStock) {
            const stockUnitSelect = row.querySelector('.input-stock-unit');
            const stockQtyInput = row.querySelector('.input-stock-qty');
            if (stockUnitSelect && stockQtyInput) {
                const selectedOpt = stockUnitSelect.options[stockUnitSelect.selectedIndex];
                const stockFactor = selectedOpt ? (parseFloat(selectedOpt.getAttribute('data-factor')) || 1) : 1;
                const stockQty = parseNumericValue(stockQtyInput.value);
                const stockTotalPcs = stockQty * stockFactor;

                const valStockPcsEl = row.querySelector('.val-stock-pcs');
                if (valStockPcsEl) {
                    valStockPcsEl.textContent = formatNumber(stockTotalPcs);
                }
            }
        }
    }

    // Tandai baris dirty
    function markRowDirty(row) {
        if (!row) return;
        row.classList.add('is-dirty');
        updateDirtyCounter();
    }

    // Counter baris dirty
    function updateDirtyCounter() {
        const dirtyRows = document.querySelectorAll('tr.product-row.is-dirty');
        const badge = document.getElementById('dirty-badge');
        const counter = document.getElementById('dirty-count');

        if (dirtyRows.length > 0) {
            badge.classList.remove('d-none');
            counter.textContent = dirtyRows.length;
        } else {
            badge.classList.add('d-none');
            counter.textContent = '0';
        }
    }

    // Event Delegation untuk interaksi keyboard dan input
    document.addEventListener('DOMContentLoaded', function() {
        const table = document.getElementById('table-quick-setup');
        if (!table) return;

        // Tangani Backspace jika kursor berada tepat setelah titik pemisah ribuan
        table.addEventListener('keydown', function(e) {
            const target = e.target;
            if (target && target.matches('.format-thousand') && e.key === 'Backspace') {
                const pos = target.selectionStart;
                if (pos > 0 && target.value[pos - 1] === '.') {
                    e.preventDefault();
                    const val = target.value;
                    const newVal = val.slice(0, pos - 2) + val.slice(pos - 1);
                    target.value = newVal;
                    target.setSelectionRange(pos - 1, pos - 1);
                    target.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }
        });

        // Tangani input event: auto-format ribuan dan update konversi secara real-time
        ['input', 'keyup', 'change', 'paste'].forEach(function(eventType) {
            table.addEventListener(eventType, function(e) {
                const target = e.target;
                if (!target) return;

                const row = target.closest('tr.product-row');
                if (!row) return;

                // Format ribuan jika elemen bertipe format-thousand pada event input atau paste
                if (target.matches('.format-thousand') && (eventType === 'input' || eventType === 'paste')) {
                    handleThousandFormat(target);
                }

                if (target.matches('.input-hpp-amount, .input-hpp-unit, .input-stock-qty, .input-stock-unit')) {
                    calculateRowConversions(row);
                    markRowDirty(row);
                } else if (target.matches('.price-input-min, .price-input-rec, .price-input-max, .input-starts-at, .input-ends-at')) {
                    markRowDirty(row);
                } else if (target.matches('.unlimited-toggle')) {
                    onUnlimitedToggle(target);
                    markRowDirty(row);
                }
            });
        });
    });

    function onUnlimitedToggle(checkbox) {
        const row = checkbox.closest('tr.product-row');
        if (!row) return;
        const container = row.querySelector('.date-range-container');
        if (container) {
            if (checkbox.checked) {
                container.classList.add('d-none');
            } else {
                container.classList.remove('d-none');
            }
        }
    }

    function extractRowData(row) {
        const hppUnitSelect = row.querySelector('.input-hpp-unit');
        const stockUnitSelect = row.querySelector('.input-stock-unit');
        const unlimitedCb = row.querySelector('.unlimited-toggle');
        const startsAtInput = row.querySelector('.input-starts-at');
        const endsAtInput = row.querySelector('.input-ends-at');

        const prices = {};
        ['ring_retail', 'ring_b2b', 'ring_pos'].forEach(ringKey => {
            const cell = row.querySelector(`[data-ring="${ringKey}"]`);
            if (cell) {
                const minVal = cell.querySelector('.price-input-min')?.value || '';
                const recVal = cell.querySelector('.price-input-rec')?.value || '';
                const maxVal = cell.querySelector('.price-input-max')?.value || '';
                prices[ringKey] = {
                    min: minVal !== '' ? parseNumericValue(minVal) : null,
                    recommended: recVal !== '' ? parseNumericValue(recVal) : null,
                    max: maxVal !== '' ? parseNumericValue(maxVal) : null
                };
            }
        });

        return {
            product_id: parseInt(row.getAttribute('data-product-id')),
            work_location_id: WORK_LOCATION_ID,
            hpp_unit_id: parseInt(hppUnitSelect.value),
            hpp_amount: parseNumericValue(row.querySelector('.input-hpp-amount')?.value),
            stock_unit_id: parseInt(stockUnitSelect.value),
            stock_qty: parseNumericValue(row.querySelector('.input-stock-qty')?.value),
            prices: prices,
            unlimited: unlimitedCb ? (unlimitedCb.checked ? 1 : 0) : 1,
            starts_at: startsAtInput?.value || null,
            ends_at: unlimitedCb && unlimitedCb.checked ? null : (endsAtInput?.value || null),
        };
    }

    async function saveSingleRow(button) {
        const row = button.closest('tr.product-row');
        if (!row) return;

        const originalIcon = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        row.classList.add('is-saving');

        const payload = extractRowData(row);

        try {
            const response = await fetch(SAVE_ROW_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (response.ok && data.success) {
                row.classList.remove('is-dirty');
                row.classList.remove('is-saving');
                button.classList.remove('btn-light-primary');
                button.classList.add('btn-light-success');
                button.innerHTML = '<i class="ki-outline ki-check fs-3 text-success"></i>';

                // Perbarui status kelengkapan visual
                const badge = row.querySelector('.row-status-badge');
                if (badge) {
                    badge.classList.remove('badge-light-warning', 'text-warning');
                    badge.classList.add('badge-light-success', 'text-success');
                    badge.innerHTML = '<i class="ki-outline ki-check-circle text-success fs-9 me-1"></i> Lengkap';
                }

                // Jika stok awal diposting, lock input stok awal
                if (data.data.stock_initialized) {
                    row.setAttribute('data-has-existing-stock', '1');
                    const stockInput = row.querySelector('.input-stock-qty');
                    if (stockInput) {
                        stockInput.setAttribute('readonly', 'readonly');
                        stockInput.classList.add('bg-light', 'text-muted', 'border-dashed');
                    }
                    const stockUnitSelect = row.querySelector('.input-stock-unit');
                    if (stockUnitSelect) {
                        stockUnitSelect.setAttribute('disabled', 'disabled');
                    }
                    const convStockWrap = row.querySelector('.conversion-stock-wrap');
                    if (convStockWrap) {
                        convStockWrap.innerHTML = `
                            <div class="conversion-pill d-flex align-items-center justify-content-between bg-light-success border border-success border-opacity-25">
                                <span class="badge badge-light-success fs-9 text-success fw-bold p-0">
                                    <i class="ki-outline ki-lock fs-9 text-success me-1"></i> Terkunci
                                </span>
                                <span class="fs-8 fw-bolder text-success">
                                    ${formatNumber(data.data.initial_stock_base_qty)} <span class="text-muted fw-normal fs-9">pcs</span>
                                </span>
                            </div>`;
                    }
                }

                updateDirtyCounter();

                if (window.toastr) {
                    toastr.success(data.message || 'Perubahan berhasil disimpan!');
                }
            } else {
                throw new Error(data.message || (data.errors ? Object.values(data.errors).flat().join('\n') : 'Gagal menyimpan'));
            }
        } catch (error) {
            row.classList.remove('is-saving');
            button.classList.remove('btn-light-primary');
            button.classList.add('btn-light-danger');
            button.innerHTML = '<i class="ki-outline ki-cross fs-3 text-danger"></i>';

            if (window.Swal) {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Menyimpan',
                    text: error.message
                });
            } else {
                alert('Gagal: ' + error.message);
            }
        } finally {
            button.disabled = false;
            setTimeout(() => {
                button.classList.remove('btn-light-success', 'btn-light-danger');
                button.classList.add('btn-light-primary');
                button.innerHTML = originalIcon;
            }, 2500);
        }
    }

    async function saveAllRows() {
        const rowsToSave = document.querySelectorAll('tr.product-row.is-dirty');
        const allRows = rowsToSave.length > 0 ? rowsToSave : document.querySelectorAll('tr.product-row');

        if (allRows.length === 0) {
            if (window.toastr) toastr.info('Tidak ada baris yang dapat disimpan.');
            return;
        }

        const confirmMsg = rowsToSave.length > 0 
            ? `Simpan ${rowsToSave.length} baris produk yang telah Anda ubah?`
            : `Simpan seluruh ${allRows.length} produk di halaman ini?`;

        if (window.Swal) {
            const confirmResult = await Swal.fire({
                title: 'Konfirmasi Simpan',
                text: confirmMsg,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Simpan Semua',
                cancelButtonText: 'Batal'
            });
            if (!confirmResult.isConfirmed) return;
        } else {
            if (!confirm(confirmMsg)) return;
        }

        const saveAllBtn = document.getElementById('btn-save-all');
        const origBtnText = saveAllBtn.innerHTML;
        saveAllBtn.disabled = true;
        saveAllBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';

        const batchPayload = {
            work_location_id: WORK_LOCATION_ID,
            rows: []
        };

        allRows.forEach(row => {
            batchPayload.rows.push(extractRowData(row));
        });

        try {
            const response = await fetch(BATCH_SAVE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify(batchPayload)
            });

            const data = await response.json();

            if (response.ok && data.success) {
                allRows.forEach(row => {
                    row.classList.remove('is-dirty');
                    const badge = row.querySelector('.row-status-badge');
                    if (badge) {
                        badge.classList.remove('badge-light-warning', 'text-warning');
                        badge.classList.add('badge-light-success', 'text-success');
                        badge.innerHTML = '<i class="ki-outline ki-check-circle text-success fs-9 me-1"></i> Lengkap';
                    }
                });
                updateDirtyCounter();

                if (window.Swal) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: data.message,
                        timer: 2000
                    });
                }
                window.location.reload();
            } else {
                const errText = data.errors && data.errors.length > 0 ? data.errors.join('<br>') : (data.message || 'Gagal menyimpan batch.');
                if (window.Swal) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sebagian atau Semua Gagal',
                        html: errText
                    });
                } else {
                    alert('Gagal: ' + errText);
                }
            }
        } catch (error) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'error',
                    title: 'Kesalahan Sistem',
                    text: error.message
                });
            } else {
                alert('Error: ' + error.message);
            }
        } finally {
            saveAllBtn.disabled = false;
            saveAllBtn.innerHTML = origBtnText;
        }
    }
</script>
@endsection
