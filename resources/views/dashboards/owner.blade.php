@extends('layouts.metronic.app')

@php
    use App\Support\CurrencyFormatter;
    $kpis = $dashboard['kpis'];
    $charts = $dashboard['charts'];
    $stores = $dashboard['stores'];
    $warehouses = $dashboard['warehouses'];
    $totalTransactions = collect($stores)->sum('transactions');
@endphp

@section('title', 'Dashboard Owner - ' . config('app.name'))
@section('page_title', 'Dashboard Owner')

@push('styles')
<style>
    .owner-dashboard { --owner-navy:#111b35; --owner-blue:#3e63dd; --owner-soft:#f4f7fc; }
    .owner-hero { background:linear-gradient(125deg,#111b35 0%,#243b73 58%,#3e63dd 100%); border-radius:1.25rem; overflow:hidden; position:relative; }
    .owner-hero:after { content:""; position:absolute; width:320px; height:320px; border:55px solid rgba(255,255,255,.06); border-radius:50%; right:-90px; top:-140px; }
    .owner-hero > * { position:relative; z-index:1; }
    .owner-kpi { border:0; border-radius:1rem; box-shadow:0 5px 22px rgba(28,45,85,.07); transition:transform .2s ease,box-shadow .2s ease; }
    .owner-kpi:hover { transform:translateY(-3px); box-shadow:0 10px 28px rgba(28,45,85,.12); }
    .owner-kpi .icon-box { width:44px; height:44px; border-radius:.85rem; display:grid; place-items:center; }
    .owner-panel { border:0; border-radius:1rem; box-shadow:0 5px 22px rgba(28,45,85,.06); }
    .owner-section-title { font-size:1.25rem; font-weight:700; color:#17213d; }
    .owner-store-card { border:1px solid #e9edf5; border-radius:1rem; height:100%; transition:border-color .2s ease; }
    .owner-store-card:hover { border-color:#9eb2f4; }
    .owner-rank { width:34px; height:34px; border-radius:10px; display:grid; place-items:center; background:#eef2ff; color:#3e63dd; font-weight:700; }
    .metric-label { color:#8a94a8; font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; }
    .progress-thin { height:6px; }
    .owner-table thead th { color:#8a94a8; font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; border-bottom-color:#edf0f5; }
    [data-bs-theme="dark"] .owner-section-title { color:#f1f3f8; }
    [data-bs-theme="dark"] .owner-store-card { border-color:#2b3245; }
</style>
@endpush

@section('page_guide')
<x-metronic.page-guide id="owner-dashboard" title="Panduan Dashboard Owner">
    <x-slot:function>Memantau performa perusahaan secara menyeluruh dan membandingkan setiap toko serta gudang.</x-slot:function>
    <x-slot:workflow>Pilih periode dan lokasi, tinjau tren, lalu fokus pada unit dengan indikator risiko.</x-slot:workflow>
    <x-slot:parts>KPI utama, tren omzet, komposisi kanal, performa toko, kesehatan gudang, produk terlaris, dan alert.</x-slot:parts>
    <x-slot:impacts>Filter lokasi memengaruhi seluruh angka, grafik, dan tabel pada halaman ini.</x-slot:impacts>
    <x-slot:operation>Gunakan kartu per unit untuk membandingkan omzet, margin, stok, dan aktivitas operasional.</x-slot:operation>
    <x-slot:warnings>Margin B2B masih bersifat estimasi sesuai snapshot data transaksi yang tersedia.</x-slot:warnings>
    <x-slot:example>Gunakan periode bulan berjalan dan Semua Lokasi untuk evaluasi kinerja perusahaan.</x-slot:example>
</x-metronic.page-guide>
@endsection

@section('content')
<div class="owner-dashboard">
    <div class="owner-hero p-7 p-lg-10 mb-6 text-white">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-6 align-items-lg-center">
            <div>
                <span class="badge badge-light-primary mb-4">Ringkasan Bisnis</span>
                <h1 class="text-white fw-bold fs-2x mb-2">Selamat datang, {{ auth()->user()->name }}</h1>
                <div class="text-white opacity-75 fs-6">Pantau denyut bisnis, performa toko, dan kesehatan gudang dalam satu layar.</div>
            </div>
            <div class="d-flex gap-3 flex-wrap">
                <div class="bg-white bg-opacity-10 rounded-3 px-5 py-3"><div class="opacity-75 fs-8">PERIODE</div><div class="fw-bold">{{ $filters['start']->format('d M') }} - {{ $filters['end']->format('d M Y') }}</div></div>
                <a href="{{ route('reports.daily.index', request()->query()) }}" class="btn btn-light align-self-stretch d-flex align-items-center">Buka Laporan Harian</a>
            </div>
        </div>
    </div>

    <div class="card owner-panel mb-6"><div class="card-body py-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3 col-xl-2"><label class="form-label fw-semibold">Mulai</label><input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="form-control"></div>
            <div class="col-md-3 col-xl-2"><label class="form-label fw-semibold">Sampai</label><input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="form-control"></div>
            <div class="col-md-4 col-xl-3"><label class="form-label fw-semibold">Unit Operasional</label><select name="work_location_id" class="form-select"><option value="">Semua toko & gudang</option>@foreach($workLocations as $location)<option value="{{ $location->id }}" @selected((string) request('work_location_id') === (string) $location->id)>{{ $location->type === 'warehouse' ? 'Gudang' : 'Toko' }} · {{ $location->name }}</option>@endforeach</select></div>
            <div class="col-md-2 col-xl-2"><label class="form-label fw-semibold">Rentang Grafik</label><select name="range" class="form-select"><option value="daily" @selected($filters['range']==='daily')>Harian</option><option value="monthly" @selected($filters['range']==='monthly')>Bulanan</option><option value="yearly" @selected($filters['range']==='yearly')>Tahunan</option></select></div>
            <div class="col-md-12 col-xl-3 d-flex gap-2"><button class="btn btn-primary flex-grow-1">Terapkan</button><a href="{{ route('owner.dashboard') }}" class="btn btn-light">Reset</a></div>
        </form>
    </div></div>

    <div class="row g-5 mb-7">
        @foreach([
            ['Omzet Perusahaan', CurrencyFormatter::rupiah($kpis['revenue']), 'ki-chart-simple-2', 'primary', 'Retail + B2B'],
            ['Laba Kotor', CurrencyFormatter::rupiah($kpis['gross_margin']), 'ki-arrow-up', 'success', $kpis['margin_percent'].'% margin'],
            ['Nilai Persediaan', CurrencyFormatter::rupiah($kpis['stock_value']), 'ki-package', 'warning', $kpis['critical_stock_count'].' produk kritis'],
            ['Piutang Berjalan', CurrencyFormatter::rupiah($kpis['receivable_outstanding']), 'ki-wallet', 'info', CurrencyFormatter::rupiah($kpis['overdue_receivable']).' lewat tempo'],
        ] as [$label,$value,$icon,$color,$hint])
        <div class="col-sm-6 col-xl-3"><div class="card owner-kpi h-100"><div class="card-body p-5"><div class="d-flex justify-content-between mb-5"><div class="icon-box bg-light-{{ $color }}"><i class="ki-outline {{ $icon }} fs-2 text-{{ $color }}"></i></div><span class="badge badge-light-{{ $color }} align-self-start">Periode aktif</span></div><div class="text-muted fw-semibold mb-1">{{ $label }}</div><div class="fs-3 fw-bold text-gray-900 mb-2">{{ $value }}</div><div class="text-muted fs-8">{{ $hint }}</div></div></div></div>
        @endforeach
    </div>

    <div class="row g-5 mb-7">
        <div class="col-xl-8"><div class="card owner-panel h-100"><div class="card-header border-0 pt-5"><div><div class="owner-section-title">Tren Pendapatan</div><div class="text-muted">Perbandingan omzet retail dan B2B</div></div><div class="card-toolbar"><span class="badge badge-light-success">{{ $totalTransactions }} transaksi toko</span></div></div><div class="card-body pt-2"><div id="owner-revenue-chart" style="height:350px"></div></div></div></div>
        <div class="col-xl-4"><div class="card owner-panel h-100"><div class="card-header border-0 pt-5"><div><div class="owner-section-title">Komposisi Omzet</div><div class="text-muted">Kontribusi berdasarkan kanal</div></div></div><div class="card-body pt-0"><div id="owner-channel-chart" style="height:245px"></div><div class="row g-3 text-center mt-2"><div class="col-6"><div class="metric-label">Retail</div><div class="fw-bold">{{ CurrencyFormatter::rupiah($charts['channel_mix'][0]['value']) }}</div></div><div class="col-6"><div class="metric-label">B2B</div><div class="fw-bold">{{ CurrencyFormatter::rupiah($charts['channel_mix'][1]['value']) }}</div></div></div></div></div></div>
    </div>

    <div class="d-flex justify-content-between align-items-end mb-4"><div><div class="owner-section-title">Performa Setiap Toko</div><div class="text-muted">Bandingkan kontribusi penjualan dan kesiapan stok antar toko</div></div><span class="badge badge-light-primary">{{ count($stores) }} toko aktif</span></div>
    <div class="row g-5 mb-8">
        @forelse($stores as $index => $store)
        <div class="col-md-6 col-xl-4"><div class="card owner-store-card"><div class="card-body p-5"><div class="d-flex align-items-center gap-3 mb-5"><div class="owner-rank">{{ $index + 1 }}</div><div class="flex-grow-1"><div class="fw-bold fs-5 text-gray-900">{{ $store['name'] }}</div><div class="text-muted fs-8">{{ $store['code'] }}</div></div><span class="badge badge-light-{{ $store['critical_count'] ? 'warning' : 'success' }}">{{ $store['critical_count'] }} kritis</span></div><div class="metric-label mb-1">Omzet</div><div class="fs-3 fw-bold text-primary mb-4">{{ CurrencyFormatter::rupiah($store['revenue']) }}</div><div class="row g-3 mb-4"><div class="col-4"><div class="metric-label">Transaksi</div><div class="fw-bold">{{ number_format($store['transactions'],0,',','.') }}</div></div><div class="col-4"><div class="metric-label">Margin</div><div class="fw-bold text-success">{{ $store['margin_percent'] }}%</div></div><div class="col-4"><div class="metric-label">Rata-rata</div><div class="fw-bold">{{ CurrencyFormatter::rupiah($store['average_ticket']) }}</div></div></div><div class="border-top pt-4 d-flex justify-content-between"><span class="text-muted">Nilai stok · {{ qty($store['available_quantity']) }} unit</span><span class="fw-semibold">{{ CurrencyFormatter::rupiah($store['stock_value']) }}</span></div></div></div></div>
        @empty
        <div class="col-12"><x-metronic.empty-state title="Belum ada data toko" description="Toko dalam cakupan akses akan tampil di sini." /></div>
        @endforelse
    </div>

    <div class="row g-5 mb-7">
        <div class="col-xl-8"><div class="card owner-panel h-100"><div class="card-header border-0 pt-5"><div><div class="owner-section-title">Kesehatan Setiap Gudang</div><div class="text-muted">Posisi persediaan dan arus barang selama periode aktif</div></div></div><div class="card-body pt-2"><div class="table-responsive"><table class="table owner-table align-middle"><thead><tr><th>Gudang</th><th class="text-end">Nilai Stok</th><th class="text-end">Tersedia</th><th class="text-end">Masuk / Keluar</th><th class="text-end">Risiko</th></tr></thead><tbody>@forelse($warehouses as $warehouse)<tr><td><div class="fw-bold text-gray-900">{{ $warehouse['name'] }}</div><div class="text-muted fs-8">{{ $warehouse['code'] }} · {{ $warehouse['movement_count'] }} mutasi</div></td><td class="text-end fw-bold">{{ CurrencyFormatter::rupiah($warehouse['stock_value']) }}</td><td class="text-end">{{ qty($warehouse['available_quantity']) }}</td><td class="text-end"><span class="text-success">+{{ qty($warehouse['incoming']) }}</span> <span class="text-muted">/</span> <span class="text-danger">-{{ qty($warehouse['outgoing']) }}</span></td><td class="text-end"><span class="badge badge-light-{{ $warehouse['empty_count'] ? 'danger' : ($warehouse['critical_count'] ? 'warning' : 'success') }}">{{ $warehouse['empty_count'] }} kosong · {{ $warehouse['critical_count'] }} kritis</span></td></tr>@empty<tr><td colspan="5"><x-metronic.empty-state title="Belum ada data gudang" description="Gudang dalam cakupan akses akan tampil di sini." /></td></tr>@endforelse</tbody></table></div></div></div></div>
        <div class="col-xl-4"><div class="card owner-panel h-100"><div class="card-header border-0 pt-5"><div class="owner-section-title">Perlu Perhatian</div></div><div class="card-body pt-2">@foreach([['Pending approval',$kpis['pending_approval'],'warning'],['Anomali terbuka',$kpis['anomaly_open'],'danger'],['Stok kritis',$kpis['critical_stock_count'],'danger'],['Kehadiran terlambat',$kpis['attendance_late'],'warning']] as [$label,$value,$color])<div class="d-flex align-items-center justify-content-between py-3 border-bottom"><div class="d-flex align-items-center gap-3"><span class="bullet bullet-dot bg-{{ $color }} h-10px w-10px"></span><span class="fw-semibold">{{ $label }}</span></div><span class="badge badge-light-{{ $color }}">{{ $value }}</span></div>@endforeach<div class="rounded bg-light-primary p-4 mt-5"><div class="text-muted fs-8 mb-1">Selisih kas</div><div class="fw-bold fs-4 text-primary">{{ CurrencyFormatter::rupiah($kpis['cash_difference']) }}</div></div></div></div></div>
    </div>

    <div class="row g-5 mb-5"><div class="col-xl-7"><div class="card owner-panel h-100"><div class="card-header border-0 pt-5"><div class="owner-section-title">Produk Terlaris</div></div><div class="card-body pt-0"><div class="table-responsive"><table class="table owner-table align-middle"><thead><tr><th>Produk</th><th class="text-end">Terjual</th><th class="text-end">Omzet</th></tr></thead><tbody>@forelse($charts['top_products'] as $index=>$row)<tr><td><div class="d-flex align-items-center gap-3"><div class="owner-rank">{{ $index+1 }}</div><div><div class="fw-bold text-gray-900">{{ $row['product'] }}</div><div class="text-muted fs-8">{{ $row['sku'] }}</div></div></div></td><td class="text-end fw-semibold">{{ qty($row['quantity']) }}</td><td class="text-end fw-bold text-primary">{{ CurrencyFormatter::rupiah($row['revenue']) }}</td></tr>@empty<tr><td colspan="3"><x-metronic.empty-state title="Belum ada produk terjual" description="Data akan muncul setelah transaksi selesai." /></td></tr>@endforelse</tbody></table></div></div></div></div><div class="col-xl-5"><div class="card owner-panel h-100"><div class="card-header border-0 pt-5"><div><div class="owner-section-title">Margin per Toko</div><div class="text-muted">Omzet dibanding laba kotor</div></div></div><div class="card-body pt-2"><div id="owner-margin-chart" style="height:310px"></div></div></div></div></div>
    <div class="text-muted text-end fs-8">Data diperbarui {{ $dashboard['last_updated_at']->format('d/m/Y H:i:s') }} WIB</div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (!window.ApexCharts) return;
    const revenue = @json($charts['daily_revenue']);
    const margin = @json($charts['branch_margin']);
    const chartText = getComputedStyle(document.documentElement).getPropertyValue('--bs-gray-600').trim() || '#7e8299';
    new ApexCharts(document.querySelector('#owner-revenue-chart'), {chart:{type:'area',height:350,toolbar:{show:false},fontFamily:'Inter'},series:[{name:'Retail',data:revenue.map(r=>Number(r.retail))},{name:'B2B',data:revenue.map(r=>Number(r.b2b))}],colors:['#3e63dd','#17c1a3'],stroke:{curve:'smooth',width:3},fill:{type:'gradient',gradient:{opacityFrom:.28,opacityTo:.03}},dataLabels:{enabled:false},grid:{borderColor:'#eef1f6',strokeDashArray:4},xaxis:{categories:revenue.map(r=>r.date),labels:{style:{colors:chartText}}},yaxis:{labels:{formatter:v=>'Rp '+new Intl.NumberFormat('id-ID',{notation:'compact'}).format(v),style:{colors:chartText}}},tooltip:{y:{formatter:v=>'Rp '+new Intl.NumberFormat('id-ID').format(v)}}}).render();
    new ApexCharts(document.querySelector('#owner-channel-chart'), {chart:{type:'donut',height:245},series:@json($charts['channel_mix']).map(r=>Number(r.value)),labels:['Retail POS','B2B'],colors:['#3e63dd','#17c1a3'],stroke:{width:0},legend:{position:'bottom'},plotOptions:{pie:{donut:{size:'72%',labels:{show:true,total:{show:true,label:'Total',formatter:()=> '100%'}}}}}}).render();
    new ApexCharts(document.querySelector('#owner-margin-chart'), {chart:{type:'bar',height:310,toolbar:{show:false}},series:[{name:'Omzet',data:margin.map(r=>Number(r.revenue))},{name:'Margin',data:margin.map(r=>Number(r.margin))}],colors:['#3e63dd','#17c1a3'],plotOptions:{bar:{horizontal:true,borderRadius:4,barHeight:'55%'}},dataLabels:{enabled:false},xaxis:{categories:margin.map(r=>r.label),labels:{formatter:v=>'Rp '+new Intl.NumberFormat('id-ID',{notation:'compact'}).format(v)}},legend:{position:'top'}}).render();
});
</script>
@endpush
