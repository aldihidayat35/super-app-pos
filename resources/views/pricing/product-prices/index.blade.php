@extends('layouts.metronic.app')

@section('title', 'Harga Produk per Ring & Lokasi - ' . config('app.name'))
@section('page_title', 'Harga Produk per Ring & Lokasi')

@section('page_guide')
    <x-metronic.page-guide
        id="product-prices"
        title="Panduan Modul Setting Harga Produk"
        description="Atur harga jual produk per lokasi untuk 3 ring harga (Retail, B2B, POS) dengan rincian HPP bertingkat tanpa proses approval.">
        <x-slot:function>
            Mengatur dan memperbarui harga produk aktif langsung per lokasi penugasan atau global untuk berbagai channel penjualan secara independen per produk.
        </x-slot:function>
        <x-slot:workflow>
            1. Pilih lokasi kerja (Super Admin bebas memilih; role lain terkunci ke lokasi penugasan).<br>
            2. Periksa informasi produk dan rincian HPP (termasuk tingkatan HPP bertingkat bila ada).<br>
            3. Masukkan nominal harga pada 3 Ring Harga (Retail, B2B, POS).<br>
            4. Atur masa berlaku (default Unlimited tanpa batas waktu).<br>
            5. Klik tombol "Simpan" pada baris produk bersangkutan untuk menyimpan langsung.
        </x-slot:workflow>
        <x-slot:parts>
            - <strong>Pilihan Lokasi</strong>: Membatasi harga hanya berlaku pada lokasi kerja aktif.<br>
            - <strong>Rincian HPP</strong>: Menampilkan lapisan biaya stok per produk agar penetapan harga tetap menghasilkan laba.<br>
            - <strong>3 Ring Harga</strong>: Ring Retail, Ring B2B, dan Ring POS.<br>
            - <strong>Masa Berlaku</strong>: Periode aktif harga (default tanpa batas waktu).<br>
            - <strong>Audit Log</strong>: Setiap perubahan harga otomatis dicatat lengkap bersama akun pengubah dan waktu transaksi.
        </x-slot:parts>
        <x-slot:impacts>
            Perubahan harga langsung berlaku seketika (tanpa antrian approval) pada POS, Retail, atau B2B sesuai lokasi kerja yang dipilih.
        </x-slot:impacts>
        <x-slot:operation>
            Cukup ubah nominal pada kolom ring harga yang diinginkan lalu tekan tombol <em>Simpan</em> di sisi kanan baris produk.
        </x-slot:operation>
        <x-slot:warnings>
            Pastikan harga jual berada di atas HPP untuk menjaga margin usaha. Pengguna yang menyimpan harga bertanggung jawab penuh atas nilai harga yang diinput.
        </x-slot:warnings>
        <x-slot:example>
            Produk Tumbler memiliki HPP Tingkat 1 Rp10.000 dan Tingkat 2 Rp12.000. Pengguna mengisi Ring Retail Rp16.000, Ring POS Rp16.000, dan Ring B2B Rp14.000 lalu menekan Simpan.
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <a href="{{ route('pricing.history.index') }}" class="btn btn-sm btn-light-info me-2">
        <i class="ki-outline ki-time fs-5 me-1"></i> Riwayat Audit Harga
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
    /* Styling Ring Prices Table */
    .table-pricing th { vertical-align: middle; }
    .table-pricing td { vertical-align: middle; }
    .ring-price-cell { min-width: 155px; max-width: 195px; }
    .ring-input-wrap {
        display: flex;
        align-items: center;
        gap: 3px;
        margin-bottom: 2px;
    }
    .ring-input-wrap:last-child {
        margin-bottom: 0;
    }
    .ring-lbl {
        font-size: 10px;
        font-weight: 700;
        width: 32px;
        text-align: right;
        flex-shrink: 0;
        user-select: none;
    }
    .ring-input-box {
        height: 25px;
        font-size: 11px;
        padding: 1px 6px;
    }
    .ring-input-box.is-recommended {
        height: 27px;
        font-weight: 700;
        background-color: #fff;
        border-color: var(--bs-primary);
        box-shadow: 0 0 0 1px rgba(var(--bs-primary-rgb), 0.15);
    }
    .ring-input-box.is-recommended:focus {
        border-color: var(--bs-primary);
        box-shadow: 0 0 0 0.15rem rgba(var(--bs-primary-rgb), 0.2);
    }
    .row-saved-flash {
        animation: rowFlash 1.5s ease-out;
    }
    @keyframes rowFlash {
        0% { background-color: rgba(40, 167, 69, 0.25); }
        100% { background-color: transparent; }
    }
    .w-fit { width: fit-content; }

    /* Modern Product Picker (Multi-Select Dropdown with Search for Bulk Drawer) */
    :root {
        --pp-primary-rgb: var(--bs-primary-rgb, 13, 110, 253);
    }
    .pp-picker-container { position: relative; width: 100%; z-index: 1050; }
    .pp-trigger {
        position: relative; width: 100%; min-height: 44px;
        background: var(--bs-gray-100); border: 1px solid var(--bs-gray-300);
        border-radius: .475rem; padding: .35rem .75rem;
        display: flex; flex-wrap: wrap; align-items: center; gap: .3rem;
        cursor: text; transition: border-color .2s, box-shadow .2s, background .15s;
    }
    .pp-trigger:hover { border-color: var(--bs-gray-400); background: #fff; }
    .pp-trigger:focus-within, .pp-trigger.open {
        border-color: var(--bs-primary); background: #fff;
        box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .15);
    }
    .pp-arrow {
        position: absolute; right: .75rem; top: 50%; transform: translateY(-50%);
        color: var(--bs-gray-500); transition: transform .2s, color .2s;
        pointer-events: none; z-index: 2;
    }
    .pp-trigger.open .pp-arrow { transform: translateY(-50%) rotate(180deg); color: var(--bs-primary); }
    .pp-chip {
        display: inline-flex; align-items: center; gap: .25rem;
        background: rgba(var(--bs-primary-rgb), .12); color: var(--bs-primary);
        border: 1px solid rgba(var(--bs-primary-rgb), .25);
        border-radius: .375rem; padding: .2rem .4rem .2rem .6rem;
        font-size: .8rem; font-weight: 500; white-space: nowrap; line-height: 1.4;
    }
    .pp-chip-remove {
        background: none; border: none; color: inherit; cursor: pointer;
        font-size: .9rem; line-height: 1; padding: 0 1px; opacity: .6;
    }
    .pp-chip-remove:hover { opacity: 1; color: var(--bs-danger); }
    .pp-native-select { position: absolute; clip: rect(0,0,0,0); pointer-events: none; }
    .pp-placeholder { color: var(--bs-gray-400); font-size: .88rem; pointer-events: none; user-select: none; flex: 1; }
    .pp-dropdown {
        position: absolute; top: calc(100% + 6px); left: 0; right: 0;
        background: #fff; border: 1px solid var(--bs-gray-300);
        border-radius: .5rem; box-shadow: 0 .5rem 1rem rgba(0,0,0,.12);
        z-index: 1060; display: none; overflow: hidden;
    }
    .pp-dropdown.show { display: block; }
    .pp-dropdown-header { padding: .6rem .75rem; border-bottom: 1px solid var(--bs-gray-200); position: sticky; top: 0; background: #fff; z-index: 1; }
    .pp-dropdown-search {
        width: 100%; border: 1px solid var(--bs-gray-300); border-radius: .4rem;
        padding: .5rem .75rem; font-size: .88rem; outline: none;
    }
    .pp-list-wrap { max-height: 280px; overflow-y: auto; }
    .pp-item {
        display: flex; align-items: center; gap: .6rem;
        padding: .5rem .75rem; cursor: pointer; border-bottom: 1px solid var(--bs-gray-100);
    }
    .pp-item:hover { background: var(--bs-gray-50); }
    .pp-item.selected { background: rgba(var(--bs-primary-rgb), .06); }
    .pp-item input[type="checkbox"] { accent-color: var(--bs-primary); width: 16px; height: 16px; }
    .pp-item-info { flex: 1; min-width: 0; }
    .pp-item-sku { font-size: .72rem; color: var(--bs-gray-500); font-family: monospace; }
    .pp-item-name { font-size: .85rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pp-footer {
        padding: .5rem .75rem; border-top: 1px solid var(--bs-gray-200);
        display: flex; align-items: center; justify-content: space-between;
        font-size: .75rem; color: var(--bs-gray-500); background: var(--bs-gray-50);
    }
    .pp-footer-btn {
        background: none; border: 1px solid var(--bs-gray-300); border-radius: .35rem;
        padding: .2rem .5rem; font-size: .72rem; cursor: pointer; color: var(--bs-gray-600);
    }
    .pp-counter {
        display: inline-flex; align-items: center; gap: .4rem;
        border: 1px solid var(--bs-gray-200); border-radius: .5rem;
        background: var(--bs-gray-100); padding: .25rem .6rem; font-size: .75rem;
        color: var(--bs-gray-500); margin-top: .4rem;
    }
    .pp-counter.has-items { background: rgba(var(--bs-primary-rgb), .08); color: var(--bs-primary); border-color: rgba(var(--bs-primary-rgb), .2); }
    .pp-counter-dot {
        background: var(--bs-gray-300); border-radius: 1rem;
        color: #fff; font-size: .68rem; font-weight: 700;
        min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center;
    }
    .pp-counter.has-items .pp-counter-dot { background: var(--bs-primary); }
</style>
@endpush

@section('content')
    <!-- 1. Collapsible Bulk Price Assignment (Preserved & Enhanced) -->
    <div class="collapse mb-6" id="bulkPriceCollapse">
        <x-metronic.card title="Input Ring Harga Masal (Banyak Produk Sekaligus)">
            <form method="POST" action="{{ route('pricing.product-prices.store') }}" class="row g-3 align-items-start" id="product-price-form">
                @csrf
                <div class="col-md-4">
                    <div class="fv-row mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label for="product_ids" class="form-label required mb-0">Pilih Produk</label>
                            <button type="button" class="btn btn-sm btn-light-primary py-1 px-3" data-bs-toggle="modal" data-bs-target="#visualCatalogModal" title="Pilih dari Katalog Visual">
                                <i class="ki-outline ki-element-11"></i> Katalog Visual
                            </button>
                        </div>
                        <div class="pp-picker-container w-100" data-product-picker="true">
                            <select id="product_ids" name="product_ids[]"
                                class="pp-native-select @error('product_ids') is-invalid @enderror"
                                data-control="native"
                                multiple required>
                                @foreach($allProducts as $p)
                                    <option value="{{ $p->id }}"
                                        data-sku="{{ $p->sku }}"
                                        data-name="{{ $p->name }}"
                                        data-category="{{ $p->category?->name ?? '' }}"
                                        data-image="{{ $p->main_image_url }}"
                                        @selected(in_array((string) $p->id, array_map('strval', old('product_ids', [])), true))>
                                        {{ $p->sku }} — {{ $p->name }}
                                    </option>
                                @endforeach
                            </select>

                            <div class="pp-trigger">
                                <span class="pp-placeholder">Cari dan pilih produk...</span>
                                <span class="pp-arrow"><i class="ki-outline ki-square-down fs-6"></i></span>
                            </div>

                            <div class="pp-dropdown">
                                <div class="pp-dropdown-header">
                                    <input type="text" class="pp-dropdown-search" placeholder="Cari berdasarkan SKU, nama, atau kategori...">
                                </div>
                                <div class="pp-list-wrap">
                                    <div class="pp-list-inner"></div>
                                </div>
                                <div class="pp-footer"></div>
                            </div>

                            <div class="pp-counter">
                                <span class="pp-counter-dot">0</span>
                                <span>Belum ada produk dipilih</span>
                            </div>
                        </div>
                        @error('product_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
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
                                <option value="{{ $branch->id }}" @selected((string) old('branch_id', ! $isSuperAdmin ? ($currentLocation ? \App\Models\Branch::where('work_location_id', $currentLocation->id)->value('id') : '') : '') === (string) $branch->id)>{{ $branch->code }} — {{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>
                </div>

                <div class="col-md-3">
                    <x-metronic.form-group name="recommended_price" label="Harga Jual" required>
                        <input type="number" step="any" min="0" name="recommended_price" class="form-control" placeholder="0" required>
                    </x-metronic.form-group>
                </div>
                <div class="col-md-3">
                    <x-metronic.form-group name="starts_at" label="Mulai Berlaku">
                        <input type="date" name="starts_at" value="{{ now()->toDateString() }}" class="form-control">
                    </x-metronic.form-group>
                </div>
                <div class="col-md-3">
                    <x-metronic.form-group name="ends_at" label="Selesai (Kosong = Unlimited)">
                        <input type="date" name="ends_at" class="form-control" placeholder="Unlimited">
                    </x-metronic.form-group>
                </div>
                <div class="col-md-3">
                    <x-metronic.form-group name="notes" label="Catatan / Alasan">
                        <input type="text" name="notes" class="form-control" placeholder="Update harga masal">
                    </x-metronic.form-group>
                </div>

                <div class="col-md-12 text-end">
                    <button type="submit" class="btn btn-primary" id="save-product-prices" disabled @cannot('prices.update') data-permission-disabled="true" @endcannot>
                        <span class="indicator-label"><i class="ki-outline ki-check fs-5 me-1"></i> Simpan Harga Masal</span>
                        <span class="indicator-progress">Menyimpan... <span class="spinner-border spinner-border-sm ms-2"></span></span>
                    </button>
                </div>
            </form>
        </x-metronic.card>
    </div>

    <!-- 2. Main Filter Card: Hak Akses & Pilihan Lokasi -->
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

    <!-- 3. Daftar Produk & Setting 4 Ring Harga (Tabel Per Baris) -->
    <x-metronic.card title="Daftar Harga Produk per Ring">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div class="text-muted fs-7">
                Menampilkan <strong>{{ $products->total() }}</strong> produk aktif.
                @if($currentLocation)
                    Harga saat ini diset untuk lokasi: <span class="badge badge-light-primary fw-bold">{{ $currentLocation->name }}</span>
                @else
                    Harga saat ini diset untuk: <span class="badge badge-light-secondary fw-bold">Semua Lokasi (Global)</span>
                @endif
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge badge-light-info fs-8"><i class="ki-outline ki-shield-tick fs-7 me-1"></i> Tanpa Approval (Langsung Aktif)</span>
                <span class="badge badge-light-success fs-8"><i class="ki-outline ki-document fs-7 me-1"></i> Otomatis Tercatat di Audit Log</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-300 align-middle gs-4 gy-4 table-pricing">
                <thead>
                    <tr class="fw-bold text-gray-600 text-uppercase fs-7 bg-light border-bottom border-gray-200">
                        <th class="ps-4 min-w-280px">Informasi Produk</th>
                        <th class="text-center min-w-160px" style="background-color: rgba(var(--bs-primary-rgb), 0.03);">
                            <span class="text-primary fw-bolder">Ring Retail</span>
                            <div class="text-muted fs-9 fw-normal">Min · Acuan · Max</div>
                        </th>
                        <th class="text-center min-w-160px">
                            <span class="text-dark fw-bolder">Ring B2B</span>
                            <div class="text-muted fs-9 fw-normal">Min · Acuan · Max</div>
                        </th>
                        <th class="text-center min-w-160px" style="background-color: rgba(var(--bs-success-rgb), 0.03);">
                            <span class="text-success fw-bolder">Ring POS</span>
                            <div class="text-muted fs-9 fw-normal">Min · Acuan · Max</div>
                        </th>
                        <th class="text-center min-w-130px">
                            <span class="fw-bolder">Masa Berlaku</span>
                            <div class="text-muted fs-9 fw-normal">Default: Unlimited</div>
                        </th>
                        <th class="text-end pe-4 min-w-100px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($products as $product)
                    @php($row = $itemsData[$product->id] ?? null)
                    <tr id="product-row-{{ $product->id }}" class="product-price-row" data-product-id="{{ $product->id }}">
                        <!-- Kolom Informasi Produk -->
                        <td class="ps-4">
                            <div class="d-flex align-items-start">
                                <div class="symbol symbol-40px me-3 flex-shrink-0 mt-1">
                                    <x-metronic.image-preview url="{{ $product->main_image_url }}" title="{{ $product->name }}" />
                                </div>
                                <div class="d-flex flex-column min-w-0">
                                    <a href="{{ route('admin.products.show', $product) }}" class="text-gray-900 fw-bold text-hover-primary fs-6 mb-1 text-truncate" title="{{ $product->name }}">
                                        {{ $product->name }}
                                    </a>
                                    <div class="d-flex align-items-center gap-1 flex-wrap fs-8 text-muted mb-1">
                                        <span class="badge badge-light-dark font-monospace py-0 px-2 fs-9">{{ $product->sku }}</span>
                                        @if($product->category)
                                             <span class="badge badge-light-secondary py-0 px-2 fs-9">{{ $product->category->name }}</span>
                                        @endif
                                        @if($product->baseUnit)
                                            <span class="text-gray-500 fs-9">Satuan: {{ $product->baseUnit->symbol }}</span>
                                        @endif
                                        @php($filledCount = (int) ($row['filled_rings_count'] ?? 0))
                                        @if($filledCount === 0)
                                            <span class="badge badge-light-danger py-0 px-2 fs-9 fw-semibold" title="Belum memiliki harga ring aktif"><i class="ki-outline ki-cross-circle fs-9 me-1"></i>0/3 Ring</span>
                                        @elseif($filledCount === 1)
                                            <span class="badge badge-light-warning py-0 px-2 fs-9 fw-semibold" title="Baru 1 dari 3 ring terisi"><i class="ki-outline ki-information fs-9 me-1"></i>1/3 Ring</span>
                                        @elseif($filledCount === 2)
                                            <span class="badge badge-light-info py-0 px-2 fs-9 fw-semibold" title="2 dari 3 ring terisi"><i class="ki-outline ki-information fs-9 me-1"></i>2/3 Ring</span>
                                        @else
                                            <span class="badge badge-light-success py-0 px-2 fs-9 fw-bold" title="Ketiga ring harga sudah terisi lengkap"><i class="ki-outline ki-check-circle fs-9 me-1"></i>3/3 Ring Lengkap</span>
                                        @endif
                                    </div>

                                    <!-- Tepat di bawah nama produk: Rincian HPP (termasuk HPP Bertingkat) -->
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

                        <!-- Ring 1: Retail -->
                        <td class="text-center" style="background-color: rgba(var(--bs-primary-rgb), 0.02);">
                            <div class="ring-price-cell mx-auto" data-ring="ring_retail">
                                <div class="ring-input-wrap" title="Batas Minimum Ring Retail">
                                    <span class="ring-lbl text-muted">Min</span>
                                    <div class="input-group input-group-sm flex-grow-1">
                                        <span class="input-group-text py-0 px-1 fs-9 text-muted">Rp</span>
                                        <input type="number" step="any" min="0" class="form-control form-control-sm text-end ring-input-box price-input-min"
                                            name="prices[ring_retail][min]"
                                            value="{{ $row['prices']['ring_retail']['min'] ?? '' }}"
                                            placeholder="{{ $row['prices']['ring_retail']['recommended'] ?? '0' }}"
                                            aria-label="Ring Retail Min">
                                    </div>
                                </div>
                                <div class="ring-input-wrap bg-light-primary rounded p-1" title="Harga Acuan/Rekomendasi Ring Retail (Muncul Otomatis di Kasir/Order)">
                                    <span class="ring-lbl text-primary">Acuan</span>
                                    <div class="input-group input-group-sm flex-grow-1">
                                        <span class="input-group-text py-0 px-1 fs-8 text-primary fw-bold">Rp</span>
                                        <input type="number" step="any" min="0" class="form-control form-control-sm text-end ring-input-box is-recommended price-input-rec"
                                            name="prices[ring_retail][recommended]"
                                            value="{{ $row['prices']['ring_retail']['recommended'] ?? '' }}"
                                            placeholder="0"
                                            aria-label="Ring Retail Rekomendasi">
                                    </div>
                                </div>
                                <div class="ring-input-wrap" title="Batas Maksimum Ring Retail">
                                    <span class="ring-lbl text-muted">Max</span>
                                    <div class="input-group input-group-sm flex-grow-1">
                                        <span class="input-group-text py-0 px-1 fs-9 text-muted">Rp</span>
                                        <input type="number" step="any" min="0" class="form-control form-control-sm text-end ring-input-box price-input-max"
                                            name="prices[ring_retail][max]"
                                            value="{{ $row['prices']['ring_retail']['max'] ?? '' }}"
                                            placeholder="{{ $row['prices']['ring_retail']['recommended'] ?? '0' }}"
                                            aria-label="Ring Retail Max">
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Ring 2: B2B -->
                        <td class="text-center">
                            <div class="ring-price-cell mx-auto" data-ring="ring_b2b">
                                <div class="ring-input-wrap" title="Batas Minimum Ring B2B">
                                    <span class="ring-lbl text-muted">Min</span>
                                    <div class="input-group input-group-sm flex-grow-1">
                                        <span class="input-group-text py-0 px-1 fs-9 text-muted">Rp</span>
                                        <input type="number" step="any" min="0" class="form-control form-control-sm text-end ring-input-box price-input-min"
                                            name="prices[ring_b2b][min]"
                                            value="{{ $row['prices']['ring_b2b']['min'] ?? '' }}"
                                            placeholder="{{ $row['prices']['ring_b2b']['recommended'] ?? '0' }}"
                                            aria-label="Ring B2B Min">
                                    </div>
                                </div>
                                <div class="ring-input-wrap bg-light-primary rounded p-1" title="Harga Acuan/Rekomendasi Ring B2B">
                                    <span class="ring-lbl text-primary">Acuan</span>
                                    <div class="input-group input-group-sm flex-grow-1">
                                        <span class="input-group-text py-0 px-1 fs-8 text-primary fw-bold">Rp</span>
                                        <input type="number" step="any" min="0" class="form-control form-control-sm text-end ring-input-box is-recommended price-input-rec"
                                            name="prices[ring_b2b][recommended]"
                                            value="{{ $row['prices']['ring_b2b']['recommended'] ?? '' }}"
                                            placeholder="0"
                                            aria-label="Ring B2B Rekomendasi">
                                    </div>
                                </div>
                                <div class="ring-input-wrap" title="Batas Maksimum Ring B2B">
                                    <span class="ring-lbl text-muted">Max</span>
                                    <div class="input-group input-group-sm flex-grow-1">
                                        <span class="input-group-text py-0 px-1 fs-9 text-muted">Rp</span>
                                        <input type="number" step="any" min="0" class="form-control form-control-sm text-end ring-input-box price-input-max"
                                            name="prices[ring_b2b][max]"
                                            value="{{ $row['prices']['ring_b2b']['max'] ?? '' }}"
                                            placeholder="{{ $row['prices']['ring_b2b']['recommended'] ?? '0' }}"
                                            aria-label="Ring B2B Max">
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Ring 3: POS -->
                        <td class="text-center" style="background-color: rgba(var(--bs-success-rgb), 0.02);">
                            <div class="ring-price-cell mx-auto" data-ring="ring_pos">
                                <div class="ring-input-wrap" title="Batas Minimum Ring POS">
                                    <span class="ring-lbl text-muted">Min</span>
                                    <div class="input-group input-group-sm flex-grow-1">
                                        <span class="input-group-text py-0 px-1 fs-9 text-muted">Rp</span>
                                        <input type="number" step="any" min="0" class="form-control form-control-sm text-end ring-input-box price-input-min"
                                            name="prices[ring_pos][min]"
                                            value="{{ $row['prices']['ring_pos']['min'] ?? '' }}"
                                            placeholder="{{ $row['prices']['ring_pos']['recommended'] ?? '0' }}"
                                            aria-label="Ring POS Min">
                                    </div>
                                </div>
                                <div class="ring-input-wrap bg-light-success rounded p-1" title="Harga Acuan/Rekomendasi Ring POS (Otomatis Masuk Keranjang Kasir)">
                                    <span class="ring-lbl text-success">Acuan</span>
                                    <div class="input-group input-group-sm flex-grow-1">
                                        <span class="input-group-text py-0 px-1 fs-8 text-success fw-bold">Rp</span>
                                        <input type="number" step="any" min="0" class="form-control form-control-sm text-end ring-input-box is-recommended price-input-rec"
                                            name="prices[ring_pos][recommended]"
                                            value="{{ $row['prices']['ring_pos']['recommended'] ?? '' }}"
                                            placeholder="0"
                                            aria-label="Ring POS Rekomendasi">
                                    </div>
                                </div>
                                <div class="ring-input-wrap" title="Batas Maksimum Ring POS">
                                    <span class="ring-lbl text-muted">Max</span>
                                    <div class="input-group input-group-sm flex-grow-1">
                                        <span class="input-group-text py-0 px-1 fs-9 text-muted">Rp</span>
                                        <input type="number" step="any" min="0" class="form-control form-control-sm text-end ring-input-box price-input-max"
                                            name="prices[ring_pos][max]"
                                            value="{{ $row['prices']['ring_pos']['max'] ?? '' }}"
                                            placeholder="{{ $row['prices']['ring_pos']['recommended'] ?? '0' }}"
                                            aria-label="Ring POS Max">
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Masa Berlaku: Default Unlimited -->
                        <td class="text-center">
                            <div class="d-flex flex-column align-items-center gap-1">
                                <div class="form-check form-check-custom form-check-solid form-check-sm">
                                    <input class="form-check-input unlimited-toggle" type="checkbox"
                                        id="unlimited_{{ $product->id }}"
                                        name="unlimited"
                                        value="1"
                                        @checked($row['is_unlimited'] ?? true)
                                        data-product-id="{{ $product->id }}">
                                    <label class="form-check-label fs-8 fw-semibold text-gray-700 cursor-pointer" for="unlimited_{{ $product->id }}">
                                        Unlimited
                                    </label>
                                </div>
                                <div id="dates-container-{{ $product->id }}" class="date-range-container d-flex flex-column gap-1 {{ ($row['is_unlimited'] ?? true) ? 'd-none' : '' }}" style="width: 120px;">
                                    <input type="date" name="starts_at" value="{{ $row['starts_at'] ?? now()->toDateString() }}" class="form-control form-control-sm fs-9 py-1 px-1" title="Mulai Berlaku">
                                    <input type="date" name="ends_at" value="{{ $row['ends_at'] ?? '' }}" class="form-control form-control-sm fs-9 py-1 px-1" placeholder="Selesai" title="Tanggal Selesai">
                                </div>
                            </div>
                        </td>

                        <!-- Tombol Aksi: Simpan Langsung Per Item -->
                        <td class="text-end pe-4">
                            <div class="d-flex align-items-center justify-content-end gap-1">
                                <button type="button" class="btn btn-sm btn-primary px-3 py-2 btn-save-row" data-product-id="{{ $product->id }}" title="Simpan perubahan harga untuk produk ini">
                                    <span class="indicator-label"><i class="ki-outline ki-check fs-6 me-1"></i> Simpan</span>
                                    <span class="indicator-progress"><span class="spinner-border spinner-border-sm"></span></span>
                                </button>
                                <a href="{{ route('pricing.history.index', ['product_id' => $product->id]) }}" class="btn btn-sm btn-light-info px-2 py-2" title="Lihat riwayat audit harga produk ini">
                                    <i class="ki-outline ki-time fs-6"></i>
                                </a>
                                @if(!empty($row['price_model']))
                                    <a href="{{ route('pricing.product-prices.edit', $row['price_model']) }}" class="btn btn-sm btn-light-secondary px-2 py-2" title="Kelola detail & histori">
                                        <i class="ki-outline ki-setting-2 fs-6"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <x-metronic.empty-state
                                title="Tidak ada produk ditemukan"
                                description="Ubah kata kunci pencarian atau pilih kategori lain." />
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

    <!-- Visual Catalog Modals for Bulk Picker -->
    <x-metronic.visual-catalog-modal 
        id="visualCatalogModal" 
        sourceSelector="select#product_ids option"
        on-select="handleCatalogSelect" 
    />
    <x-metronic.visual-catalog-modal 
        id="visualCatalogModalFilter" 
        sourceSelector="select#product_ids option"
        on-select="handleCatalogFilterSelect" 
    />
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const saveItemUrl = "{{ route('pricing.product-prices.save-item') }}";
        const selectedLocationId = @json($selectedLocationId);

        // ── 1. Toggle Unlimited Checkbox ──
        document.querySelectorAll('.unlimited-toggle').forEach(function (toggle) {
            toggle.addEventListener('change', function () {
                const productId = this.dataset.productId;
                const container = document.getElementById('dates-container-' + productId);
                if (container) {
                    if (this.checked) {
                        container.classList.add('d-none');
                    } else {
                        container.classList.remove('d-none');
                    }
                }
            });
        });

        // ── 2. Save Row Button Click (AJAX per-item save) ──
        document.querySelectorAll('.btn-save-row').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const productId = this.dataset.productId;
                const row = document.getElementById('product-row-' + productId);
                if (!row) return;

                const ringKeys = ['ring_retail', 'ring_b2b', 'ring_pos'];
                const pricesPayload = {};

                ringKeys.forEach(function (rKey) {
                    const minEl = row.querySelector(`input[name="prices[${rKey}][min]"]`);
                    const recEl = row.querySelector(`input[name="prices[${rKey}][recommended]"]`);
                    const maxEl = row.querySelector(`input[name="prices[${rKey}][max]"]`);

                    const minVal = minEl ? minEl.value.trim() : '';
                    const recVal = recEl ? recEl.value.trim() : '';
                    const maxVal = maxEl ? maxEl.value.trim() : '';

                    if (minVal !== '' || recVal !== '' || maxVal !== '') {
                        pricesPayload[rKey] = {
                            min: minVal !== '' ? minVal : null,
                            recommended: recVal !== '' ? recVal : null,
                            max: maxVal !== '' ? maxVal : null,
                        };
                    }
                });

                const unlimitedInput  = row.querySelector('input[name="unlimited"]');
                const startsAtInput   = row.querySelector('input[name="starts_at"]');
                const endsAtInput     = row.querySelector('input[name="ends_at"]');

                const payload = {
                    _token: csrfToken,
                    product_id: productId,
                    work_location_id: selectedLocationId,
                    prices: pricesPayload,
                    unlimited: unlimitedInput ? (unlimitedInput.checked ? 1 : 0) : 1,
                    starts_at: startsAtInput ? startsAtInput.value : null,
                    ends_at: (unlimitedInput && unlimitedInput.checked) ? null : (endsAtInput ? endsAtInput.value : null),
                };

                // Indicator on
                btn.setAttribute('data-kt-indicator', 'on');
                btn.disabled = true;

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
                        // Flash animation
                        row.classList.remove('row-saved-flash');
                        void row.offsetWidth; // Trigger reflow
                        row.classList.add('row-saved-flash');

                        const isWarning = Boolean(result.data.has_warning);
                        const alertIcon = isWarning ? 'warning' : 'success';
                        const alertTitle = isWarning ? 'Disimpan dengan Peringatan' : 'Berhasil Disimpan';

                        if (typeof toastr !== 'undefined') {
                            if (isWarning) {
                                toastr.warning(result.data.message || 'Harga berhasil disimpan dengan catatan.');
                            } else {
                                toastr.success(result.data.message || 'Harga berhasil disimpan.');
                            }
                        } else if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: alertIcon,
                                title: alertTitle,
                                text: result.data.message || 'Harga berhasil disimpan.',
                                timer: isWarning ? 4000 : 1500,
                                showConfirmButton: isWarning,
                            });
                        }
                    } else {
                        let errMsg = (result.data && result.data.message) ? result.data.message : 'Gagal menyimpan harga.';
                        if (result.data && result.data.errors) {
                            const firstKey = Object.keys(result.data.errors)[0];
                            if (firstKey && result.data.errors[firstKey][0]) {
                                errMsg = result.data.errors[firstKey][0];
                            }
                        }
                        if (typeof toastr !== 'undefined') {
                            toastr.error(errMsg);
                        } else if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', title: 'Terjadi Kesalahan', text: errMsg });
                        } else {
                            alert(errMsg);
                        }
                    }
                })
                .catch(function (err) {
                    btn.removeAttribute('data-kt-indicator');
                    btn.disabled = false;
                    console.error('Save error:', err);
                    if (typeof toastr !== 'undefined') {
                        toastr.error('Terjadi kesalahan jaringan.');
                    } else {
                        alert('Terjadi kesalahan jaringan.');
                    }
                });
            });
        });

        // ── 3. Pressing Enter inside table price inputs saves the row without submitting top filter form ──
        document.querySelectorAll('.product-price-row').forEach(function (row) {
            row.querySelectorAll('.ring-input-box, input[type="date"]').forEach(function (input) {
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        row.querySelector('.btn-save-row')?.click();
                    }
                });
            });

            // Auto placeholder synchronization: Saat mengetik Rekomendasi/Acuan, update placeholder Min & Max bila kosong
            row.querySelectorAll('.price-input-rec').forEach(function (recInput) {
                const ringWrap = recInput.closest('.ring-price-cell');
                if (!ringWrap) return;
                const minInput = ringWrap.querySelector('.price-input-min');
                const maxInput = ringWrap.querySelector('.price-input-max');

                recInput.addEventListener('input', function () {
                    const val = this.value.trim();
                    if (minInput && !minInput.value) {
                        minInput.placeholder = val ? val : '0';
                    }
                    if (maxInput && !maxInput.value) {
                        maxInput.placeholder = val ? val : '0';
                    }
                });
            });
        });

        // ── 4. Bulk Picker Initialization (Preserved for tests & drawer) ──
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
                        name: opt.dataset.name || opt.textContent.replace(opt.dataset.sku, '').replace(/^[\s—]+/,''),
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
                                <div class="pp-item-name">${escapeHtml(p.name)}${p.category ? ` <span class="text-muted fs-8">(${escapeHtml(p.category)})</span>` : ''}</div>
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
                    if (submitButton && !submitButton.dataset.permissionDisabled) submitButton.disabled = sel === 0;
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

    window.handleCatalogSelect = function(product) {
        const select = document.getElementById('product_ids');
        if (select) {
            const option = select.querySelector(`option[value="${product.id}"]`);
            if (option) {
                option.selected = true;
                select.dispatchEvent(new Event('change'));
                toastr && toastr.success(product.name + ' ditambahkan.');
            }
        }
    };

    window.handleCatalogFilterSelect = function(product) {
        const filterInput = document.querySelector('input[name="q"]');
        if (filterInput) {
            filterInput.value = product.name;
            filterInput.closest('form').submit();
        }
    };
})();
</script>
@endpush
