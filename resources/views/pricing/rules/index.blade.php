@extends('layouts.metronic.app')

@section('title', 'Aturan Harga dan Margin - ' . config('app.name'))
@section('page_title', 'Aturan Harga dan Margin')

@section('page_guide')
    <x-metronic.page-guide
        id="price-rules"
        title="Panduan Modul Aturan Harga dan Margin"
        description="Kebijakan dasar perhitungan margin minimum dan batasan diskon/toleransi harga otomatis per channel penjualan.">
        <x-slot:function>
            Mengatur kebijakan batas margin dasar, toleransi batas atas harga, dan batas diskon penjualan ketika produk belum diatur secara spesifik di menu Harga Produk.
        </x-slot:function>
        <x-slot:workflow>
            1. Periksa daftar aturan harga aktif berdasarkan channel dan prioritas.<br>
            2. Klik "+ Tambah Aturan" untuk menambahkan kebijakan baru sesuai channel (Retail, POS, B2B, atau Semua).<br>
            3. Tentukan metode margin: Persentase (%) atau Nominal Tetap (Rp) di atas HPP produk.<br>
            4. Atur batas toleransi kenaikan harga (overpricing) dan batas diskon maksimum kasir.<br>
            5. Simpan aturan. Aturan dengan prioritas terkecil akan dievaluasi terlebih dahulu saat transaksi.
        </x-slot:workflow>
        <x-slot:parts>
            - <strong>Ruang Lingkup (Scope)</strong>: Berlaku untuk channel tertentu (Retail, POS, B2B) atau Semua Channel, serta dapat dibatasi ke cabang spesifik.<br>
            - <strong>Margin Minimum</strong>: Nilai laba terendah yang wajib dipenuhi dari HPP produk (persentase atau nominal).<br>
            - <strong>Toleransi Overpricing</strong>: Persentase batas kenaikan harga wajar agar harga tidak melambung terlalu tinggi.<br>
            - <strong>Diskon Maksimum</strong>: Batas diskon kasir/penjualan tertinggi yang diizinkan sistem.<br>
            - <strong>Prioritas</strong>: Angka urutan evaluasi aturan (misal 10 lebih diprioritaskan dibanding 100).
        </x-slot:parts>
        <x-slot:impacts>
            Aturan ini berfungsi sebagai jaring pengaman (fallback otomatis). Transaksi penjualan POS dan Retail akan menggunakan margin dari aturan ini jika produk tidak memiliki harga khusus.
        </x-slot:impacts>
        <x-slot:operation>
            Gunakan tombol edit untuk menyesuaikan angka margin/diskon, tombol status untuk mengaktifkan/menonaktifkan aturan tanpa menghapus, atau tombol hapus bila sudah tidak berlaku.
        </x-slot:operation>
        <x-slot:warnings>
            Pastikan selalu ada minimal satu aturan aktif untuk 'Semua Channel' tanpa cabang sebagai jaring pengaman global sistem.
        </x-slot:warnings>
        <x-slot:example>
            Aturan 'POS Toko Default': Channel POS, Margin Min 25% dari HPP, Toleransi 50%, Diskon Maks 10%. Jika produk ber-HPP Rp 20.000, maka harga dasar adalah Rp 25.000 dengan toleransi s.d. Rp 37.500.
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <a href="{{ route('pricing.product-prices.index') }}" class="btn btn-sm btn-light-info me-2">
        <i class="ki-outline ki-price-tag fs-5 me-1"></i> Ke Harga Produk
    </a>
    <a href="{{ route('pricing.history.index') }}" class="btn btn-sm btn-light-secondary me-2">
        <i class="ki-outline ki-time fs-5 me-1"></i> Riwayat Audit
    </a>
    @can('create', App\Models\PriceRule::class)
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal_price_rule" id="btn-create-rule">
            <i class="ki-outline ki-plus fs-5 me-1"></i> Tambah Aturan Baru
        </button>
    @endcan
@endsection

@section('content')
    <div class="row g-6">
        <div class="col-12">
            {{-- Filter Toolbar --}}
            <div class="card card-flush mb-6">
                <div class="card-body py-4">
                    <form method="GET" action="{{ route('pricing.rules.index') }}" class="row g-3 align-items-center" id="filter-rule-form">
                        <div class="col-md-4 col-12">
                            <div class="position-relative">
                                <i class="ki-outline ki-magnifier fs-3 position-absolute top-50 translate-middle-y ms-4 text-gray-500"></i>
                                <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control form-control-solid ps-12" placeholder="Cari nama aturan, kategori, catatan...">
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <select name="channel" class="form-select form-select-solid" data-control="select2" data-hide-search="true">
                                <option value="all_channels" @selected($filters['channel'] === 'all_channels')>Semua Channel</option>
                                @foreach($channels as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['channel'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 col-6">
                            <select name="branch_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Semua Cabang" data-allow-clear="true">
                                <option value="">Semua Cabang</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((string) $filters['branch_id'] === (string) $branch->id)>{{ $branch->code }} — {{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 col-6">
                            <select name="status" class="form-select form-select-solid" data-control="select2" data-hide-search="true">
                                <option value="" @selected($filters['status'] === '')>Semua Status</option>
                                <option value="active" @selected($filters['status'] === 'active')>Aktif Saja</option>
                                <option value="inactive" @selected($filters['status'] === 'inactive')>Nonaktif Saja</option>
                            </select>
                        </div>
                        <div class="col-md-1 col-6 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-icon w-100" title="Terapkan Filter">
                                <i class="ki-outline ki-filter fs-4"></i>
                            </button>
                            @if($filters['q'] || ($filters['channel'] !== 'all_channels') || $filters['branch_id'] || $filters['status'])
                                <a href="{{ route('pricing.rules.index') }}" class="btn btn-light btn-icon" title="Reset Filter">
                                    <i class="ki-outline ki-cross fs-4"></i>
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            {{-- Main Table Card --}}
            <div class="card card-flush">
                <div class="card-header pt-6">
                    <div class="card-title">
                        <h3 class="fw-bold fs-4 mb-0">
                            Daftar Aturan Harga dan Kebijakan Margin
                            <span class="badge badge-light-primary fw-bold ms-2">{{ $rules->total() }} Aturan</span>
                        </h3>
                    </div>
                    <div class="card-toolbar">
                        <span class="text-muted fs-7">
                            <i class="ki-outline ki-shield-check text-success fs-6 me-1"></i> Jaring pengaman otomatis untuk POS & Penjualan Retail
                        </span>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle gs-4 gy-4">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                                    <th class="min-w-50px text-center">Prioritas</th>
                                    <th class="min-w-175px">Nama Aturan</th>
                                    <th class="min-w-150px">Ruang Lingkup (Scope)</th>
                                    <th class="min-w-140px">Margin Minimum</th>
                                    <th class="min-w-150px">Toleransi & Diskon</th>
                                    <th class="min-w-130px">Masa Berlaku</th>
                                    <th class="min-w-100px text-center">Status</th>
                                    <th class="min-w-100px text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="fw-semibold text-gray-700">
                                @forelse($rules as $rule)
                                    @php
                                        $rulePayload = [
                                            'id' => $rule->id,
                                            'name' => $rule->name,
                                            'channel' => $rule->channel,
                                            'branch_id' => $rule->branch_id,
                                            'customer_category' => $rule->customer_category,
                                            'margin_method' => $rule->margin_method,
                                            'minimum_margin_percent' => (string) $rule->minimum_margin_percent,
                                            'minimum_margin_amount' => (string) $rule->minimum_margin_amount,
                                            'overpricing_tolerance_percent' => (string) $rule->overpricing_tolerance_percent,
                                            'max_discount_percent' => (string) $rule->max_discount_percent,
                                            'priority' => $rule->priority,
                                            'starts_at' => $rule->starts_at?->toDateString(),
                                            'ends_at' => $rule->ends_at?->toDateString(),
                                            'is_active' => $rule->is_active ? 1 : 0,
                                            'notes' => $rule->notes,
                                        ];
                                    @endphp
                                    <tr class="{{ ! $rule->is_active ? 'bg-light-secondary opacity-75' : '' }}">
                                        {{-- Prioritas --}}
                                        <td class="text-center">
                                            <span class="badge badge-light-{{ $rule->priority <= 10 ? 'primary' : 'secondary' }} fw-bold fs-7 px-3 py-2">
                                                #{{ $rule->priority }}
                                            </span>
                                        </td>

                                        {{-- Nama Aturan --}}
                                        <td>
                                            <div class="fw-bold fs-6 text-gray-900">{{ $rule->name }}</div>
                                            @if($rule->notes)
                                                <div class="text-muted fs-8 text-truncate max-w-250px mt-1" title="{{ $rule->notes }}">
                                                    {{ $rule->notes }}
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Scope (Channel, Cabang, Kategori) --}}
                                        <td>
                                            @if($rule->channel === 'pos')
                                                <span class="badge badge-light-success fw-bold fs-8 px-2 py-1 mb-1">
                                                    <i class="ki-outline ki-basket fs-8 me-1 text-success"></i> POS
                                                </span>
                                            @elseif($rule->channel === 'retail')
                                                <span class="badge badge-light-primary fw-bold fs-8 px-2 py-1 mb-1">
                                                    <i class="ki-outline ki-shop fs-8 me-1 text-primary"></i> Retail
                                                </span>
                                            @elseif($rule->channel === 'b2b')
                                                <span class="badge badge-light-info fw-bold fs-8 px-2 py-1 mb-1">
                                                    <i class="ki-outline ki-briefcase fs-8 me-1 text-info"></i> B2B
                                                </span>
                                            @else
                                                <span class="badge badge-light-secondary fw-bold fs-8 px-2 py-1 mb-1">
                                                    <i class="ki-outline ki-globe fs-8 me-1 text-gray-600"></i> Semua Channel
                                                </span>
                                            @endif

                                            <div class="text-gray-700 fs-7">
                                                @if($rule->branch)
                                                    <i class="ki-outline ki-geolocation fs-8 text-primary me-1"></i>{{ $rule->branch->code }} — {{ $rule->branch->name }}
                                                @else
                                                    <span class="text-muted"><i class="ki-outline ki-geolocation fs-8 me-1"></i>Semua Cabang</span>
                                                @endif
                                            </div>

                                            @if($rule->customer_category)
                                                <div class="text-muted fs-8 mt-1">
                                                    <i class="ki-outline ki-profile-user fs-8 me-1"></i> Kat: <span class="badge badge-secondary fs-9">{{ $rule->customer_category }}</span>
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Margin Minimum --}}
                                        <td>
                                            @if($rule->margin_method === 'nominal')
                                                <div class="d-flex align-items-center">
                                                    <span class="badge badge-light-success fs-7 fw-bold px-2 py-1">
                                                        +{{ App\Support\CurrencyFormatter::rupiah($rule->minimum_margin_amount) }}
                                                    </span>
                                                </div>
                                                <div class="text-muted fs-8 mt-1">Nominal tetap dari HPP</div>
                                            @else
                                                <div class="d-flex align-items-center">
                                                    <span class="badge badge-light-success fs-7 fw-bold px-2 py-1">
                                                        +{{ $rule->minimum_margin_percent }}%
                                                    </span>
                                                </div>
                                                <div class="text-muted fs-8 mt-1">Persentase dari HPP</div>
                                            @endif
                                        </td>

                                        {{-- Toleransi & Diskon --}}
                                        <td>
                                            <div class="fs-7">
                                                <span class="text-muted">Toleransi Atas:</span>
                                                <span class="fw-bold text-gray-800">+{{ $rule->overpricing_tolerance_percent }}%</span>
                                            </div>
                                            <div class="fs-7 mt-1">
                                                <span class="text-muted">Diskon Maks:</span>
                                                <span class="fw-bold text-danger">{{ $rule->max_discount_percent }}%</span>
                                            </div>
                                        </td>

                                        {{-- Masa Berlaku --}}
                                        <td>
                                            @if(! $rule->starts_at && ! $rule->ends_at)
                                                <span class="badge badge-light-dark fs-8 fw-semibold px-2 py-1">
                                                    <i class="ki-outline ki-infinity fs-8 me-1 text-gray-600"></i> Permanen
                                                </span>
                                            @else
                                                <div class="fs-7 fw-semibold text-gray-800">
                                                    {{ $rule->starts_at ? $rule->starts_at->format('d/m/Y') : 'Sekarang' }}
                                                </div>
                                                <div class="text-muted fs-8">
                                                    s.d. {{ $rule->ends_at ? $rule->ends_at->format('d/m/Y') : 'Tanpa Batas' }}
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Status --}}
                                        <td class="text-center">
                                            <x-metronic.status-badge :status="$rule->is_active ? 'active' : 'inactive'" :label="$rule->is_active ? 'Aktif' : 'Nonaktif'" />
                                        </td>

                                        {{-- Aksi --}}
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-1 align-items-center justify-content-end">
                                                @can('update', $rule)
                                                    {{-- Quick Toggle Status --}}
                                                    <button type="button"
                                                        class="btn btn-icon btn-sm {{ $rule->is_active ? 'btn-light-warning' : 'btn-light-success' }} btn-toggle-rule"
                                                        data-id="{{ $rule->id }}"
                                                        data-name="{{ $rule->name }}"
                                                        data-active="{{ $rule->is_active ? '1' : '0' }}"
                                                        title="{{ $rule->is_active ? 'Nonaktifkan Aturan' : 'Aktifkan Aturan' }}">
                                                        <i class="ki-outline ki-{{ $rule->is_active ? 'cross-circle' : 'check-circle' }} fs-5"></i>
                                                    </button>

                                                    {{-- Edit Rule --}}
                                                    <button type="button"
                                                        class="btn btn-icon btn-sm btn-light-primary btn-edit-rule"
                                                        data-rule="{{ json_encode($rulePayload) }}"
                                                        title="Ubah Aturan">
                                                        <i class="ki-outline ki-pencil fs-5"></i>
                                                    </button>
                                                @endcan

                                                @can('delete', $rule)
                                                    {{-- Delete Rule --}}
                                                    <button type="button"
                                                        class="btn btn-icon btn-sm btn-light-danger btn-delete-rule"
                                                        data-id="{{ $rule->id }}"
                                                        data-name="{{ $rule->name }}"
                                                        title="Hapus Aturan">
                                                        <i class="ki-outline ki-trash fs-5"></i>
                                                    </button>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8">
                                            <x-metronic.empty-state
                                                title="Belum ada aturan harga"
                                                description="Sistem akan memakai fallback bawaan margin 20% sampai aturan harga baru dibuat." />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination --}}
                    <div class="mt-4">
                        {{ $rules->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Tambah / Ubah Aturan Harga --}}
    <div class="modal fade" id="modal_price_rule" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form method="POST" action="{{ route('pricing.rules.store') }}" id="form_price_rule">
                    @csrf
                    <input type="hidden" name="id" id="rule_id" value="{{ old('id') }}">

                    <div class="modal-header pb-0 border-0 justify-content-between">
                        <div>
                            <h2 class="fw-bold modal-title" id="modal_price_rule_title">Tambah Aturan Harga Baru</h2>
                            <div class="text-muted fs-7 mt-1">Kebijakan batas margin minimum, batas atas toleransi, dan diskon per channel.</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                            <i class="ki-outline ki-cross fs-1"></i>
                        </button>
                    </div>

                    <div class="modal-body py-6 px-lg-10">
                        {{-- Bagian 1: Ruang Lingkup & Identitas --}}
                        <div class="card card-bordered p-5 mb-5 bg-light-subtle">
                            <h5 class="fw-bold text-gray-800 mb-4">
                                <i class="ki-outline ki-abstract-26 text-primary me-2 fs-5"></i> Ruang Lingkup (Scope) Aturan
                            </h5>
                            <div class="row g-4">
                                <div class="col-md-7">
                                    <label class="form-label required fw-semibold fs-7">Nama Aturan</label>
                                    <input type="text" name="name" id="rule_name" value="{{ old('name') }}" class="form-control form-control-solid" placeholder="Contoh: POS Toko Reguler, Margin B2B Utama" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label required fw-semibold fs-7">Channel Penjualan</label>
                                    <select name="channel" id="rule_channel" class="form-select form-select-solid" data-control="select2" data-hide-search="true" required>
                                        @foreach($channels as $value => $label)
                                            <option value="{{ $value }}" @selected(old('channel', 'all') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold fs-7">Berlaku di Cabang</label>
                                    <select name="branch_id" id="rule_branch_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Semua Cabang (Global)" data-allow-clear="true">
                                        <option value="">Semua Cabang (Global)</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->code }} — {{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text fs-8">Kosongkan jika aturan berlaku untuk semua cabang.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold fs-7">Kategori Pelanggan</label>
                                    <input type="text" name="customer_category" id="rule_customer_category" value="{{ old('customer_category') }}" class="form-control form-control-solid" placeholder="Contoh: Grosir, VIP (Opsional)">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold fs-7">Prioritas</label>
                                    <input type="number" name="priority" id="rule_priority" value="{{ old('priority', 100) }}" min="1" max="9999" class="form-control form-control-solid" title="Angka lebih kecil dievaluasi lebih dulu">
                                </div>
                            </div>
                        </div>

                        {{-- Bagian 2: Perhitungan Margin Minimum --}}
                        <div class="card card-bordered p-5 mb-5 bg-light-subtle">
                            <h5 class="fw-bold text-gray-800 mb-4">
                                <i class="ki-outline ki-chart-simple text-success me-2 fs-5"></i> Kebijakan Margin Minimum (Fallback HPP)
                            </h5>
                            <div class="row g-4 align-items-end">
                                <div class="col-md-6">
                                    <label class="form-label required fw-semibold fs-7">Metode Perhitungan Margin</label>
                                    <div class="d-flex gap-4 mt-2">
                                        <div class="form-check form-check-custom form-check-solid">
                                            <input class="form-check-input" type="radio" name="margin_method" id="method_percent" value="percent" @checked(old('margin_method', 'percent') === 'percent')>
                                            <label class="form-check-label fw-semibold fs-7" for="method_percent">
                                                Persentase (%)
                                            </label>
                                        </div>
                                        <div class="form-check form-check-custom form-check-solid">
                                            <input class="form-check-input" type="radio" name="margin_method" id="method_nominal" value="nominal" @checked(old('margin_method') === 'nominal')>
                                            <label class="form-check-label fw-semibold fs-7" for="method_nominal">
                                                Nominal Tetap (Rp)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    {{-- Group Margin Percent --}}
                                    <div id="group_margin_percent" class="{{ old('margin_method', 'percent') === 'nominal' ? 'd-none' : '' }}">
                                        <label class="form-label required fw-semibold fs-7">Margin Minimum Persentase</label>
                                        <div class="input-group input-group-solid">
                                            <input type="number" step="0.01" min="0" max="1000" name="minimum_margin_percent" id="rule_margin_percent" value="{{ old('minimum_margin_percent', '20.00') }}" class="form-control">
                                            <span class="input-group-text fw-bold">%</span>
                                        </div>
                                        <div class="form-text fs-8">Ditambahkan ke HPP produk untuk harga batas bawah.</div>
                                    </div>

                                    {{-- Group Margin Nominal --}}
                                    <div id="group_margin_nominal" class="{{ old('margin_method', 'percent') !== 'nominal' ? 'd-none' : '' }}">
                                        <label class="form-label required fw-semibold fs-7">Margin Minimum Nominal Tetap</label>
                                        <div class="input-group input-group-solid">
                                            <span class="input-group-text fw-bold">Rp</span>
                                            <input type="number" step="1" min="0" name="minimum_margin_amount" id="rule_margin_amount" value="{{ old('minimum_margin_amount', '0') }}" class="form-control">
                                        </div>
                                        <div class="form-text fs-8">Nominal rupiah tetap di atas HPP produk.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Bagian 3: Batasan Toleransi & Diskon --}}
                        <div class="card card-bordered p-5 mb-5 bg-light-subtle">
                            <h5 class="fw-bold text-gray-800 mb-4">
                                <i class="ki-outline ki-shield-cross text-warning me-2 fs-5"></i> Batasan Toleransi & Diskon Penjualan
                            </h5>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold fs-7">Toleransi Overpricing Maksimum</label>
                                    <div class="input-group input-group-solid">
                                        <input type="number" step="0.01" min="0" max="1000" name="overpricing_tolerance_percent" id="rule_overpricing_tolerance_percent" value="{{ old('overpricing_tolerance_percent', '50.00') }}" class="form-control">
                                        <span class="input-group-text fw-bold">%</span>
                                    </div>
                                    <div class="form-text fs-8">Batas wajar harga jual maksimal di atas harga dasar.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold fs-7">Diskon Maksimum Kasir/Penjualan</label>
                                    <div class="input-group input-group-solid">
                                        <input type="number" step="0.01" min="0" max="100" name="max_discount_percent" id="rule_max_discount_percent" value="{{ old('max_discount_percent', '20.00') }}" class="form-control">
                                        <span class="input-group-text fw-bold text-danger">%</span>
                                    </div>
                                    <div class="form-text fs-8">Batas maksimal potongan diskon yang dapat diberikan.</div>
                                </div>
                            </div>
                        </div>

                        {{-- Bagian 4: Masa Berlaku & Status --}}
                        <div class="card card-bordered p-5 bg-light-subtle">
                            <h5 class="fw-bold text-gray-800 mb-4">
                                <i class="ki-outline ki-calendar text-info me-2 fs-5"></i> Periode Masa Berlaku & Status
                            </h5>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold fs-7">Tanggal Mulai Berlaku</label>
                                    <input type="date" name="starts_at" id="rule_starts_at" value="{{ old('starts_at') }}" class="form-control form-control-solid">
                                    <div class="form-text fs-8">Kosongkan jika langsung aktif saat ini.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold fs-7">Tanggal Selesai Berlaku</label>
                                    <input type="date" name="ends_at" id="rule_ends_at" value="{{ old('ends_at') }}" class="form-control form-control-solid">
                                    <div class="form-text fs-8">Kosongkan untuk masa berlaku tanpa batas (Permanen).</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold fs-7">Catatan Internal</label>
                                    <textarea name="notes" id="rule_notes" rows="2" class="form-control form-control-solid" placeholder="Contoh: Kebijakan margin POS cabang baru...">{{ old('notes') }}</textarea>
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch form-check-custom form-check-solid">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="rule_is_active" value="1" @checked(old('is_active', true))>
                                        <label class="form-check-label fw-semibold fs-7" for="rule_is_active">
                                            Aktifkan Aturan Harga Ini
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="btn_submit_price_rule">
                            <span class="indicator-label">
                                <i class="ki-outline ki-check fs-5 me-1"></i> Simpan Aturan
                            </span>
                            <span class="indicator-progress">
                                Menyimpan... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Hidden form for Toggle status --}}
    <form id="form_toggle_rule" method="POST" class="d-none">
        @csrf
    </form>

    {{-- Hidden form for Delete rule --}}
    <form id="form_delete_rule" method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('modal_price_rule');
    const modalTitle = document.getElementById('modal_price_rule_title');
    const ruleForm = document.getElementById('form_price_rule');
    const methodPercent = document.getElementById('method_percent');
    const methodNominal = document.getElementById('method_nominal');
    const groupPercent = document.getElementById('group_margin_percent');
    const groupNominal = document.getElementById('group_margin_nominal');
    const inputMarginPercent = document.getElementById('rule_margin_percent');
    const inputMarginAmount = document.getElementById('rule_margin_amount');

    // Switch margin method UI
    const syncMarginMethod = () => {
        const isPercent = methodPercent.checked;
        groupPercent.classList.toggle('d-none', !isPercent);
        groupNominal.classList.toggle('d-none', isPercent);
        inputMarginPercent.required = isPercent;
        inputMarginAmount.required = !isPercent;
    };

    methodPercent?.addEventListener('change', syncMarginMethod);
    methodNominal?.addEventListener('change', syncMarginMethod);

    // Reset Form for New Rule
    document.getElementById('btn-create-rule')?.addEventListener('click', () => {
        modalTitle.textContent = 'Tambah Aturan Harga Baru';
        ruleForm.reset();
        document.getElementById('rule_id').value = '';
        document.getElementById('rule_priority').value = '100';
        document.getElementById('rule_margin_percent').value = '20.00';
        document.getElementById('rule_margin_amount').value = '0';
        document.getElementById('rule_overpricing_tolerance_percent').value = '50.00';
        document.getElementById('rule_max_discount_percent').value = '20.00';
        document.getElementById('rule_is_active').checked = true;
        methodPercent.checked = true;

        if (window.jQuery) {
            window.jQuery('#rule_channel').val('all').trigger('change');
            window.jQuery('#rule_branch_id').val('').trigger('change');
        }
        syncMarginMethod();
    });

    // Populate Modal for Edit Rule
    document.querySelectorAll('.btn-edit-rule').forEach(button => {
        button.addEventListener('click', () => {
            const raw = button.getAttribute('data-rule');
            if (!raw) return;
            const data = JSON.parse(raw);

            modalTitle.textContent = 'Ubah Aturan Harga';
            document.getElementById('rule_id').value = data.id || '';
            document.getElementById('rule_name').value = data.name || '';
            document.getElementById('rule_customer_category').value = data.customer_category || '';
            document.getElementById('rule_priority').value = data.priority ?? 100;
            document.getElementById('rule_overpricing_tolerance_percent').value = data.overpricing_tolerance_percent ?? '50.00';
            document.getElementById('rule_max_discount_percent').value = data.max_discount_percent ?? '20.00';
            document.getElementById('rule_starts_at').value = data.starts_at || '';
            document.getElementById('rule_ends_at').value = data.ends_at || '';
            document.getElementById('rule_notes').value = data.notes || '';
            document.getElementById('rule_is_active').checked = !!data.is_active;

            if (data.margin_method === 'nominal') {
                methodNominal.checked = true;
                document.getElementById('rule_margin_amount').value = data.minimum_margin_amount || '0';
                document.getElementById('rule_margin_percent').value = '0.00';
            } else {
                methodPercent.checked = true;
                document.getElementById('rule_margin_percent').value = data.minimum_margin_percent || '20.00';
                document.getElementById('rule_margin_amount').value = '0';
            }

            if (window.jQuery) {
                window.jQuery('#rule_channel').val(data.channel || 'all').trigger('change');
                window.jQuery('#rule_branch_id').val(data.branch_id || '').trigger('change');
            }

            syncMarginMethod();

            if (window.bootstrap?.Modal) {
                const bsModal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
                bsModal.show();
            }
        });
    });

    // Submit handler with progress indicator
    ruleForm?.addEventListener('submit', () => {
        syncMarginMethod();
        const submitBtn = document.getElementById('btn_submit_price_rule');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.setAttribute('data-kt-indicator', 'on');
        }
    });

    // Quick Toggle Status handler
    document.querySelectorAll('.btn-toggle-rule').forEach(button => {
        button.addEventListener('click', () => {
            const ruleId = button.dataset.id;
            const ruleName = button.dataset.name;
            const isActive = button.dataset.active === '1';
            const actionText = isActive ? 'nonaktifkan' : 'aktifkan';

            const runToggle = () => {
                const toggleForm = document.getElementById('form_toggle_rule');
                toggleForm.action = `/pricing/rules/${ruleId}/toggle`;
                toggleForm.submit();
            };

            if (window.Swal) {
                window.Swal.fire({
                    title: `Yakin ingin ${actionText} aturan ini?`,
                    text: `Aturan '${ruleName}' akan ${actionText}.`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: `Ya, ${actionText}!`,
                    cancelButtonText: 'Batal',
                    customClass: {
                        confirmButton: isActive ? 'btn btn-warning' : 'btn btn-success',
                        cancelButton: 'btn btn-light'
                    }
                }).then(result => {
                    if (result.isConfirmed) {
                        runToggle();
                    }
                });
            } else {
                if (confirm(`Yakin ingin ${actionText} aturan '${ruleName}'?`)) {
                    runToggle();
                }
            }
        });
    });

    // Delete Rule with SweetAlert2 confirmation
    document.querySelectorAll('.btn-delete-rule').forEach(button => {
        button.addEventListener('click', () => {
            const ruleId = button.dataset.id;
            const ruleName = button.dataset.name;

            const runDelete = () => {
                const deleteForm = document.getElementById('form_delete_rule');
                deleteForm.action = `/pricing/rules/${ruleId}`;
                deleteForm.submit();
            };

            if (window.Swal) {
                window.Swal.fire({
                    title: 'Hapus Aturan Harga?',
                    text: `Aturan '${ruleName}' akan dihapus permanen. Produk yang mengandalkan aturan ini akan kembali ke fallback sistem.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Hapus!',
                    cancelButtonText: 'Batal',
                    customClass: {
                        confirmButton: 'btn btn-danger',
                        cancelButton: 'btn btn-light'
                    }
                }).then(result => {
                    if (result.isConfirmed) {
                        runDelete();
                    }
                });
            } else {
                if (confirm(`Hapus aturan '${ruleName}'?`)) {
                    runDelete();
                }
            }
        });
    });
});
</script>
@endpush
