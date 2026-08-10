@extends('layouts.metronic.app')

@php
    use App\Support\CurrencyFormatter;
    $filters = $report['filters'];
    $summary = $report['summary'];
    $charts = $report['charts'];
    $sections = $report['sections'];
    $scope = $filters['report_scope'] ?? 'all';

    $scopeMeta = [
        'all' => [
            'label' => 'Laporan Utama',
            'description' => 'Ikhtisar gabungan performa toko, gudang, B2B, persediaan, dan risiko bisnis.',
            'icon' => 'ki-chart-pie-4',
            'color' => 'primary',
            'gradient' => 'linear-gradient(135deg,#1a3a8f 0%,#3b6edc 50%,#5b8def 100%)',
        ],
        'retail' => [
            'label' => 'Laporan Toko/Cabang',
            'description' => 'Analisis penjualan POS, margin, kasir, metode pembayaran, dan kesiapan stok toko.',
            'icon' => 'ki-shop',
            'color' => 'success',
            'gradient' => 'linear-gradient(135deg,#0f6f3a 0%,#1ca55a 50%,#4ade80 100%)',
        ],
        'warehouse' => [
            'label' => 'Laporan Gudang',
            'description' => 'Analisis posisi persediaan, stok kritis, dan pergerakan barang gudang.',
            'icon' => 'ki-delivery-3',
            'color' => 'warning',
            'gradient' => 'linear-gradient(135deg,#92531b 0%,#d68a1f 50%,#fbc16d 100%)',
        ],
    ][$scope];

    $kpiItems = match ($scope) {
        'retail' => [
            ['Omzet POS', CurrencyFormatter::rupiah($summary['revenue']), 'ki-chart-line-up', 'success', 'Transaksi selesai periode aktif'],
            ['Jumlah Transaksi', number_format($summary['transaction_count'], 0, ',', '.'), 'ki-receipt-square', 'primary', 'Rata-rata '.CurrencyFormatter::rupiah($summary['average_ticket'])],
            ['Margin Kotor', CurrencyFormatter::rupiah($summary['margin']), 'ki-arrow-up', 'info', $summary['margin_percent'].'% dari omzet'],
            ['Nilai Stok Toko', CurrencyFormatter::rupiah($summary['stock_value']), 'ki-parcel', 'warning', $summary['critical_stock_count'].' kritis · '.$summary['empty_stock_count'].' kosong'],
        ],
        'warehouse' => [
            ['Nilai Persediaan', CurrencyFormatter::rupiah($summary['stock_value']), 'ki-parcel', 'warning', $summary['total_products'].' produk tersimpan'],
            ['Stok Tersedia', qty($summary['available_quantity']), 'ki-check-circle', 'success', qty($summary['on_hand_quantity']).' unit on hand'],
            ['Reserved / Rusak', qty($summary['reserved_quantity']).' / '.qty($summary['damaged_quantity']), 'ki-lock-2', 'info', 'Unit belum dapat dijual'],
            ['Perlu Perhatian', $summary['critical_count'].' / '.$summary['empty_count'], 'ki-warning', 'danger', 'Kritis / kosong'],
        ],
        default => [
            ['Omzet Perusahaan', CurrencyFormatter::rupiah($summary['revenue']), 'ki-chart-line-up', 'primary', 'Retail POS + B2B'],
            ['Laba Kotor', CurrencyFormatter::rupiah($summary['gross_margin']), 'ki-arrow-up', 'success', $summary['margin_percent'].'% margin'],
            ['Nilai Persediaan', CurrencyFormatter::rupiah($summary['stock_value']), 'ki-parcel', 'warning', $summary['critical_stock_count'].' produk kritis'],
            ['Piutang Berjalan', CurrencyFormatter::rupiah($summary['receivable_outstanding']), 'ki-wallet', 'info', CurrencyFormatter::rupiah($summary['overdue_receivable']).' overdue'],
        ],
    };

    $secondaryKpis = match ($scope) {
        'retail' => [
            ['Shift Aktif', $summary['active_shift_count'], 'ki-clock', 'success'],
            ['Closing Pending', $summary['closing_pending_count'], 'ki-time', 'warning'],
            ['Void', $summary['void_count'], 'ki-cross-circle', 'danger'],
            ['Nilai Retur', CurrencyFormatter::rupiah($summary['return_amount']), 'ki-arrow-left', 'danger'],
            ['Selisih Kas', CurrencyFormatter::rupiah($summary['cash_difference']), 'ki-wallet', 'warning'],
            ['Stok Kosong', $summary['empty_stock_count'], 'ki-parcel', 'danger'],
        ],
        'warehouse' => [
            ['Total Produk', $summary['total_products'], 'ki-parcel', 'primary'],
            ['Mutasi Masuk', $summary['incoming_count'], 'ki-down-square', 'success'],
            ['Mutasi Keluar', $summary['outgoing_count'], 'ki-up-square', 'danger'],
            ['PO Berjalan', $summary['pending_po'], 'ki-purchase', 'warning'],
            ['Transfer Berjalan', $summary['pending_transfer'], 'ki-arrow-left-right', 'info'],
            ['Order Gudang', $summary['pending_order'], 'ki-basket', 'primary'],
            ['Opname Terbuka', $summary['open_opname'], 'ki-clipboard', 'warning'],
            ['GR Posted', $summary['posted_receipts'], 'ki-check-circle', 'success'],
        ],
        default => [
            ['Transaksi Hari Ini', $summary['transactions_today'], 'ki-receipt-square', 'primary'],
            ['Pending Approval', $summary['pending_approval'], 'ki-shield-tick', 'warning'],
            ['Anomali Terbuka', $summary['anomaly_open'], 'ki-warning', 'danger'],
            ['Nilai Retur/Loss', CurrencyFormatter::rupiah($summary['returns_value']), 'ki-arrow-left', 'danger'],
            ['Selisih Kas', CurrencyFormatter::rupiah($summary['cash_difference']), 'ki-wallet', 'warning'],
            ['Kehadiran Telat', $summary['attendance_late'], 'ki-calendar-tick', 'info'],
        ],
    };
@endphp

@section('title', 'Laporan Harian Owner - '.config('app.name'))
@section('page_title', 'Laporan Harian Owner')

@push('styles')
<style>
    .daily-report-hero {
        background: {{ $scopeMeta['gradient'] }};
        border-radius: 1.25rem;
        overflow: hidden;
        position: relative;
        box-shadow: 0 12px 32px rgba(20,40,90,0.18);
    }
    .daily-report-hero::after {
        content: "";
        position: absolute;
        width: 320px;
        height: 320px;
        border: 56px solid rgba(255,255,255,0.06);
        border-radius: 50%;
        right: -90px;
        top: -150px;
        pointer-events: none;
    }
    .daily-report-hero > * { position: relative; z-index: 1; }
    .daily-report-hero .hero-icon {
        width: 56px; height: 56px;
        border-radius: 14px;
        display: grid; place-items: center;
        background: rgba(255,255,255,0.14);
        backdrop-filter: blur(6px);
    }
    .daily-report-hero .hero-period {
        background: rgba(255,255,255,0.12);
        backdrop-filter: blur(6px);
    }
    .scope-segment {
        display: inline-flex;
        gap: 4px;
        padding: 5px;
        background: var(--bs-gray-100);
        border-radius: 14px;
        max-width: 100%;
        overflow: auto;
    }
    .scope-segment .scope-btn {
        border-radius: 10px;
        padding: 0.55rem 1rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--bs-gray-700);
        transition: all .2s ease;
        border: 0;
        background: transparent;
        text-decoration: none;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    .scope-segment .scope-btn:hover { background: var(--bs-gray-200); }
    .scope-segment .scope-btn.active {
        background: var(--bs-{{ $scopeMeta['color'] }}, #1ca55a);
        color: white;
        box-shadow: 0 6px 18px rgba(0,0,0,0.12);
    }
    .report-card-hover {
        transition: transform .25s ease, box-shadow .25s ease;
    }
    .report-card-hover:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 32px rgba(29,45,80,0.10);
    }
    .kpi-strip {
        display: flex;
        flex-wrap: wrap;
        gap: 0;
    }
    .kpi-strip > .kpi-cell {
        flex: 1 1 0;
        min-width: 160px;
        padding: 1rem 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        border-right: 1px solid var(--bs-gray-200);
    }
    .kpi-strip > .kpi-cell:last-child { border-right: 0; }
    @media (max-width: 768px) {
        .kpi-strip > .kpi-cell { border-right: 0; border-bottom: 1px solid var(--bs-gray-200); }
    }
    .kpi-cell .kpi-cell-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--bs-gray-600);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .kpi-cell .kpi-cell-value {
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--bs-gray-900);
        line-height: 1.2;
    }
    .list-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.85rem 1.25rem;
        border-bottom: 1px solid var(--bs-gray-200);
        gap: 1rem;
    }
    .list-row:last-child { border-bottom: 0; }
    .list-row .list-title { font-weight: 600; color: var(--bs-gray-900); }
    .list-row .list-meta { font-size: 0.78rem; color: var(--bs-gray-600); margin-top: 0.15rem; }
    .mini-card {
        background: var(--bs-gray-100);
        border-radius: 0.85rem;
        padding: 1rem 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        border: 1px solid var(--bs-gray-200);
    }
    .mini-card:hover {
        background: white;
        border-color: var(--bs-{{ $scopeMeta['color'] }}, #1ca55a);
    }
    .chart-container {
        position: relative;
        min-height: 320px;
    }
    .report-table thead th {
        color: var(--bs-gray-600);
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
        padding-top: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid var(--bs-gray-200);
        white-space: nowrap;
    }
    .report-table tbody td {
        padding-top: 0.85rem;
        padding-bottom: 0.85rem;
        vertical-align: middle;
    }
    .filter-bar {
        background: var(--bs-gray-100);
        border-radius: 1rem;
        padding: 1.25rem 1.5rem;
    }
    .filter-bar label {
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--bs-gray-700);
        margin-bottom: 0.35rem;
    }
    .pill-soft {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.3rem 0.65rem;
        font-size: 0.7rem;
        font-weight: 600;
        border-radius: 999px;
    }
</style>
@endpush

@section('page_guide')
<x-metronic.page-guide id="reports-daily" title="Panduan Laporan Harian Owner">
    <x-slot:function>Melihat laporan harian dalam mode gabungan, toko/cabang, atau gudang dengan data yang relevan untuk tiap unit.</x-slot:function>
    <x-slot:workflow>Pilih jenis laporan, pilih lokasi spesifik bila diperlukan, tentukan periode, lalu terapkan filter.</x-slot:workflow>
    <x-slot:parts>Mode Utama menampilkan bisnis keseluruhan; mode Toko fokus penjualan POS; mode Gudang fokus persediaan dan mutasi.</x-slot:parts>
    <x-slot:impacts>Pemilihan jenis laporan membatasi daftar lokasi dan seluruh query laporan pada tipe lokasi tersebut.</x-slot:impacts>
    <x-slot:operation>Gunakan grafik untuk melihat tren, lalu tabel detail untuk menelusuri sumber angkanya.</x-slot:operation>
    <x-slot:warnings>Lokasi toko tidak dapat digunakan pada mode Gudang dan lokasi gudang tidak dapat digunakan pada mode Toko.</x-slot:warnings>
    <x-slot:example>Pilih Toko/Cabang lalu Toko Kedaung untuk melihat omzet, transaksi, margin, dan produk pada toko tersebut saja.</x-slot:example>
</x-metronic.page-guide>
@endsection

@section('content')
<div class="daily-report">
    {{-- HERO --}}
    <div class="daily-report-hero p-7 p-lg-9 mb-6 text-white">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-6">
            <div>
                <div class="d-flex align-items-center gap-3 mb-4">
                    <span class="hero-icon">
                        <i class="ki-outline {{ $scopeMeta['icon'] }} fs-2 text-white"></i>
                    </span>
                    <span class="pill-soft bg-white bg-opacity-15 text-white">
                        <i class="ki-outline ki-chart-line fs-7"></i>
                        {{ $scopeMeta['label'] }}
                    </span>
                </div>
                <h1 class="text-white fw-bold fs-2x mb-3">Laporan Harian Owner</h1>
                <div class="text-white opacity-80 fs-6" style="max-width: 620px;">{{ $scopeMeta['description'] }}</div>
            </div>
            <div class="d-flex gap-3 flex-wrap align-items-center">
                <div class="hero-period rounded-3 px-5 py-3">
                    <div class="opacity-75 fs-8 fw-semibold text-uppercase" style="letter-spacing:.06em;">Periode Aktif</div>
                    <div class="fw-bold fs-5">{{ $filters['start']->format('d M Y') }} – {{ $filters['end']->format('d M Y') }}</div>
                </div>
                @can('reports.export')
                    <a href="{{ route('reports.exports.index', ['report_type'=>'daily','start_date'=>$filters['start_date'],'end_date'=>$filters['end_date']]) }}" class="btn btn-light btn-color-white d-flex align-items-center">
                        <i class="ki-outline ki-file-down me-2 fs-4"></i><span class="fw-semibold">Export</span>
                    </a>
                @endcan
            </div>
        </div>
    </div>

    {{-- SCOPE SEGMENTED --}}
    <div class="mb-6 d-flex justify-content-center justify-content-lg-start">
        <div class="scope-segment" role="navigation" aria-label="Jenis laporan">
            @foreach(['all'=>['Laporan Utama','ki-chart-pie-4','primary'], 'retail'=>['Toko/Cabang','ki-shop','success'], 'warehouse'=>['Gudang','ki-delivery-3','warning']] as $value=>$meta)
                <a href="{{ route('reports.daily.index', array_merge(request()->except(['report_scope','work_location_id']), ['report_scope'=>$value])) }}" class="scope-btn {{ $scope===$value ? 'active' : '' }}">
                    <i class="ki-outline {{ $meta[1] }}"></i>{{ $meta[0] }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- FILTER BAR --}}
    <x-metronic.card class="mb-6" flush>
        <div class="card-body filter-bar">
            <form method="GET" id="daily-report-filter" class="row g-4 align-items-end">
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">Jenis Laporan</label>
                    <select name="report_scope" id="report-scope" class="form-select form-select-solid">
                        <option value="all" @selected($scope==='all')>Utama — Toko + Gudang</option>
                        <option value="retail" @selected($scope==='retail')>Toko/Cabang</option>
                        <option value="warehouse" @selected($scope==='warehouse')>Gudang</option>
                    </select>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label" id="location-label">Lokasi Spesifik</label>
                    <select name="work_location_id" id="work-location" class="form-select form-select-solid">
                        <option value="">Semua lokasi sesuai jenis</option>
                        @foreach($workLocations as $location)
                            <option value="{{ $location->id }}" data-location-type="{{ $location->type }}" @selected((string)$filters['work_location_id']===(string)$location->id)>
                                {{ $location->type==='warehouse' ? 'Gudang' : 'Toko/Cabang' }} · {{ $location->name }} ({{ $location->code }})
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text" id="location-help">Daftar lokasi mengikuti jenis laporan.</div>
                </div>
                <div class="col-sm-6 col-xl-2">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="form-control form-control-solid">
                </div>
                <div class="col-sm-6 col-xl-2">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="form-control form-control-solid">
                </div>
                <div class="col-sm-6 col-xl-1">
                    <label class="form-label">Grafik</label>
                    <select name="range" class="form-select form-select-solid">
                        <option value="daily" @selected($filters['range']==='daily')>Harian</option>
                        <option value="monthly" @selected($filters['range']==='monthly')>Bulanan</option>
                        <option value="yearly" @selected($filters['range']==='yearly')>Tahunan</option>
                    </select>
                </div>
                <div class="col-sm-6 col-xl-1 d-flex gap-2">
                    <button class="btn btn-{{ $scopeMeta['color'] }} flex-grow-1 d-flex align-items-center justify-content-center" title="Terapkan filter">
                        <i class="ki-outline ki-filter fs-4"></i>
                    </button>
                    <a href="{{ route('reports.daily.index') }}" class="btn btn-light d-flex align-items-center justify-content-center" title="Reset filter">
                        <i class="ki-outline ki-arrows-circle fs-4"></i>
                    </a>
                </div>
            </form>
        </div>
    </x-metronic.card>

    {{-- PRIMARY KPI CARDS --}}
    <div class="row g-5 mb-6">
        @foreach($kpiItems as [$label,$value,$icon,$color,$hint])
            <div class="col-sm-6 col-xl-3">
                <x-metronic.kpi-card
                    :title="$label"
                    :value="$value"
                    :icon="'ki-outline '.$icon"
                    :color="$color"
                    :description="$hint" />
            </div>
        @endforeach
    </div>

    {{-- SECONDARY KPI STRIP --}}
    <x-metronic.card class="mb-6 report-card-hover">
        <div class="card-body p-0">
            <div class="kpi-strip">
                @foreach($secondaryKpis as [$label,$value,$icon,$color])
                    <div class="kpi-cell">
                        <div class="kpi-cell-label">
                            <i class="ki-outline {{ $icon }} text-{{ $color }} fs-4"></i>
                            {{ $label }}
                        </div>
                        <div class="kpi-cell-value">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-metronic.card>

    {{-- PRIMARY CHARTS --}}
    <div class="row g-5 mb-6">
        <div class="col-xl-8">
            <x-metronic.card>
                <x-slot:title>{{ $scope==='warehouse' ? 'Arus Stok' : 'Tren Performa' }}</x-slot:title>
                <div class="text-muted fs-7 mb-3">{{ $scope==='warehouse' ? 'Kuantitas barang masuk dibanding keluar' : 'Pergerakan omzet sepanjang periode aktif' }}</div>
                <div id="daily-primary-chart" class="chart-container"></div>
            </x-metronic.card>
        </div>
        <div class="col-xl-4">
            <x-metronic.card>
                <x-slot:title>{{ $scope==='retail' ? 'Metode Pembayaran' : ($scope==='warehouse' ? 'Nilai Stok per Gudang' : 'Komposisi Omzet') }}</x-slot:title>
                <div class="text-muted fs-7 mb-3">Distribusi data periode aktif</div>
                <div id="daily-secondary-chart" style="height:320px;"></div>
            </x-metronic.card>
        </div>
    </div>

    {{-- SCOPED CONTENT --}}
    @if($scope === 'all')
        <div class="row g-5 mb-6">
            <div class="col-xl-7">
                <x-metronic.card title="Performa Toko" subtitle="Kontribusi komersial setiap cabang" class="report-card-hover h-100">
                    <div class="table-responsive">
                        <table class="table report-table align-middle">
                            <thead><tr><th>Toko/Cabang</th><th class="text-end">Omzet</th><th class="text-end">Transaksi</th><th class="text-end">Margin</th><th class="text-end">Stok</th></tr></thead>
                            <tbody>
                                @forelse($sections['stores'] as $store)
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-gray-900">{{ $store['name'] }}</div>
                                            <div class="text-muted fs-8">{{ $store['code'] }}</div>
                                        </td>
                                        <td class="text-end fw-bold text-primary">{{ CurrencyFormatter::rupiah($store['revenue']) }}</td>
                                        <td class="text-end">{{ number_format($store['transactions'],0,',','.') }}</td>
                                        <td class="text-end"><span class="badge badge-light-success fw-bold">{{ $store['margin_percent'] }}%</span></td>
                                        <td class="text-end text-gray-800">{{ CurrencyFormatter::rupiah($store['stock_value']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5"><x-metronic.empty-state title="Belum ada data toko" description="Data toko akan tampil sesuai cakupan akses." /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-metronic.card>
            </div>
            <div class="col-xl-5">
                <x-metronic.card title="Kesehatan Gudang" subtitle="Stok dan aktivitas mutasi" class="report-card-hover h-100">
                    @forelse($sections['warehouses'] as $warehouse)
                        <div class="mini-card mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold text-gray-900">{{ $warehouse['name'] }}</div>
                                    <div class="text-muted fs-8">{{ $warehouse['code'] }}</div>
                                </div>
                                <span class="badge badge-light-{{ $warehouse['empty_count'] ? 'danger' : 'success' }} fw-bold">{{ $warehouse['empty_count'] }} kosong</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <span class="text-muted fs-8">Nilai stok</span>
                                <span class="fw-bold text-warning">{{ CurrencyFormatter::rupiah($warehouse['stock_value']) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted fs-8">Masuk / Keluar</span>
                                <span><b class="text-success">+{{ qty($warehouse['incoming']) }}</b> <span class="text-muted mx-1">/</span> <b class="text-danger">-{{ qty($warehouse['outgoing']) }}</b></span>
                            </div>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Belum ada data gudang" description="Data gudang akan tampil sesuai cakupan akses." />
                    @endforelse
                </x-metronic.card>
            </div>
        </div>
    @elseif($scope === 'retail')
        <div class="row g-5 mb-6">
            <div class="col-xl-7">
                <x-metronic.card title="Produk Terlaris" subtitle="Produk dengan penjualan tertinggi" class="report-card-hover h-100">
                    <div class="table-responsive">
                        <table class="table report-table align-middle">
                            <thead><tr><th>Produk</th><th class="text-end">Terjual</th><th class="text-end">Omzet</th></tr></thead>
                            <tbody>
                                @forelse($sections['top_products'] as $row)
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-gray-900">{{ $row['product'] }}</div>
                                            <div class="text-muted fs-8">{{ $row['sku'] }}</div>
                                        </td>
                                        <td class="text-end">{{ qty($row['quantity']) }}</td>
                                        <td class="text-end fw-bold text-success">{{ CurrencyFormatter::rupiah($row['revenue']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3"><x-metronic.empty-state title="Belum ada penjualan" description="Produk terlaris akan muncul setelah transaksi selesai." /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-metronic.card>
            </div>
            <div class="col-xl-5">
                <x-metronic.card title="Performa per Toko" subtitle="Ringkasan unit dalam cakupan filter" class="report-card-hover h-100">
                    @forelse($sections['stores'] as $store)
                        <div class="mini-card mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold text-gray-900">{{ $store['name'] }}</div>
                                    <div class="text-muted fs-8">{{ $store['transactions'] }} transaksi</div>
                                </div>
                                <span class="badge badge-light-success fw-bold">{{ $store['margin_percent'] }}%</span>
                            </div>
                            <div class="fs-3 fw-bold text-success">{{ CurrencyFormatter::rupiah($store['revenue']) }}</div>
                            <div class="text-muted fs-8">Rata-rata nota {{ CurrencyFormatter::rupiah($store['average_ticket']) }}</div>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Belum ada toko" description="Pilih cakupan toko yang tersedia." />
                    @endforelse
                </x-metronic.card>
            </div>
        </div>
    @else
        <div class="row g-5 mb-6">
            <div class="col-xl-7">
                <x-metronic.card title="Produk dengan Pergerakan Terbesar" subtitle="Prioritas aktivitas operasional gudang" class="report-card-hover h-100">
                    <div class="table-responsive">
                        <table class="table report-table align-middle">
                            <thead><tr><th>Produk</th><th class="text-end">Total Gerak</th><th class="text-end">Frekuensi</th></tr></thead>
                            <tbody>
                                @forelse($sections['top_movers'] as $row)
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-gray-900">{{ $row['product'] }}</div>
                                            <div class="text-muted fs-8">{{ $row['sku'] }}</div>
                                        </td>
                                        <td class="text-end fw-bold text-warning">{{ qty($row['total_movement']) }}</td>
                                        <td class="text-end">{{ $row['frequency'] }} mutasi</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3"><x-metronic.empty-state title="Belum ada mutasi" description="Pergerakan produk akan tampil setelah mutasi diposting." /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-metronic.card>
            </div>
            <div class="col-xl-5">
                <x-metronic.card title="Status per Gudang" subtitle="Kuantitas dan risiko persediaan" class="report-card-hover h-100">
                    @forelse($sections['warehouses'] as $warehouse)
                        <div class="mini-card mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold text-gray-900">{{ $warehouse['name'] }}</div>
                                    <div class="text-muted fs-8">{{ qty($warehouse['available_quantity']) }} unit tersedia</div>
                                </div>
                                <span class="badge badge-light-{{ $warehouse['empty_count'] ? 'danger' : ($warehouse['critical_count'] ? 'warning' : 'success') }} fw-bold">{{ $warehouse['critical_count'] }} kritis</span>
                            </div>
                            <div class="fs-3 fw-bold text-warning">{{ CurrencyFormatter::rupiah($warehouse['stock_value']) }}</div>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Belum ada gudang" description="Pilih cakupan gudang yang tersedia." />
                    @endforelse
                </x-metronic.card>
            </div>
        </div>
    @endif

    {{-- ADDITIONAL DETAIL SECTIONS --}}
    @if($scope === 'all')
        <div class="row g-5 mb-6">
            <div class="col-xl-7">
                <x-metronic.card title="Piutang Jatuh Tempo" subtitle="Tagihan dengan saldo terbesar yang perlu ditindaklanjuti" class="report-card-hover h-100">
                    <x-slot:toolbar><a href="{{ route('reports.receivables.index') }}" class="btn btn-sm btn-light-primary">Lihat Semua</a></x-slot:toolbar>
                    <div class="table-responsive">
                        <table class="table report-table align-middle">
                            <thead><tr><th>Tagihan / Pelanggan</th><th>Jatuh Tempo</th><th>Aging</th><th class="text-end">Outstanding</th></tr></thead>
                            <tbody>
                                @forelse($sections['overdue_receivables'] as $row)
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-gray-900">{{ $row['number'] }}</div>
                                            <div class="text-muted fs-8">{{ $row['customer'] }} · {{ $row['location'] ?: 'Pusat' }}</div>
                                        </td>
                                        <td>{{ \Illuminate\Support\Carbon::parse($row['due_date'])->format('d/m/Y') }}</td>
                                        <td><span class="badge badge-light-danger fw-bold">{{ str_replace('_',' ',$row['aging_bucket']) }}</span></td>
                                        <td class="text-end fw-bold text-danger">{{ CurrencyFormatter::rupiah($row['outstanding']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4"><x-metronic.empty-state title="Tidak ada piutang overdue" description="Seluruh tagihan dalam kondisi terkendali." /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-metronic.card>
            </div>
            <div class="col-xl-5">
                <x-metronic.card title="Aging Piutang" subtitle="Distribusi saldo berdasarkan umur tagihan" class="report-card-hover h-100">
                    @forelse($sections['aging'] as $bucket=>$amount)
                        <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <span class="bullet bullet-dot bg-{{ $bucket==='not_due' ? 'success' : 'danger' }}"></span>
                                <span class="fw-semibold text-capitalize text-gray-800">{{ str_replace('_',' ',$bucket) }}</span>
                            </div>
                            <span class="fw-bold text-gray-900">{{ CurrencyFormatter::rupiah($amount) }}</span>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Belum ada piutang" description="Distribusi aging akan tampil setelah invoice diterbitkan." />
                    @endforelse
                </x-metronic.card>
            </div>
        </div>

        <div class="row g-5 mb-6">
            <div class="col-xl-6">
                <x-metronic.card title="Approval Prioritas" subtitle="Permintaan dengan nilai risiko terbesar" class="report-card-hover h-100">
                    <x-slot:toolbar><a href="{{ route('approvals.index') }}" class="btn btn-sm btn-light-warning">Kotak Approval</a></x-slot:toolbar>
                    @forelse($sections['pending_approvals'] as $row)
                        <div class="mini-card mb-3">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="fw-bold text-gray-900 text-capitalize">{{ str_replace('_',' ',$row['approval_type']) }}</div>
                                    <div class="text-muted fs-8">{{ $row['module'] }} · {{ $row['requester'] ?: 'Sistem' }} · {{ $row['location'] ?: 'Semua lokasi' }}</div>
                                </div>
                                <span class="badge badge-light-{{ in_array($row['risk_level'],['high','critical']) ? 'danger' : 'warning' }} fw-bold">{{ $row['risk_level'] }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <span class="text-muted fs-8 text-truncate me-3">{{ $row['reason'] }}</span>
                                <span class="fw-bold text-gray-900">{{ CurrencyFormatter::rupiah($row['risk_value']) }}</span>
                            </div>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Tidak ada approval pending" description="Tidak ada keputusan yang menunggu owner." />
                    @endforelse
                </x-metronic.card>
            </div>
            <div class="col-xl-6">
                <x-metronic.card title="Order B2B Terbaru" subtitle="Aktivitas pelanggan langganan pada periode aktif" class="report-card-hover h-100">
                    <x-slot:toolbar><a href="{{ route('reports.b2b.index') }}" class="btn btn-sm btn-light-primary">Laporan B2B</a></x-slot:toolbar>
                    <div class="table-responsive">
                        <table class="table report-table align-middle">
                            <thead><tr><th>Order</th><th>Status</th><th class="text-end">Nilai</th></tr></thead>
                            <tbody>
                                @forelse($sections['recent_b2b_orders'] as $row)
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-gray-900">{{ $row['number'] }}</div>
                                            <div class="text-muted fs-8">{{ $row['customer'] }} · {{ $row['submitted_at'] ? \Illuminate\Support\Carbon::parse($row['submitted_at'])->format('d/m H:i') : '-' }}</div>
                                        </td>
                                        <td><span class="badge badge-light-primary fw-bold">{{ str_replace('_',' ',$row['status']) }}</span></td>
                                        <td class="text-end fw-bold">{{ CurrencyFormatter::rupiah($row['amount']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3"><x-metronic.empty-state title="Belum ada order B2B" description="Order terbaru akan tampil di sini." /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-metronic.card>
            </div>
        </div>

        <div class="row g-5 mb-6">
            <div class="col-xl-4">
                <x-metronic.card title="Transaksi POS Terbaru" class="report-card-hover h-100">
                    @forelse($sections['recent_sales'] as $row)
                        <div class="list-row">
                            <div>
                                <div class="list-title">{{ $row['number'] }}</div>
                                <div class="list-meta">{{ $row['cashier'] ?: 'Tanpa kasir' }}</div>
                            </div>
                            <span class="fw-bold text-success">{{ CurrencyFormatter::rupiah($row['amount']) }}</span>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Belum ada transaksi" description="Transaksi POS terbaru akan tampil di sini." />
                    @endforelse
                </x-metronic.card>
            </div>
            <div class="col-xl-4">
                <x-metronic.card title="Stok Perlu Tindakan" class="report-card-hover h-100">
                    @forelse($sections['stock_alerts'] as $row)
                        <div class="list-row">
                            <div>
                                <div class="list-title">{{ $row['product'] }}</div>
                                <div class="list-meta">{{ $row['sku'] }} · minimum {{ qty($row['minimum_stock']) }}</div>
                            </div>
                            <span class="badge badge-light-{{ $row['status']==='empty' ? 'danger' : 'warning' }} fw-bold">{{ qty($row['available']) }}</span>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Stok aman" description="Tidak ada produk di bawah stok minimum." />
                    @endforelse
                </x-metronic.card>
            </div>
            <div class="col-xl-4">
                <x-metronic.card title="Anomali Terbaru" class="report-card-hover h-100">
                    @forelse($sections['alerts'] as $row)
                        <div class="mini-card mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="fw-bold text-gray-900">{{ $row['title'] }}</span>
                                <span class="badge badge-light-{{ in_array($row['severity'],['high','critical']) ? 'danger' : 'warning' }} fw-bold">{{ $row['severity'] }}</span>
                            </div>
                            <div class="text-muted fs-8">{{ $row['description'] ?: str_replace('_',' ',$row['rule_key']) }}</div>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Tidak ada anomali" description="Tidak ada alert terbuka saat ini." />
                    @endforelse
                </x-metronic.card>
            </div>
        </div>

        <div class="row g-5 mb-6">
            <div class="col-xl-4">
                <x-metronic.card title="Produk Terlaris" subtitle="Kontributor omzet Retail terbesar" class="report-card-hover h-100">
                    @forelse(array_slice($sections['top_products'],0,6) as $row)
                        <div class="list-row">
                            <div>
                                <div class="list-title">{{ $row['product'] }}</div>
                                <div class="list-meta">{{ $row['sku'] }} · {{ qty($row['quantity']) }} unit</div>
                            </div>
                            <span class="fw-bold text-primary">{{ CurrencyFormatter::rupiah($row['revenue']) }}</span>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Belum ada penjualan" description="Produk terlaris akan tampil setelah transaksi selesai." />
                    @endforelse
                </x-metronic.card>
            </div>
            <div class="col-xl-4">
                <x-metronic.card title="Slow / Dead Stock" subtitle="Modal tertahan pada produk minim penjualan" class="report-card-hover h-100">
                    @forelse(array_slice($sections['slow_products'],0,6) as $row)
                        <div class="list-row">
                            <div>
                                <div class="list-title">{{ $row['product'] }}</div>
                                <div class="list-meta">{{ $row['sku'] }} · {{ qty($row['quantity']) }} unit</div>
                            </div>
                            <span class="fw-bold text-warning">{{ CurrencyFormatter::rupiah($row['stock_value']) }}</span>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Tidak ada slow stock" description="Seluruh produk memiliki aktivitas penjualan." />
                    @endforelse
                </x-metronic.card>
            </div>
            <div class="col-xl-4">
                <x-metronic.card title="Shift Kasir Aktif" subtitle="Shift terbuka dan menunggu closing" class="report-card-hover h-100">
                    @forelse($sections['active_shifts'] as $row)
                        <div class="mini-card mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold text-gray-900">{{ $row['number'] }}</div>
                                    <div class="text-muted fs-8">{{ $row['cashier'] ?: 'Tanpa kasir' }}</div>
                                </div>
                                <span class="badge badge-light-{{ $row['status']==='open' ? 'success' : 'warning' }} fw-bold">{{ str_replace('_',' ',$row['status']) }}</span>
                            </div>
                            <div class="text-muted fs-8 pt-2 border-top">Kas awal <span class="fw-bold text-gray-900">{{ CurrencyFormatter::rupiah($row['opening_cash_amount']) }}</span></div>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Tidak ada shift aktif" description="Seluruh shift kasir telah ditutup." />
                    @endforelse
                </x-metronic.card>
            </div>
        </div>
    @elseif($scope === 'retail')
        <div class="row g-5 mb-6">
            <div class="col-xl-6">
                <x-metronic.card title="Transaksi Terbaru" subtitle="Penjualan POS terakhir pada cakupan toko" class="report-card-hover h-100">
                    @forelse($sections['recent_sales'] as $row)
                        <div class="list-row">
                            <div>
                                <div class="list-title">{{ $row['number'] }}</div>
                                <div class="list-meta">{{ $row['cashier'] ?: 'Tanpa kasir' }} · {{ $row['status'] }}</div>
                            </div>
                            <span class="fw-bold text-success">{{ CurrencyFormatter::rupiah($row['amount']) }}</span>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Belum ada transaksi" description="Transaksi akan tampil sesuai periode." />
                    @endforelse
                </x-metronic.card>
            </div>
            <div class="col-xl-6">
                <x-metronic.card title="Shift Kasir Aktif" subtitle="Shift yang masih terbuka atau menunggu closing" class="report-card-hover h-100">
                    @forelse($sections['active_shifts'] as $row)
                        <div class="mini-card mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold text-gray-900">{{ $row['number'] }}</div>
                                    <div class="text-muted fs-8">{{ $row['cashier'] ?: 'Tanpa kasir' }}</div>
                                </div>
                                <span class="badge badge-light-{{ $row['status']==='open' ? 'success' : 'warning' }} fw-bold">{{ str_replace('_',' ',$row['status']) }}</span>
                            </div>
                            <div class="text-muted fs-8 pt-2 border-top">Kas awal <span class="fw-bold text-gray-900">{{ CurrencyFormatter::rupiah($row['opening_cash_amount']) }}</span></div>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Tidak ada shift aktif" description="Seluruh shift telah ditutup." />
                    @endforelse
                </x-metronic.card>
            </div>
        </div>

        <div class="row g-5 mb-6">
            <div class="col-xl-6">
                <x-metronic.card title="Stok Toko Kritis" class="report-card-hover h-100">
                    @forelse($sections['stock_alerts'] as $row)
                        <div class="list-row">
                            <div>
                                <div class="list-title">{{ $row['product'] }}</div>
                                <div class="list-meta">{{ $row['sku'] }} · minimum {{ qty($row['minimum_stock']) }}</div>
                            </div>
                            <span class="badge badge-light-{{ $row['status']==='empty' ? 'danger' : 'warning' }} fw-bold">{{ qty($row['available']) }}</span>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Stok toko aman" description="Tidak ada stok kritis." />
                    @endforelse
                </x-metronic.card>
            </div>
            <div class="col-xl-6">
                <x-metronic.card title="Produk Slow Moving" class="report-card-hover h-100">
                    @forelse($sections['slow_products'] as $row)
                        <div class="list-row">
                            <div>
                                <div class="list-title">{{ $row['product'] }}</div>
                                <div class="list-meta">{{ $row['sku'] }}</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">{{ qty($row['quantity']) }} unit</div>
                                <div class="text-muted fs-8">{{ CurrencyFormatter::rupiah($row['stock_value']) }}</div>
                            </div>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Tidak ada slow moving" description="Semua produk memiliki aktivitas penjualan." />
                    @endforelse
                </x-metronic.card>
            </div>
        </div>
    @else
        <div class="row g-5 mb-6">
            <div class="col-xl-6">
                <x-metronic.card title="Prioritas Restock" class="report-card-hover h-100">
                    @forelse($sections['restock_needed'] as $row)
                        <div class="list-row">
                            <div>
                                <div class="list-title">{{ $row['product'] }}</div>
                                <div class="list-meta">{{ $row['sku'] }} · minimum {{ qty($row['minimum_stock']) }}</div>
                            </div>
                            <span class="badge badge-light-danger fw-bold">{{ qty($row['available']) }} tersedia</span>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Tidak perlu restock" description="Persediaan berada di atas batas minimum." />
                    @endforelse
                </x-metronic.card>
            </div>
            <div class="col-xl-6">
                <x-metronic.card title="Mutasi Besar" class="report-card-hover h-100">
                    @forelse($sections['large_mutations'] as $row)
                        <div class="mini-card mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold text-gray-900">{{ $row['product'] }}</div>
                                    <div class="text-muted fs-8">{{ $row['sku'] }} · {{ $row['location'] }}</div>
                                </div>
                                <span class="fw-bold {{ $row['quantity_on_hand_change'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $row['quantity_on_hand_change'] >= 0 ? '+' : '' }}{{ qty($row['quantity_on_hand_change']) }}</span>
                            </div>
                            <div class="text-muted fs-8 text-capitalize">{{ str_replace('_',' ',$row['mutation_type']) }} · {{ \Illuminate\Support\Carbon::parse($row['occurred_at'])->format('d/m/Y H:i') }}</div>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Tidak ada mutasi besar" description="Tidak ada perubahan stok di atas ambang pemantauan." />
                    @endforelse
                </x-metronic.card>
            </div>
        </div>

        <div class="row g-5 mb-6">
            <div class="col-xl-6">
                <x-metronic.card title="Dead Stock" class="report-card-hover h-100">
                    @forelse($sections['dead_stock'] as $row)
                        <div class="list-row">
                            <div>
                                <div class="list-title">{{ $row['product'] }}</div>
                                <div class="list-meta">{{ $row['sku'] }}</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">{{ qty($row['quantity']) }} unit</div>
                                <div class="text-muted fs-8">{{ CurrencyFormatter::rupiah($row['stock_value']) }}</div>
                            </div>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Tidak ada dead stock" description="Seluruh produk memiliki pergerakan pada periode aktif." />
                    @endforelse
                </x-metronic.card>
            </div>
            <div class="col-xl-6">
                <x-metronic.card title="Produk dengan Nilai Stok Terbesar" class="report-card-hover h-100">
                    @forelse($sections['top_stocked_products'] as $row)
                        <div class="list-row">
                            <div>
                                <div class="list-title">{{ $row['product'] }}</div>
                                <div class="list-meta">{{ $row['sku'] }} · {{ qty($row['quantity']) }} unit</div>
                            </div>
                            <span class="fw-bold text-warning">{{ CurrencyFormatter::rupiah($row['stock_value']) }}</span>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Belum ada persediaan" description="Produk bernilai stok terbesar akan tampil di sini." />
                    @endforelse
                </x-metronic.card>
            </div>
        </div>
    @endif

    {{-- DETAIL TABLE --}}
    <x-metronic.card class="mb-6 report-card-hover">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h3 class="card-title fw-bold">{{ $scope==='warehouse' ? 'Detail Saldo Persediaan' : ($scope==='retail' ? 'Detail Penjualan per Kasir' : 'Rekap Omzet Harian') }}</h3>
                <div class="text-muted fs-7">Data sumber sesuai filter aktif · diperbarui {{ $report['last_updated_at']->format('d/m/Y H:i:s') }} WIB</div>
            </div>
        </div>
        <div class="table-responsive mt-3">
            <table class="table report-table table-row-dashed align-middle">
                <thead>
                    <tr>
                        @if($scope==='warehouse')
                            <th>Produk</th><th>Lokasi</th><th class="text-end">On Hand</th><th class="text-end">Reserved</th><th class="text-end">Rusak</th><th class="text-end">Tersedia</th><th class="text-end">Nilai Stok</th>
                        @elseif($scope==='retail')
                            <th>Tanggal</th><th>Toko/Cabang</th><th>Kasir</th><th class="text-end">Transaksi</th><th class="text-end">Omzet</th><th class="text-end">Margin</th>
                        @else
                            <th>Tanggal</th><th class="text-end">Retail POS</th><th class="text-end">B2B</th><th class="text-end">Total</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($report['rows'] as $row)
                        <tr>
                            @if($scope==='warehouse')
                                <td>
                                    <div class="fw-bold text-gray-900">{{ $row['product'] }}</div>
                                    <div class="text-muted fs-8">{{ $row['sku'] }}</div>
                                </td>
                                <td>{{ $row['location'] }}</td>
                                <td class="text-end">{{ qty($row['quantity_on_hand']) }}</td>
                                <td class="text-end">{{ qty($row['quantity_reserved']) }}</td>
                                <td class="text-end text-danger">{{ qty($row['quantity_damaged']) }}</td>
                                <td class="text-end fw-bold">{{ qty($row['available']) }}</td>
                                <td class="text-end fw-bold text-warning">{{ CurrencyFormatter::rupiah($row['cost_value']) }}</td>
                            @elseif($scope==='retail')
                                <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                                <td class="fw-bold">{{ $row['location'] ?: 'Tanpa lokasi' }}</td>
                                <td>{{ $row['cashier'] ?: 'Tanpa kasir' }}</td>
                                <td class="text-end">{{ number_format($row['transaction_count'],0,',','.') }}</td>
                                <td class="text-end fw-bold text-success">{{ CurrencyFormatter::rupiah($row['revenue']) }}</td>
                                <td class="text-end">{{ CurrencyFormatter::rupiah($row['margin']) }}</td>
                            @else
                                <td class="fw-bold">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                                <td class="text-end">{{ CurrencyFormatter::rupiah($row['retail']) }}</td>
                                <td class="text-end">{{ CurrencyFormatter::rupiah($row['b2b']) }}</td>
                                <td class="text-end fw-bold text-primary">{{ CurrencyFormatter::rupiah($row['total']) }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-metronic.empty-state title="Tidak ada data pada periode ini" description="Ubah periode atau lokasi untuk melihat data lainnya." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-metronic.card>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const scopeSelect = document.querySelector('#report-scope');
    const locationSelect = document.querySelector('#work-location');
    const locationLabel = document.querySelector('#location-label');
    const updateLocations = (reset = false) => {
        const scope = scopeSelect.value;
        if (reset) locationSelect.value = '';
        [...locationSelect.options].forEach((option, index) => {
            if (index === 0) return;
            const visible = scope === 'all' || option.dataset.locationType === (scope === 'retail' ? 'branch' : 'warehouse');
            option.hidden = !visible;
            option.disabled = !visible;
        });
        locationSelect.options[0].textContent = scope === 'retail' ? 'Semua toko/cabang' : (scope === 'warehouse' ? 'Semua gudang' : 'Semua toko & gudang');
        locationLabel.textContent = scope === 'retail' ? 'Toko/Cabang Spesifik' : (scope === 'warehouse' ? 'Gudang Spesifik' : 'Lokasi Spesifik');
    };
    scopeSelect?.addEventListener('change', () => updateLocations(true));
    updateLocations(false);

    if (!window.ApexCharts) return;
    const scope = @json($scope);
    const money = value => 'Rp '+new Intl.NumberFormat('id-ID', {notation:'compact'}).format(value);
    const common = {
        chart: { height: 340, toolbar: { show: false }, fontFamily: 'Inter', animations: { enabled: true } },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        grid: { borderColor: '#edf0f5', strokeDashArray: 4, padding: { left: 10, right: 10 } },
        legend: { position: 'top', horizontalAlign: 'left', offsetY: 0, markers: { width: 10, height: 10, radius: 12 } },
        noData: { text: 'Belum ada data' }
    };
    let primary;
    let secondary;
    if (scope === 'warehouse') {
        const movement = @json($charts['movement'] ?? []);
        const stocks = @json($charts['warehouse_stock'] ?? []);
        primary = {...common, chart:{...common.chart, type:'bar', stacked:false}, series:[{name:'Barang Masuk',data:movement.map(r=>Number(r.incoming))},{name:'Barang Keluar',data:movement.map(r=>Number(r.outgoing))}], colors:['#17c653','#f1416c'], xaxis:{categories:movement.map(r=>r.date)}, plotOptions:{bar:{borderRadius:6, columnWidth:'48%'}}};
        secondary = {chart:{type:'donut',height:320,fontFamily:'Inter'},series:stocks.map(r=>Number(r.stock_value)),labels:stocks.map(r=>r.name),colors:['#f6c000','#3e97ff','#50cd89','#7239ea','#f1416c'],legend:{position:'bottom'},stroke:{width:0},tooltip:{y:{formatter:money}},noData:{text:'Belum ada stok'},plotOptions:{pie:{donut:{size:'70%'}}}};
    } else if (scope === 'retail') {
        const revenue = @json($charts['revenue'] ?? []);
        const transactions = @json($charts['transactions'] ?? []);
        const payments = @json($charts['payment_methods'] ?? []);
        primary = {...common, chart:{...common.chart, type:'line'}, series:[{name:'Omzet',type:'area',data:revenue.map(r=>Number(r.retail))},{name:'Transaksi',type:'line',data:transactions.map(r=>Number(r.count))}], colors:['#17c653','#3e97ff'], fill:{type:['gradient','solid'],gradient:{opacityFrom:.28,opacityTo:.03}}, xaxis:{categories:revenue.map(r=>r.date)}, yaxis:[{labels:{formatter:money}},{opposite:true,labels:{formatter:v=>Math.round(v)}}], tooltip:{y:[{formatter:money},{formatter:v=>v+' transaksi'}]}};
        secondary = {chart:{type:'donut',height:320,fontFamily:'Inter'},series:payments.map(r=>Number(r.value)),labels:payments.map(r=>String(r.label).replaceAll('_',' ').toUpperCase()),colors:['#17c653','#3e97ff','#f6c000','#7239ea','#f1416c'],legend:{position:'bottom'},stroke:{width:0},tooltip:{y:{formatter:money}},noData:{text:'Belum ada pembayaran'},plotOptions:{pie:{donut:{size:'70%'}}}};
    } else {
        const revenue = @json($charts['revenue'] ?? []);
        const channels = @json($charts['channel_mix'] ?? []);
        primary = {...common, chart:{...common.chart, type:'area'}, series:[{name:'Retail POS',data:revenue.map(r=>Number(r.retail))},{name:'B2B',data:revenue.map(r=>Number(r.b2b))}], colors:['#3e97ff','#17c653'], fill:{type:'gradient',gradient:{opacityFrom:.28,opacityTo:.03}}, xaxis:{categories:revenue.map(r=>r.date)}, yaxis:{labels:{formatter:money}}, tooltip:{y:{formatter:value=>'Rp '+new Intl.NumberFormat('id-ID').format(value)}}};
        secondary = {chart:{type:'donut',height:320,fontFamily:'Inter'},series:channels.map(r=>Number(r.value)),labels:channels.map(r=>r.label),colors:['#3e97ff','#17c653'],legend:{position:'bottom'},stroke:{width:0},tooltip:{y:{formatter:money}},plotOptions:{pie:{donut:{size:'70%'}}},noData:{text:'Belum ada omzet'}};
    }
    new ApexCharts(document.querySelector('#daily-primary-chart'), primary).render();
    new ApexCharts(document.querySelector('#daily-secondary-chart'), secondary).render();
});
</script>
@endpush
