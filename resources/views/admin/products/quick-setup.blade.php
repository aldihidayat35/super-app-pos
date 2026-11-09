@extends('layouts.metronic.app')
@section('title', 'Setup Operasional Cepat Produk')
@section('page_title', 'Setup Operasional Cepat Produk')

@section('toolbar_actions')
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
            <p>Halaman ini memudahkan konfigurasi parameter operasional produk secara cepat dengan sistem <strong>Auto-Save otomatis</strong> dan proteksi <strong>1 Harga Jual Minimum (Anti-Rugi)</strong>. Setiap perubahan data otomatis tersimpan di latar belakang tanpa perlu klik tombol simpan manual.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol>
                <li>Pilih lokasi kerja gudang atau cabang toko di bagian atas jika memiliki banyak penugasan.</li>
                <li>Pada kolom <strong>HPP (Modal Beli)</strong>: tentukan satuan kulakan (misal Dus/Lusin/Pcs) dan ketik nominal HPP modal. Nilai HPP per pieces otomatis dihitung real-time.</li>
                <li>Pada kolom <strong>Stok Awal</strong>: tentukan satuan persediaan dan ketik jumlah stok fisik awal. Total pieces otomatis terakumulasi.</li>
                <li>Pada kolom <strong>Harga Jual Minimum</strong>: tentukan satuan penjualan dan ketik nominal harga jual minimal, atau klik <strong>Shortcut Margin (+10%, +20%, +30%, +50%)</strong> untuk menghitung harga jual secara instan.</li>
                <li>Sistem secara otomatis memeriksa margin keuntungan dan melakukan <strong>Auto-Save</strong> saat Anda selesai mengetik atau berpindah kolom.</li>
            </ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul>
                <li><strong>Kolom Produk &amp; Barcode:</strong> Nama produk, SKU, kategori, input scanner barcode, serta indikator status penyimpanan real-time.</li>
                <li><strong>Kolom HPP (Modal):</strong> Pilihan satuan kulakan + input HPP + konversi otomatis ke HPP per pcs.</li>
                <li><strong>Kolom Stok Awal:</strong> Pilihan satuan stok + input jumlah fisik awal + konversi total pieces (terkunci jika saldo sudah aktif).</li>
                <li><strong>Kolom Harga Jual Minimum:</strong> Pilihan satuan jual + input harga + tombol shortcut laba + badge proteksi anti-rugi.</li>
                <li><strong>Auto-Save Engine:</strong> Indikator status per baris (Menyimpan... &rarr; Tersimpan &rarr; Lengkap).</li>
            </ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Harga jual minimum yang disimpan langsung aktif untuk seluruh transaksi kasir POS, katalog retail, dan pesanan B2B tanpa risiko terjadi penjualan di bawah harga modal (HPP).</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol>
                <li>Gunakan tab filter <strong>Belum Lengkap</strong> untuk fokus menyelesaikan produk-produk baru.</li>
                <li>Gunakan tombol shortcut margin <code>+20%</code> atau <code>+30%</code> untuk mempercepat penetapan harga jual di atas HPP.</li>
                <li>Ketik angka bebas; pemisah ribuan titik akan terformat secara otomatis.</li>
            </ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0">
                <ul class="mb-0">
                    <li>Sistem memiliki <strong>proteksi anti-rugi 100%</strong>: Harga jual minimum dilarang lebih rendah dari HPP per pcs. Jika lebih rendah, penyimpanan otomatis diblokir dengan peringatan merah.</li>
                    <li>Stok awal dicatat satu kali sebagai saldo awal fisik pada lokasi kerja terpilih.</li>
                </ul>
            </div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Produk Kopi Sachet: HPP dipilih satuan Dus seharga Rp 120.000 (faktor 12 pcs &rarr; HPP Rp 10.000/pcs). Klik tombol shortcut <code>+20%</code>, maka Harga Jual Minimum per Pcs otomatis terisi Rp 12.000 (Laba: +Rp 2.000 / +20%). Data langsung tersimpan otomatis!</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
<style>
    .setup-mini-card {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 0.6rem 0.75rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .setup-mini-card:focus-within {
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        background-color: #ffffff;
    }
    .conversion-pill {
        border-radius: 0.375rem;
        padding: 0.3rem 0.5rem;
        font-size: 0.8rem;
        transition: all 0.15s ease-in-out;
    }
    .btn-margin-shortcut {
        border-radius: 0.35rem;
        padding: 2px 6px !important;
        font-size: 0.72rem !important;
        font-weight: 700 !important;
        line-height: 1.2;
        white-space: nowrap;
    }
    .btn-margin-shortcut:hover {
        transform: translateY(-1px);
    }
    .product-row {
        transition: background-color 0.3s ease, border-color 0.3s ease;
    }
    .product-row.row-saved-flash {
        background-color: rgba(16, 185, 129, 0.12) !important;
    }
    .product-row.row-error-flash {
        background-color: rgba(239, 68, 68, 0.08) !important;
    }
    .bg-light-success-subtle {
        background-color: rgba(16, 185, 129, 0.02);
    }
    .price-input-box.is-invalid {
        border-color: #ef4444 !important;
        background-color: #fef2f2 !important;
        color: #b91c1c !important;
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
                        <div class="text-muted fs-8">{{ $currentLocation->city ?: 'Operasional Aktif' }} · {{ $currentLocation->code }}</div>
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
                        <span class="fs-2hx fw-bolder text-warning" id="kpi-incomplete-count">{{ number_format($stats['incomplete_count'], 0, ',', '.') }}</span>
                        <span class="text-muted fs-7 ms-1">produk</span>
                    </div>
                    <div class="text-muted fs-8">Perlu dilengkapi HPP, Stok Awal, &amp; Harga Jual.</div>
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
                        <span class="fs-2hx fw-bolder text-success" id="kpi-complete-count">{{ number_format($stats['complete_count'], 0, ',', '.') }}</span>
                        <span class="text-muted fs-7 ms-1">produk</span>
                    </div>
                    <div class="text-muted fs-8">HPP, stok fisik, &amp; harga minimum siap transaksi.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Toolbar & Auto-Save Badge Header --}}
    <x-metronic.card>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
            {{-- Filter Form --}}
            <form method="GET" class="d-flex flex-wrap align-items-center gap-3">
                <input type="hidden" name="work_location_id" value="{{ $currentLocation->id }}">

                {{-- Status Kelengkapan Tabs --}}
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
                    <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm form-control-solid ps-9 w-200px" placeholder="Cari SKU, barcode, nama...">
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

            {{-- Status Auto-Save & Batch Action --}}
            <div class="d-flex align-items-center gap-3">
                <div class="d-flex align-items-center text-success bg-light-success border border-success border-opacity-25 rounded px-3 py-2 fs-8 fw-bold">
                    <i class="ki-outline ki-cloud-change fs-5 text-success me-2"></i>
                    <span>Auto-Save Aktif: Data otomatis tersimpan saat Anda selesai mengetik</span>
                </div>
                <button type="button" class="btn btn-sm btn-primary fw-bold" id="btn-save-all" onclick="saveAllRows()">
                    <i class="ki-outline ki-check fs-5 me-1"></i> Simpan Semua Baris
                </button>
            </div>
        </div>

        {{-- Tabel Utama Quick Setup (4 Kolom Lega & Responsif) --}}
        <div class="table-responsive border border-gray-200 rounded">
            <table class="table table-row-bordered table-row-gray-300 align-middle gs-4 gy-4 mb-0" id="table-quick-setup">
                <thead class="bg-light">
                    <tr class="fw-bolder text-gray-800 fs-8 text-uppercase">
                        {{-- Kolom 1: Produk & Barcode --}}
                        <th style="width: 27%; min-width: 250px;" class="ps-4">
                            <span class="text-gray-900 fw-bolder">Produk &amp; Barcode</span>
                            <div class="text-muted fs-9 fw-normal">Nama · SKU · Barcode Scanner</div>
                        </th>
                        {{-- Kolom 2: HPP (Modal Beli) --}}
                        <th style="width: 23%; min-width: 210px; background-color: rgba(var(--bs-primary-rgb), 0.02);">
                            <span class="text-primary fw-bolder">HPP (Modal Beli)</span>
                            <div class="text-muted fs-9 fw-normal">Satuan Kulakan &rarr; Modal per Pcs</div>
                        </th>
                        {{-- Kolom 3: Stok Awal --}}
                        <th style="width: 20%; min-width: 180px;">
                            <span class="text-gray-900 fw-bolder">Stok Awal Fisik</span>
                            <div class="text-muted fs-9 fw-normal">Satuan Stok &rarr; Total Pieces</div>
                        </th>
                        {{-- Kolom 4: Harga Jual Minimum (Anti-Rugi) --}}
                        <th style="width: 30%; min-width: 280px; background-color: rgba(var(--bs-success-rgb), 0.02);" class="pe-4">
                            <span class="text-success fw-bolder">Harga Jual Minimum (Anti-Rugi)</span>
                            <div class="text-muted fs-9 fw-normal">Satuan Jual · Shortcut Margin · Batas Anti-Rugi</div>
                        </th>
                    </tr>
                </thead>
                <tbody class="fs-7">
                    @forelse($products as $product)
                        @php
                            $baseUnit = $product->baseUnit ?? $units->firstWhere('code', 'PCS') ?? $units->first();
                            $pcsUnit = $units->firstWhere('code', 'PCS') ?? $baseUnit;

                            $retailPriceModel = $product->prices->firstWhere('channel', 'retail') 
                                ?? $product->prices->firstWhere('channel', 'pos')
                                ?? $product->prices->first();

                            $openingBatch = $product->stockBatches->first();
                            $hasExistingOpeningStock = (bool) $openingBatch;
                            $existingStockOnHand = $openingBatch ? (float) $openingBatch->quantity_on_hand : 0;

                            $productAttrs = (array) ($product->attributes ?? []);
                            $quickSetupAttrs = (array) ($productAttrs['quick_setup'] ?? []);
                            $locationsAttrs = (array) ($quickSetupAttrs['locations'] ?? []);
                            $qsData = $locationsAttrs[$currentLocation->id] ?? ($quickSetupAttrs['hpp_unit_id'] ?? null ? $quickSetupAttrs : null);

                            // 1. Tentukan Satuan HPP & Nominal HPP
                            $retailCostPrice = (float) ($product->cost_price ?? 0);
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
                                $defaultHppUnit = $baseUnit;
                                $defaultHppFactor = 1;
                                $defaultHppAmount = $retailCostPrice;
                            }

                            // 2. Tentukan Satuan Stok & Jumlah Stok
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
                                $defaultStockUnit = $baseUnit;
                                $defaultStockFactor = 1;
                                $defaultStockQty = $existingStockOnHand;
                            }

                            // 3. Tentukan Satuan Jual & Nominal Harga Jual Minimum
                            $retailMinPrice = (float) ($product->minimum_price > 0 ? $product->minimum_price : ($retailPriceModel?->recommended_price ?? 0));
                            if ($qsData && !empty($qsData['sell_unit_id']) && $units->contains('id', (int) $qsData['sell_unit_id'])) {
                                $defaultSellUnit = $units->firstWhere('id', (int) $qsData['sell_unit_id']);
                                $defaultSellFactor = match(strtoupper((string) $defaultSellUnit->code)) {
                                    'PCS' => 1,
                                    'LSN' => 12,
                                    'GROS' => 144,
                                    default => (float) ($product->units->firstWhere('unit_id', $defaultSellUnit->id)?->conversion_factor ?? 1),
                                };
                                $defaultSellAmount = isset($qsData['min_price_amount']) ? (float) $qsData['min_price_amount'] : ($retailMinPrice * $defaultSellFactor);
                            } else {
                                $defaultSellUnit = $baseUnit;
                                $defaultSellFactor = 1;
                                $defaultSellAmount = $retailMinPrice;
                            }

                            // Hitung margin keuntungan
                            $marginAmount = $retailMinPrice - $retailCostPrice;
                            $marginPercent = $retailCostPrice > 0 ? round(($marginAmount / $retailCostPrice) * 100, 1) : 0;

                            // Status Kelengkapan
                            $isComplete = $hasExistingOpeningStock && $retailMinPrice > 0 && $retailCostPrice > 0 && $retailMinPrice >= $retailCostPrice;
                        @endphp
                        <tr class="product-row {{ $isComplete ? 'bg-light-success-subtle' : '' }}" 
                            data-product-id="{{ $product->id }}" 
                            data-product-name="{{ $product->name }}"
                            data-has-existing-stock="{{ $hasExistingOpeningStock ? '1' : '0' }}"
                            data-can-save="1">
                            
                            {{-- 1. Data Produk & Barcode --}}
                            <td class="ps-4 align-top">
                                <div class="d-flex flex-column">
                                    <span class="fw-bolder text-gray-900 fs-6 line-clamp-2 mb-1" title="{{ $product->name }}">{{ $product->name }}</span>
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="badge badge-light-dark font-monospace fs-8 px-2 py-1">{{ $product->sku }}</span>
                                        <span class="text-muted fs-8">{{ $product->category?->name ?: 'Umum' }}</span>
                                    </div>
                                    <div class="mb-2" style="max-width: 220px;">
                                        <div class="input-group input-group-sm input-group-solid">
                                            <span class="input-group-text px-2 text-gray-500 py-1" title="Barcode Scanner Produk">
                                                <i class="ki-outline ki-barcode fs-6"></i>
                                            </span>
                                            <input type="text" 
                                                   class="form-control form-control-sm form-control-solid input-barcode font-monospace fs-8 py-1 px-2"
                                                   value="{{ $product->barcodes->firstWhere('is_primary', true)?->code ?? $product->barcodes->first()?->code ?? '' }}"
                                                   placeholder="Scan / ketik barcode"
                                                   autocomplete="off"
                                                   title="Barcode Produk (opsional & unik)">
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge {{ $isComplete ? 'badge-light-success text-success' : 'badge-light-warning text-warning' }} fs-9 fw-bold row-status-badge">
                                            <i class="ki-outline {{ $isComplete ? 'ki-check-circle text-success' : 'ki-cross-circle text-warning' }} fs-9 me-1"></i>
                                            <span class="status-text">{{ $isComplete ? 'Lengkap' : 'Belum Lengkap' }}</span>
                                        </span>
                                        <span class="row-save-status fs-9"></span>
                                    </div>
                                </div>
                            </td>

                            {{-- 2. HPP Produk (Modal Beli) --}}
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
                                            <label class="text-gray-600 fs-9 fw-bolder text-uppercase mb-1">Nominal HPP:</label>
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
                                    <div class="conversion-pill d-flex align-items-center justify-content-between bg-light-primary border border-primary border-opacity-25">
                                        <span class="fs-9 text-gray-700 fw-semibold">
                                            <i class="ki-outline ki-arrows-circle fs-9 text-primary me-1"></i>Modal per Pcs:
                                        </span>
                                        <span class="fs-8 fw-bolder text-primary">
                                            Rp <span class="val-hpp-pcs">{{ number_format($retailCostPrice, 0, ',', '.') }}</span><span class="text-muted fw-normal fs-9"> /pcs</span>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            {{-- 3. Stok Awal Fisik --}}
                            <td class="align-top">
                                <div class="setup-mini-card d-flex flex-column gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="d-flex flex-column" style="width: 85px;">
                                            <label class="text-gray-600 fs-9 fw-bolder text-uppercase mb-1">Satuan:</label>
                                            <select class="form-select form-select-sm form-select-solid input-stock-unit px-2 py-1 fs-8 fw-semibold" 
                                                    style="height: 32px;"
                                                    @disabled($hasExistingOpeningStock)>
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
                                            <label class="text-gray-600 fs-9 fw-bolder text-uppercase mb-1">Fisik Awal:</label>
                                            <input type="text" 
                                                   inputmode="numeric" 
                                                   class="form-control form-control-sm form-control-solid input-stock-qty format-thousand text-end fw-bold fs-7 py-1" 
                                                   style="height: 32px;"
                                                   value="{{ $defaultStockQty > 0 ? number_format($defaultStockQty, 0, ',', '.') : '' }}"
                                                   placeholder="0"
                                                   @disabled($hasExistingOpeningStock)>
                                        </div>
                                    </div>
                                    {{-- Hasil Konversi Real-Time Stok --}}
                                    @if($hasExistingOpeningStock)
                                        <div class="conversion-pill d-flex align-items-center justify-content-between bg-light-success border border-success border-opacity-25" title="Saldo stok awal sudah pernah dialokasikan sebelumnya.">
                                            <span class="badge badge-light-success fs-9 text-success fw-bold p-0">
                                                <i class="ki-outline ki-lock fs-9 text-success me-1"></i> Terkunci
                                            </span>
                                            <span class="fs-8 fw-bolder text-success">
                                                {{ number_format($existingStockOnHand, 0, ',', '.') }} <span class="text-muted fw-normal fs-9">pcs</span>
                                            </span>
                                        </div>
                                    @else
                                        <div class="conversion-pill d-flex align-items-center justify-content-between bg-light-secondary border border-gray-300">
                                            <span class="fs-9 text-gray-700 fw-semibold">
                                                <i class="ki-outline ki-arrows-circle fs-9 text-gray-500 me-1"></i>Total:
                                            </span>
                                            <span class="fs-8 fw-bolder text-gray-800">
                                                <span class="val-stock-pcs">{{ number_format($defaultStockQty * $defaultStockFactor, 0, ',', '.') }}</span><span class="text-muted fw-normal fs-9"> pcs</span>
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </td>

                            {{-- 4. Harga Jual Minimum (Anti-Rugi) --}}
                            <td class="align-top pe-4" style="background-color: rgba(var(--bs-success-rgb), 0.02);">
                                <div class="setup-mini-card d-flex flex-column gap-2 border-success border-opacity-25">
                                    {{-- Shortcut Margin Buttons --}}
                                    <div class="d-flex align-items-center justify-content-between gap-1 flex-nowrap">
                                        <span class="text-muted fs-9 fw-bolder text-uppercase text-nowrap">
                                            <i class="ki-outline ki-flash text-warning fs-8 me-0.5"></i>Margin:
                                        </span>
                                        <div class="d-flex align-items-center gap-1 flex-nowrap">
                                            <button type="button" class="btn btn-xs btn-light-success btn-margin-shortcut px-1.5 py-0.5" data-percent="10" title="Isi harga jual dengan margin +10% di atas HPP">+10%</button>
                                            <button type="button" class="btn btn-xs btn-light-success btn-margin-shortcut px-1.5 py-0.5" data-percent="20" title="Isi harga jual dengan margin +20% di atas HPP">+20%</button>
                                            <button type="button" class="btn btn-xs btn-light-success btn-margin-shortcut px-1.5 py-0.5" data-percent="30" title="Isi harga jual dengan margin +30% di atas HPP">+30%</button>
                                            <button type="button" class="btn btn-xs btn-light-success btn-margin-shortcut px-1.5 py-0.5" data-percent="50" title="Isi harga jual dengan margin +50% di atas HPP">+50%</button>
                                        </div>
                                    </div>

                                    {{-- Input Pilihan Satuan Jual & Nominal Harga Jual Minimum --}}
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="d-flex flex-column" style="width: 72px; min-width: 68px;">
                                            <label class="text-gray-600 fs-9 fw-bolder text-uppercase mb-0.5 text-nowrap">Satuan:</label>
                                            <select class="form-select form-select-sm form-select-solid input-sell-unit px-2 py-1 fs-8 fw-semibold" style="height: 32px;">
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
                                                            @selected($unit->id === $defaultSellUnit->id)>
                                                        {{ strtolower($unit->code) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="d-flex flex-column flex-grow-1 min-w-0">
                                            <label class="text-success fs-9 fw-bolder text-uppercase mb-0.5 text-nowrap text-truncate" title="Harga Jual Minimum">Harga Min:</label>
                                            <div class="input-group input-group-sm input-group-solid" style="height: 32px;">
                                                <span class="input-group-text px-2 fs-9 text-success fw-bold">Rp</span>
                                                <input type="text" 
                                                       inputmode="numeric" 
                                                       class="form-control form-control-sm form-control-solid input-price-amount price-input-box format-thousand text-end fw-bolder fs-7 py-1 px-2 text-success" 
                                                       value="{{ $defaultSellAmount > 0 ? number_format($defaultSellAmount, 0, ',', '.') : '' }}"
                                                       placeholder="0">
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Hasil Konversi Real-Time Harga per Pcs & Status Margin Anti-Rugi (Micro-Card 2 Tingkat Rapi & Anti-Wrap) --}}
                                    <div class="conversion-pill d-flex flex-column gap-1 bg-light-success border border-success border-opacity-25 px-2.5 py-1.5 conversion-price-pill">
                                        <div class="d-flex align-items-center justify-content-between text-nowrap">
                                            <span class="fs-9 text-gray-700 fw-semibold text-nowrap">
                                                <i class="ki-outline ki-tag fs-9 text-success me-0.5"></i>Harga /pcs:
                                            </span>
                                            <span class="fs-8 fw-bolder text-success text-nowrap">
                                                Rp&nbsp;<span class="val-price-pcs">{{ number_format($retailMinPrice, 0, ',', '.') }}</span>
                                            </span>
                                        </div>
                                        <div class="margin-indicator w-100">
                                            @if($retailMinPrice > 0 && $retailCostPrice > 0)
                                                @if($retailMinPrice < $retailCostPrice)
                                                    <span class="badge badge-light-danger text-danger fs-9 fw-bold w-100 text-center py-1 text-truncate d-flex align-items-center justify-content-center">
                                                        <i class="ki-outline ki-cross-circle fs-9 text-danger me-1"></i> Di bawah Modal!
                                                    </span>
                                                @else
                                                    <span class="badge badge-light-success text-success fs-9 fw-bold w-100 text-center py-1 text-truncate d-flex align-items-center justify-content-center">
                                                        <i class="ki-outline ki-arrow-up-right fs-9 text-success me-1"></i> Untung +Rp {{ number_format($marginAmount, 0, ',', '.') }} (+{{ $marginPercent }}%)
                                                    </span>
                                                @endif
                                            @else
                                                <span class="text-muted fs-9 text-center w-100 d-block">Tentukan harga</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-12">
                                <div class="d-flex flex-column align-items-center justify-content-center">
                                    <i class="ki-outline ki-cube-3 fs-3tx text-muted mb-3"></i>
                                    <h5 class="fw-bolder text-gray-700 mb-1">Tidak ada produk yang perlu disetup</h5>
                                    <span class="text-muted fs-7 mb-4">
                                        @if($filters['completeness'] === 'incomplete')
                                            Seluruh produk pada lokasi kerja ini telah lengkap terkonfigurasi.
                                        @else
                                            Tidak ditemukan data produk dengan filter yang Anda gunakan.
                                        @endif
                                    </span>
                                    <a href="{{ route('admin.products.import.index') }}" class="btn btn-sm btn-primary">
                                        <i class="ki-outline ki-file-up me-1"></i> Import Produk Baru
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-4 pt-3 border-top border-gray-100">
            <div class="text-muted fs-8">
                Menampilkan <strong>{{ $products->firstItem() ?? 0 }}</strong> sampai <strong>{{ $products->lastItem() ?? 0 }}</strong> dari <strong>{{ $products->total() }}</strong> produk
            </div>
            <div>
                {{ $products->links() }}
            </div>
        </div>
    </x-metronic.card>
</div>

@push('scripts')
<script>
(function() {
    'use strict';

    // Format angka ke format ribuan Indonesia
    function formatNumber(val) {
        if (val === null || val === undefined || isNaN(val)) return '0';
        return Math.round(val).toLocaleString('id-ID');
    }

    // Parser input angka tangguh: membersihkan titik ribuan dan format teks menjadi angka murni
    function parseNumericValue(val) {
        if (val === null || val === undefined) return 0;
        let str = String(val).trim();
        if (str === '') return 0;

        str = str.replace(/rp/gi, '').trim();

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

        str = str.replace(/[^0-9.-]/g, '');
        const num = parseFloat(str);
        return isNaN(num) ? 0 : num;
    }

    // Auto-formatting titik ribuan secara real-time
    function handleThousandFormat(input) {
        const originalVal = input.value;
        const cursorPos = input.selectionStart || 0;
        
        const digitsBeforeCursor = originalVal.slice(0, cursorPos).replace(/\D/g, '').length;
        const rawDigits = originalVal.replace(/\D/g, '');
        if (rawDigits === '') {
            input.value = '';
            return;
        }

        const formatted = rawDigits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        input.value = formatted;

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

    // Kalkulasi Real-Time HPP, Stok, Harga Jual Minimum, dan Margin per Baris
    function calculateRowConversions(row) {
        if (!row) return;

        // 1. HPP per pcs
        const hppUnitSelect = row.querySelector('.input-hpp-unit');
        const hppAmountInput = row.querySelector('.input-hpp-amount');
        let hppPerPcs = 0;
        if (hppUnitSelect && hppAmountInput) {
            const selectedOpt = hppUnitSelect.options[hppUnitSelect.selectedIndex];
            const hppFactor = selectedOpt ? (parseFloat(selectedOpt.getAttribute('data-factor')) || 1) : 1;
            const hppAmount = parseNumericValue(hppAmountInput.value);
            hppPerPcs = hppFactor > 0 ? (hppAmount / hppFactor) : hppAmount;

            const valHppPcsEl = row.querySelector('.val-hpp-pcs');
            if (valHppPcsEl) {
                valHppPcsEl.textContent = formatNumber(hppPerPcs);
            }
        }

        // 2. Stok Total pcs
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

        // 3. Harga Jual Minimum per pcs & Validasi Anti-Rugi
        const sellUnitSelect = row.querySelector('.input-sell-unit');
        const priceAmountInput = row.querySelector('.input-price-amount');
        let minPricePerPcs = 0;
        let sellAmount = 0;

        if (sellUnitSelect && priceAmountInput) {
            const selectedOpt = sellUnitSelect.options[sellUnitSelect.selectedIndex];
            const sellFactor = selectedOpt ? (parseFloat(selectedOpt.getAttribute('data-factor')) || 1) : 1;
            sellAmount = parseNumericValue(priceAmountInput.value);
            minPricePerPcs = sellFactor > 0 ? (sellAmount / sellFactor) : sellAmount;

            const valPricePcsEl = row.querySelector('.val-price-pcs');
            if (valPricePcsEl) {
                valPricePcsEl.textContent = formatNumber(minPricePerPcs);
            }

            const marginIndicator = row.querySelector('.margin-indicator');
            const pricePill = row.querySelector('.conversion-price-pill');
            const rowSaveStatus = row.querySelector('.row-save-status');

            if (sellAmount > 0 && hppPerPcs > 0) {
                const marginAmount = minPricePerPcs - hppPerPcs;
                const marginPercent = ((marginAmount / hppPerPcs) * 100).toFixed(1);

                if (minPricePerPcs < hppPerPcs) {
                    // Peringatan Anti-Rugi (Di bawah modal)
                    priceAmountInput.classList.add('is-invalid');
                    if (pricePill) {
                        pricePill.className = 'conversion-pill d-flex flex-column gap-1 bg-light-danger border border-danger border-opacity-50 px-2.5 py-1.5 conversion-price-pill';
                    }
                    if (marginIndicator) {
                        marginIndicator.innerHTML = `
                            <span class="badge badge-danger text-white fs-9 fw-bold w-100 text-center py-1 text-truncate d-flex align-items-center justify-content-center">
                                <i class="ki-outline ki-cross-circle fs-9 text-white me-1"></i> Rugi Rp ${formatNumber(Math.abs(marginAmount))} (${marginPercent}%)
                            </span>
                        `;
                    }
                    if (rowSaveStatus) {
                        rowSaveStatus.innerHTML = '<span class="text-danger fs-9 fw-bold"><i class="ki-outline ki-information-2 text-danger"></i> Dilarang di bawah HPP!</span>';
                    }
                    row.setAttribute('data-can-save', '0');
                } else {
                    // Margin Aman & Menguntungkan
                    priceAmountInput.classList.remove('is-invalid');
                    if (pricePill) {
                        pricePill.className = 'conversion-pill d-flex flex-column gap-1 bg-light-success border border-success border-opacity-25 px-2.5 py-1.5 conversion-price-pill';
                    }
                    if (marginIndicator) {
                        marginIndicator.innerHTML = `
                            <span class="badge badge-light-success text-success fs-9 fw-bold w-100 text-center py-1 text-truncate d-flex align-items-center justify-content-center">
                                <i class="ki-outline ki-arrow-up-right fs-9 text-success me-1"></i> Untung +Rp ${formatNumber(marginAmount)} (+${marginPercent}%)
                            </span>
                        `;
                    }
                    row.setAttribute('data-can-save', '1');
                }
            } else if (sellAmount === 0) {
                priceAmountInput.classList.remove('is-invalid');
                if (pricePill) {
                    pricePill.className = 'conversion-pill d-flex flex-column gap-1 bg-light-success border border-success border-opacity-25 px-2.5 py-1.5 conversion-price-pill';
                }
                if (marginIndicator) {
                    marginIndicator.innerHTML = '<span class="text-muted fs-9 text-center w-100 d-block">Tentukan harga</span>';
                }
                row.setAttribute('data-can-save', '1');
            }
        }
    }

    // Map auto-save timers per baris untuk debounce
    const autoSaveTimers = new Map();

    // Fungsi Pengiriman Auto-Save via AJAX
    function autoSaveRow(row) {
        if (!row) return;

        const canSave = row.getAttribute('data-can-save') !== '0';
        const rowSaveStatus = row.querySelector('.row-save-status');

        if (!canSave) {
            if (rowSaveStatus) {
                rowSaveStatus.innerHTML = '<span class="text-danger fs-9 fw-bold"><i class="ki-outline ki-information-2 text-danger"></i> Dilarang di bawah HPP!</span>';
            }
            return;
        }

        const productId = row.getAttribute('data-product-id');
        const barcodeInput = row.querySelector('.input-barcode');
        const hppUnitSelect = row.querySelector('.input-hpp-unit');
        const hppAmountInput = row.querySelector('.input-hpp-amount');
        const stockUnitSelect = row.querySelector('.input-stock-unit');
        const stockQtyInput = row.querySelector('.input-stock-qty');
        const sellUnitSelect = row.querySelector('.input-sell-unit');
        const priceAmountInput = row.querySelector('.input-price-amount');

        const payload = {
            work_location_id: {{ $currentLocation->id }},
            product_id: parseInt(productId, 10),
            barcode: barcodeInput ? barcodeInput.value.trim() : null,
            hpp_unit_id: hppUnitSelect ? parseInt(hppUnitSelect.value, 10) : null,
            hpp_amount: hppAmountInput ? parseNumericValue(hppAmountInput.value) : 0,
            stock_unit_id: stockUnitSelect ? parseInt(stockUnitSelect.value, 10) : null,
            stock_qty: stockQtyInput ? parseNumericValue(stockQtyInput.value) : 0,
            sell_unit_id: sellUnitSelect ? parseInt(sellUnitSelect.value, 10) : null,
            min_price_amount: priceAmountInput ? parseNumericValue(priceAmountInput.value) : 0,
            _token: '{{ csrf_token() }}'
        };

        if (rowSaveStatus) {
            rowSaveStatus.innerHTML = '<span class="spinner-border spinner-border-sm text-primary me-1"></span><span class="text-muted fs-9">Menyimpan...</span>';
        }

        fetch('{{ route("admin.products.quick-setup.save-row") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        })
        .then(async response => {
            const data = await response.json();
            if (!response.ok) {
                const errorMsg = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Gagal menyimpan.');
                throw new Error(errorMsg);
            }
            return data;
        })
        .then(result => {
            if (result.success) {
                if (rowSaveStatus) {
                    rowSaveStatus.innerHTML = '<span class="badge badge-light-success text-success fs-9 fw-bold"><i class="ki-outline ki-check-circle text-success me-1"></i> Tersimpan</span>';
                }

                // Efek visual sukses
                row.classList.add('row-saved-flash');
                setTimeout(() => row.classList.remove('row-saved-flash'), 1200);

                // Update badge status lengkap / belum lengkap
                const statusBadge = row.querySelector('.row-status-badge');
                const statusText = row.querySelector('.status-text');
                const isComplete = result.data?.is_complete;

                if (statusBadge && statusText) {
                    if (isComplete) {
                        statusBadge.className = 'badge badge-light-success text-success fs-9 fw-bold row-status-badge';
                        statusBadge.innerHTML = '<i class="ki-outline ki-check-circle text-success fs-9 me-1"></i><span class="status-text">Lengkap</span>';
                        row.classList.add('bg-light-success-subtle');
                    } else {
                        statusBadge.className = 'badge badge-light-warning text-warning fs-9 fw-bold row-status-badge';
                        statusBadge.innerHTML = '<i class="ki-outline ki-cross-circle text-warning fs-9 me-1"></i><span class="status-text">Belum Lengkap</span>';
                        row.classList.remove('bg-light-success-subtle');
                    }
                }
            }
        })
        .catch(err => {
            if (rowSaveStatus) {
                rowSaveStatus.innerHTML = `<span class="badge badge-light-danger text-danger fs-9 fw-bold" title="${err.message}"><i class="ki-outline ki-cross-circle text-danger me-1"></i> Gagal: ${err.message}</span>`;
            }
            row.classList.add('row-error-flash');
            setTimeout(() => row.classList.remove('row-error-flash'), 1800);
        });
    }

    // Trigger auto-save dengan debounce
    function triggerAutoSave(row, delay = 600) {
        if (!row) return;
        const productId = row.getAttribute('data-product-id');

        if (autoSaveTimers.has(productId)) {
            clearTimeout(autoSaveTimers.get(productId));
        }

        const timer = setTimeout(() => {
            autoSaveTimers.delete(productId);
            autoSaveRow(row);
        }, delay);

        autoSaveTimers.set(productId, timer);
    }

    // Inisialisasi event listener
    document.addEventListener('DOMContentLoaded', function() {
        const table = document.getElementById('table-quick-setup');
        if (!table) return;

        // Tangani input ketik dan format ribuan
        table.addEventListener('input', function(e) {
            const target = e.target;
            const row = target.closest('tr.product-row');
            if (!row) return;

            if (target.matches('.format-thousand')) {
                handleThousandFormat(target);
            }

            calculateRowConversions(row);
            triggerAutoSave(row, 650);
        });

        // Tangani perubahan dropdown satuan (segera simpan)
        table.addEventListener('change', function(e) {
            const target = e.target;
            const row = target.closest('tr.product-row');
            if (!row) return;

            calculateRowConversions(row);
            triggerAutoSave(row, 100);
        });

        // Tangani blur/keluar dari kolom input (segera simpan)
        table.addEventListener('focusout', function(e) {
            const target = e.target;
            const row = target.closest('tr.product-row');
            if (!row) return;

            calculateRowConversions(row);
            triggerAutoSave(row, 50);
        });

        // Tangani Tombol Shortcut Margin (+10%, +20%, +30%, +50%)
        table.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-margin-shortcut');
            if (!btn) return;

            e.preventDefault();
            const row = btn.closest('tr.product-row');
            if (!row) return;

            const percent = parseFloat(btn.getAttribute('data-percent')) || 0;

            const hppUnitSelect = row.querySelector('.input-hpp-unit');
            const hppAmountInput = row.querySelector('.input-hpp-amount');
            const sellUnitSelect = row.querySelector('.input-sell-unit');
            const priceAmountInput = row.querySelector('.input-price-amount');

            if (!hppUnitSelect || !hppAmountInput || !sellUnitSelect || !priceAmountInput) return;

            const selectedHppOpt = hppUnitSelect.options[hppUnitSelect.selectedIndex];
            const hppFactor = selectedHppOpt ? (parseFloat(selectedHppOpt.getAttribute('data-factor')) || 1) : 1;
            const hppAmount = parseNumericValue(hppAmountInput.value);
            const hppPerPcs = hppFactor > 0 ? (hppAmount / hppFactor) : hppAmount;

            if (hppPerPcs <= 0) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Nominal HPP Belum Diisi',
                        text: 'Silakan isi nominal HPP terlebih dahulu sebelum menerapkan shortcut persentase keuntungan.',
                        icon: 'info',
                        confirmButtonText: 'Mengerti'
                    });
                } else {
                    alert('Silakan isi nominal HPP terlebih dahulu.');
                }
                hppAmountInput.focus();
                return;
            }

            const selectedSellOpt = sellUnitSelect.options[sellUnitSelect.selectedIndex];
            const sellFactor = selectedSellOpt ? (parseFloat(selectedSellOpt.getAttribute('data-factor')) || 1) : 1;

            // Target harga per pcs = HPP/pcs * (1 + percent/100)
            const targetPricePerPcs = Math.ceil(hppPerPcs * (1 + (percent / 100)));
            // Target total harga = targetPricePerPcs * sellFactor
            const targetTotalAmount = Math.ceil(targetPricePerPcs * sellFactor);

            priceAmountInput.value = formatNumber(targetTotalAmount);

            calculateRowConversions(row);
            triggerAutoSave(row, 50);
        });

        // Hitung kalkulasi awal untuk seluruh baris
        document.querySelectorAll('tr.product-row').forEach(row => {
            calculateRowConversions(row);
        });
    });

    // Simpan Semua Baris Sekaligus (Batch Save Manual jika diinginkan)
    window.saveAllRows = function() {
        const rows = document.querySelectorAll('tr.product-row');
        if (rows.length === 0) return;

        let hasError = false;
        rows.forEach(row => {
            calculateRowConversions(row);
            if (row.getAttribute('data-can-save') === '0') {
                hasError = true;
            }
        });

        if (hasError) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Terdapat Harga di Bawah Modal!',
                    text: 'Periksa kembali baris bertanda merah. Harga jual minimum dilarang lebih rendah dari HPP per pcs.',
                    icon: 'warning',
                    confirmButtonText: 'Periksa'
                });
            } else {
                alert('Terdapat harga di bawah modal. Periksa baris bertanda merah.');
            }
            return;
        }

        const btnSaveAll = document.getElementById('btn-save-all');
        if (btnSaveAll) {
            btnSaveAll.disabled = true;
            btnSaveAll.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan Semua Baris...';
        }

        rows.forEach(row => {
            autoSaveRow(row);
        });

        setTimeout(() => {
            if (btnSaveAll) {
                btnSaveAll.disabled = false;
                btnSaveAll.innerHTML = '<i class="ki-outline ki-check fs-5 me-1"></i> Simpan Semua Baris';
            }
        }, 1500);
    };

})();
</script>
@endpush
@endsection
