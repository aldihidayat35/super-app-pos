@extends('layouts.metronic.app')

@php
    $kpis = $dashboard['kpis'];
    $charts = $dashboard['charts'];
    $range = $filters['range'] ?? 'daily';

    $revenueByRange = [
        'daily' => $charts['daily_revenue'] ?? [],
        'monthly' => $charts['monthly_revenue'] ?? [],
        'yearly' => $charts['yearly_revenue'] ?? [],
    ];
    $transactionsByRange = [
        'daily' => $charts['daily_transactions'] ?? [],
        'monthly' => $charts['monthly_transactions'] ?? [],
        'yearly' => $charts['yearly_transactions'] ?? [],
    ];

    $paymentMethods = $charts['payment_methods'] ?? [];

    $rangeLabel = match ($range) {
        'monthly' => 'Bulanan',
        'yearly' => 'Tahunan',
        default => 'Harian',
    };

    $baseQuery = request()->query();
@endphp

@section('title', 'Dashboard Retail - ' . config('app.name'))
@section('page_title', 'Dashboard Retail')

@section('content')
    <x-metronic.page-title title="Dashboard Retail / Cabang" description="DASH-03 omzet, margin, transaksi, stok kritis, shift, closing, retur, void, dan pembayaran.">
        <a href="{{ route('reports.retail.index', request()->query()) }}" class="btn btn-light-primary">Laporan Toko</a>
    </x-metronic.page-title>

    @include('reports.partials.filter', ['filters' => $filters])

    @include('reports.partials.kpi-grid', ['items' => [
        ['label' => 'Omzet', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['revenue']), 'color' => 'primary'],
        ['label' => 'Margin', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['margin']).' / '.$kpis['margin_percent'].'%', 'color' => 'success'],
        ['label' => 'Transaksi', 'value' => $kpis['transaction_count'], 'color' => 'info'],
        ['label' => 'Rata-rata Nota', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['average_ticket']), 'color' => 'primary'],
        ['label' => 'Stok Kritis', 'value' => $kpis['critical_stock_count'], 'color' => 'danger'],
        ['label' => 'Shift Aktif', 'value' => $kpis['active_shift_count'], 'color' => 'warning'],
        ['label' => 'Closing Pending', 'value' => $kpis['closing_pending_count'], 'color' => 'warning'],
        ['label' => 'Selisih Kas', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['cash_difference']), 'color' => 'danger'],
    ]])

    {{-- Baris 1: Grafik Omzet + Distribusi Pembayaran --}}
    <div class="row g-5 mb-5">
        <div class="col-lg-8">
            <x-metronic.card title="Grafik Omzet {{ $rangeLabel }}">
                <div id="retail-range-buttons" class="btn-group btn-group-sm mb-3" role="group" aria-label="Pilih range waktu">
                    @foreach (['daily' => 'Harian', 'monthly' => 'Bulanan', 'yearly' => 'Tahunan'] as $key => $label)
                        @php($query = array_merge($baseQuery, ['range' => $key, 'page' => null]))
                        <a href="{{ url()->current() . '?' . http_build_query(array_filter($query, fn ($v) => $v !== null)) }}" class="btn btn-light-primary range-btn {{ $range === $key ? 'active' : '' }}" data-range="{{ $key }}">{{ $label }}</a>
                    @endforeach
                </div>
                <div id="retail-revenue-chart" style="height: 320px"></div>
                <div class="text-muted">Last updated {{ $dashboard['last_updated_at']->format('d/m/Y H:i:s') }}</div>
            </x-metronic.card>
        </div>
        <div class="col-lg-4">
            <x-metronic.card title="Distribusi Metode Pembayaran">
                <div id="retail-payment-chart" style="height: 320px"></div>
            </x-metronic.card>
        </div>
    </div>

    {{-- Baris 2: Grafik Jumlah Transaksi + Margin vs Omzet --}}
    <div class="row g-5 mb-5">
        <div class="col-lg-6">
            <x-metronic.card title="Grafik Jumlah Transaksi {{ $rangeLabel }}">
                <div id="retail-transactions-chart" style="height: 320px"></div>
                <div class="text-muted">Jumlah transaksi POS berdasarkan range waktu aktif.</div>
            </x-metronic.card>
        </div>
        <div class="col-lg-6">
            <x-metronic.card title="Grafik Margin vs Omzet {{ $rangeLabel }}">
                <div id="retail-margin-chart" style="height: 320px"></div>
                <div class="text-muted">Perbandingan omzet dan estimasi margin berdasarkan range aktif.</div>
            </x-metronic.card>
        </div>
    </div>

    {{-- Baris 3: Tabel Pendukung --}}
    <div class="row g-5">
        <div class="col-lg-7">
            <x-metronic.card title="Metode Pembayaran">
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Metode</th><th>Nilai</th></tr></thead><tbody>
                    @forelse($paymentMethods as $row)
                        <tr><td>{{ $row['label'] }}</td><td>{{ \App\Support\CurrencyFormatter::rupiah($row['value']) }}</td></tr>
                    @empty
                        <tr><td colspan="2"><x-metronic.empty-state title="Belum ada pembayaran" description="Metode pembayaran muncul setelah transaksi POS." /></td></tr>
                    @endforelse
                </tbody></table></div>
            </x-metronic.card>
        </div>
        <div class="col-lg-5">
            @include('reports.partials.definitions', ['definitions' => $definitions])
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (!window.ApexCharts) return;

    const revenueDatasets = @json($revenueByRange);
    const transactionDatasets = @json($transactionsByRange);
    const paymentRows = @json($paymentMethods);
    const marginPercent = {{ (float) ($kpis['margin_percent'] ?? 0) }};
    const activeRange = @json($range);

    const formatRupiah = (val) => {
        const n = Number(val || 0);
        if (n >= 1_000_000) return 'Rp ' + (n / 1_000_000).toFixed(1) + 'jt';
        if (n >= 1_000) return 'Rp ' + (n / 1_000).toFixed(0) + 'rb';
        return 'Rp ' + n.toFixed(0);
    };

    const formatRangeLabel = (raw) => {
        if (!raw) return '';
        if (activeRange === 'monthly' && /^\d{4}-\d{2}$/.test(raw)) {
            const [y, m] = raw.split('-');
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            return monthNames[parseInt(m, 10) - 1] + ' ' + y;
        }
        return raw;
    };

    const renderRevenueChart = (rows) => {
        const el = document.querySelector('#retail-revenue-chart');
        if (!el) return;
        el.innerHTML = '';
        if (!rows || rows.length === 0) {
            el.innerHTML = '<div class="text-center text-muted py-10">Belum ada data omzet pada range ini</div>';
            return;
        }
        const categories = rows.map(row => formatRangeLabel(row.date));
        const series = rows.map(row => Number(row.retail || 0));
        const chart = new ApexCharts(el, {
            chart: { type: 'area', height: 320, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
            series: [{ name: 'Omzet', data: series }],
            xaxis: { categories: categories, labels: { style: { colors: '#94a3b8' } } },
            colors: ['#4f46e5'],
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.45, opacityTo: 0.05 } },
            dataLabels: { enabled: false },
            yaxis: { labels: { style: { colors: '#94a3b8' }, formatter: formatRupiah } },
            grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
            legend: { position: 'top' },
            tooltip: { theme: 'light', y: { formatter: formatRupiah } },
        });
        chart.render();
        window.__retailRevenueChart = chart;
    };

    const renderTransactionsChart = (rows) => {
        const el = document.querySelector('#retail-transactions-chart');
        if (!el) return;
        el.innerHTML = '';
        if (!rows || rows.length === 0) {
            el.innerHTML = '<div class="text-center text-muted py-10">Belum ada transaksi pada range ini</div>';
            return;
        }
        const categories = rows.map(row => formatRangeLabel(row.date));
        const series = rows.map(row => Number(row.count || 0));
        const chart = new ApexCharts(el, {
            chart: { type: 'bar', height: 320, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
            series: [{ name: 'Jumlah Transaksi', data: series }],
            xaxis: { categories: categories, labels: { style: { colors: '#94a3b8' } } },
            colors: ['#10b981'],
            plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
            dataLabels: { enabled: false },
            yaxis: { labels: { style: { colors: '#94a3b8' }, formatter: (val) => val.toFixed(0) } },
            grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
            legend: { position: 'top' },
            tooltip: { y: { formatter: (val) => val.toFixed(0) + ' transaksi' } },
        });
        chart.render();
        window.__retailTransactionsChart = chart;
    };

    const renderMarginChart = (rows) => {
        const el = document.querySelector('#retail-margin-chart');
        if (!el) return;
        el.innerHTML = '';
        if (!rows || rows.length === 0) {
            el.innerHTML = '<div class="text-center text-muted py-10">Belum ada data margin pada range ini</div>';
            return;
        }
        const categories = rows.map(row => formatRangeLabel(row.date));
        const revenueSeries = rows.map(row => Number(row.retail || 0));
        const marginSeries = rows.map((row) => {
            const rev = Number(row.retail || 0);
            return rev > 0 ? Number((rev * marginPercent / 100).toFixed(0)) : 0;
        });
        const chart = new ApexCharts(el, {
            chart: { type: 'line', height: 320, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
            series: [
                { name: 'Omzet', data: revenueSeries },
                { name: 'Margin (est.)', data: marginSeries },
            ],
            xaxis: { categories: categories, labels: { style: { colors: '#94a3b8' } } },
            colors: ['#4f46e5', '#10b981'],
            stroke: { curve: 'smooth', width: [3, 3] },
            dataLabels: { enabled: false },
            yaxis: { labels: { style: { colors: '#94a3b8' }, formatter: formatRupiah } },
            grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
            legend: { position: 'top' },
            tooltip: { theme: 'light', y: { formatter: formatRupiah } },
        });
        chart.render();
        window.__retailMarginChart = chart;
    };

    // Distribusi Metode Pembayaran (Donut) - tidak tergantung range
    const paymentEl = document.querySelector('#retail-payment-chart');
    if (paymentEl) {
        if (paymentRows.length === 0) {
            paymentEl.innerHTML = '<div class="text-center text-muted py-10">Belum ada pembayaran pada periode ini</div>';
        } else {
            new ApexCharts(paymentEl, {
                series: paymentRows.map(row => Number(row.value || 0)),
                chart: { type: 'donut', height: 320, fontFamily: 'Inter, sans-serif' },
                labels: paymentRows.map(row => row.label),
                colors: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6'],
                legend: { position: 'bottom' },
                dataLabels: { enabled: true, formatter: (val) => val.toFixed(1) + '%' },
                tooltip: { y: { formatter: formatRupiah } },
                plotOptions: {
                    pie: {
                        donut: {
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'Total Pembayaran',
                                    formatter: (w) => {
                                        const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                        return formatRupiah(total);
                                    },
                                },
                            },
                        },
                    },
                },
            }).render();
        }
    }

    // Initial render untuk grafik yang bergantung range
    renderRevenueChart(revenueDatasets[activeRange]);
    renderTransactionsChart(transactionDatasets[activeRange]);
    renderMarginChart(revenueDatasets[activeRange]);
});
</script>
@endpush
