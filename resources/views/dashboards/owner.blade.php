@extends('layouts.metronic.app')

@php
    $kpis = $dashboard['kpis'];
    $charts = $dashboard['charts'];
@endphp

@section('title', 'Dashboard Owner - ' . config('app.name'))
@section('page_title', 'Dashboard Owner')

@section('content')
    <x-metronic.page-title title="Dashboard Owner" description="Ringkasan Bisnis — DASH-01 KPI omzet, margin, stok, piutang, kas, kehadiran, approval, dan anomali.">
        <a href="{{ route('reports.daily.index', request()->query()) }}" class="btn btn-light-primary">Laporan Harian</a>
    </x-metronic.page-title>

    @include('reports.partials.filter', ['filters' => $filters])

    @include('reports.partials.kpi-grid', ['items' => [
        ['label' => 'Omzet', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['revenue']), 'color' => 'primary', 'description' => 'POS + B2B non-cancelled'],
        ['label' => 'Laba Kotor Estimasi', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['gross_margin']), 'color' => 'success', 'description' => 'Snapshot margin transaksi'],
        ['label' => 'Margin', 'value' => $kpis['margin_percent'].'%', 'color' => 'info', 'description' => 'Gross margin / omzet'],
        ['label' => 'Nilai Stok', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['stock_value']), 'color' => 'warning', 'description' => 'stocks.cost_value'],
        ['label' => 'Stok Kritis', 'value' => $kpis['critical_stock_count'], 'color' => 'danger'],
        ['label' => 'Piutang Outstanding', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['receivable_outstanding']), 'color' => 'warning'],
        ['label' => 'Selisih Kas', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['cash_difference']), 'color' => 'danger'],
        ['label' => 'Anomali Open', 'value' => $kpis['anomaly_open'], 'color' => 'danger'],
    ]])

    <div class="row g-5 mb-5">
        <div class="col-lg-8">
            <x-metronic.card title="Grafik Omzet Harian">
                <div id="owner-revenue-chart" style="height: 320px"></div>
                <div class="text-muted">Retail vs B2B berdasarkan periode filter. Last updated {{ $dashboard['last_updated_at']->format('d/m/Y H:i:s') }}</div>
            </x-metronic.card>
        </div>
        <div class="col-lg-4">
            @include('reports.partials.definitions', ['definitions' => $definitions])
        </div>
    </div>

    <div class="row g-5">
        <div class="col-lg-6">
            <x-metronic.card title="Top 10 Produk">
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Produk</th><th>Qty</th><th>Omzet</th></tr></thead><tbody>
                    @forelse($charts['top_products'] as $row)
                        <tr><td>{{ $row['sku'] }} — {{ $row['product'] }}</td><td>{{ $row['quantity'] }}</td><td>{{ \App\Support\CurrencyFormatter::rupiah($row['revenue']) }}</td></tr>
                    @empty
                        <tr><td colspan="3"><x-metronic.empty-state title="Belum ada produk terjual" description="Data top produk akan muncul setelah transaksi selesai." /></td></tr>
                    @endforelse
                </tbody></table></div>
            </x-metronic.card>
        </div>
        <div class="col-lg-6">
            <x-metronic.card title="Alert dan Pending Approval">
                <div class="d-flex justify-content-between mb-3"><span>Pending Approval</span><span class="badge badge-light-warning">{{ $kpis['pending_approval'] }}</span></div>
                <div class="d-flex justify-content-between mb-3"><span>Fast Moving</span><span class="fw-bold">{{ $kpis['fast_moving_count'] }}</span></div>
                <div class="d-flex justify-content-between mb-3"><span>Slow/Dead Stock</span><span class="fw-bold">{{ $kpis['slow_moving_count'] }}</span></div>
                <div class="d-flex justify-content-between mb-3"><span>Retur/Loss Value</span><span class="fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($kpis['returns_value']) }}</span></div>
                <div class="d-flex justify-content-between"><span>Kehadiran Telat</span><span class="fw-bold">{{ $kpis['attendance_late'] }}</span></div>
            </x-metronic.card>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (!window.ApexCharts) return;
    const rows = @json($charts['daily_revenue']);
    new ApexCharts(document.querySelector('#owner-revenue-chart'), {
        chart: { type: 'area', height: 320, toolbar: { show: false } },
        series: [
            { name: 'Retail', data: rows.map(row => Number(row.retail)) },
            { name: 'B2B', data: rows.map(row => Number(row.b2b)) },
        ],
        xaxis: { categories: rows.map(row => row.date) },
        stroke: { curve: 'smooth' }
    }).render();
});
</script>
@endpush
