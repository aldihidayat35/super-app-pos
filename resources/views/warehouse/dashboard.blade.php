@extends('layouts.metronic.app')

@php($kpis = $dashboard['kpis'])

@section('title', 'Dashboard Gudang - ' . config('app.name'))
@section('page_title', 'Dashboard Gudang')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-dashboard" title="Panduan Halaman Dashboard Gudang">
        <x-slot:function>
            <p>Halaman ini memberi ringkasan kondisi persediaan dan pekerjaan gudang yang dapat diakses pengguna sesuai lokasi kerjanya. Kepala Gudang, Staff Gudang, Purchasing, dan Owner biasanya memakainya untuk menentukan pekerjaan yang perlu didahulukan.</p>
            <p>Data berasal dari saldo stok, mutasi, purchase order, transfer, order B2B, penerimaan, dan stock opname. Dashboard hanya menampilkan ringkasan; perubahan data dilakukan melalui modul transaksi terkait.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Sistem membaca lokasi kerja yang boleh diakses oleh akun Anda.</li><li>Filter laporan diterapkan pada data operasional.</li><li>KPI stok dan dokumen dihitung dari data terbaru.</li><li>Mutasi bernilai besar ditampilkan sebagai bahan pemeriksaan.</li><li>Gunakan tombol tindakan atau halaman laporan untuk menindaklanjuti temuan.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Filter:</strong> membatasi periode dan lokasi data dashboard.</li><li><strong>Stok Tersedia:</strong> jumlah on hand dikurangi reserved dan rusak.</li><li><strong>Reserved:</strong> stok yang sudah dialokasikan sehingga tidak bebas digunakan.</li><li><strong>Rusak:</strong> stok yang dipisahkan dari stok layak jual.</li><li><strong>Nilai Persediaan:</strong> nilai stok berdasarkan HPP yang tersimpan.</li><li><strong>Stok Kritis/Kosong:</strong> produk yang perlu perhatian atau pengadaan.</li><li><strong>Dokumen Pending:</strong> pekerjaan PO, transfer, order, receipt, dan opname yang belum selesai.</li><li><strong>Mutasi Besar Terbaru:</strong> pergerakan stok besar untuk pemeriksaan cepat.</li><li><strong>Transfer Lokasi/Laporan Gudang:</strong> pintasan ke proses pemindahan dan analisis lebih rinci.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Mengubah filter hanya mengubah data yang ditampilkan dan tidak mengubah saldo. Tindakan pada modul Transfer Lokasi dapat menghasilkan mutasi keluar dan masuk. Proses penerimaan, penjualan, transfer, retur, serta opname akan memperbarui angka dashboard ketika halaman dimuat kembali.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Pilih periode dan lokasi yang ingin diperiksa.</li><li>Periksa Stok Kritis, Stok Kosong, dan Dokumen Pending terlebih dahulu.</li><li>Bandingkan stok tersedia, reserved, dan rusak.</li><li>Tinjau Mutasi Besar Terbaru untuk transaksi yang tidak biasa.</li><li>Buka Laporan Gudang untuk rincian atau gunakan tindakan cepat sesuai kebutuhan.</li><li>Muat ulang halaman setelah transaksi selesai untuk melihat data terbaru.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Pastikan filter periode dan lokasi benar sebelum mengambil keputusan.</li><li>Angka kosong dapat berarti tidak ada transaksi pada scope yang dipilih, bukan selalu error.</li><li>Stok reserved dan rusak tidak boleh dianggap sebagai stok bebas digunakan.</li><li>Dashboard bukan tempat mengoreksi stok; gunakan proses resmi agar mutasi dan audit tetap tercatat.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Dashboard menunjukkan 120 unit on hand, 20 reserved, dan 5 rusak. Stok tersedia berarti 95 unit. Kepala Gudang kemudian memeriksa transfer tertunda sebelum menjanjikan stok untuk permintaan baru.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <x-metronic.permission-button permission="stock.create" :href="route('warehouse.location-transfers.index')" icon="ki-outline ki-arrow-right-left">Transfer Lokasi</x-metronic.permission-button>
@endsection

@section('content')
    <x-metronic.page-title title="Dashboard Gudang" description="DASH-02 stok, nilai, receipt/issue, PO/transfer/order pending, damaged, opname, dan workload.">
        <x-slot:actions>
            <a href="{{ route('reports.warehouse.index', request()->query()) }}" class="btn btn-light-primary">Laporan Gudang</a>
        </x-slot:actions>
    </x-metronic.page-title>

    @include('reports.partials.filter', ['filters' => $filters])

    @include('reports.partials.kpi-grid', ['items' => [
        ['label' => 'Stok Tersedia', 'value' => qty($kpis['available_quantity']), 'color' => 'primary', 'description' => 'On hand - reserved - rusak'],
        ['label' => 'Reserved', 'value' => qty($kpis['reserved_quantity']), 'color' => 'warning'],
        ['label' => 'Rusak', 'value' => qty($kpis['damaged_quantity']), 'color' => 'danger'],
        ['label' => 'Nilai Persediaan', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['stock_value']), 'color' => 'success'],
        ['label' => 'Stok Kritis', 'value' => $kpis['critical_count'], 'color' => 'danger'],
        ['label' => 'Stok Kosong', 'value' => $kpis['empty_count'], 'color' => 'danger'],
        ['label' => 'Masuk/Keluar', 'value' => $kpis['incoming_count'].' / '.$kpis['outgoing_count'], 'color' => 'info'],
        ['label' => 'Pending PO/Transfer', 'value' => $kpis['pending_po'].' / '.$kpis['pending_transfer'], 'color' => 'warning'],
    ]])

    <div class="row g-5 mb-5">
        <div class="col-lg-4">
            <x-metronic.card title="Dokumen Pending">
                <div class="d-flex justify-content-between mb-3"><span>Order B2B Pending</span><span class="fw-bold">{{ $kpis['pending_order'] }}</span></div>
                <div class="d-flex justify-content-between mb-3"><span>Receipt Posted</span><span class="fw-bold">{{ $kpis['posted_receipts'] }}</span></div>
                <div class="d-flex justify-content-between"><span>Opname Terbuka</span><span class="fw-bold">{{ $kpis['open_opname'] }}</span></div>
            </x-metronic.card>
        </div>
        <div class="col-lg-8">
            <x-metronic.card title="Definisi KPI">
                <ul class="mb-0">
                    @foreach($definitions as $definition)
                        <li>{{ $definition }}</li>
                    @endforeach
                </ul>
            </x-metronic.card>
        </div>
    </div>
    <div class="row g-5 mb-5">
        <div class="col-lg-7">
            <x-metronic.card title="Gerakan Stok" subtitle="Perbandingan masuk &amp; keluar sepanjang periode dipilih">
                <x-slot:toolbar>
                    <div class="btn-group btn-group-sm" role="group" id="warehouse-movement-period" aria-label="Periode gerakan stok">
                        <button type="button" class="btn btn-light-primary active" data-period="daily">Harian</button>
                        <button type="button" class="btn btn-light-primary" data-period="monthly">Bulanan</button>
                        <button type="button" class="btn btn-light-primary" data-period="yearly">Tahunan</button>
                    </div>
                </x-slot:toolbar>
                <div id="warehouse-daily-movement" style="min-height: 340px; height: 340px;"></div>
            </x-metronic.card>
        </div>
        <div class="col-lg-5">
            <x-metronic.card title="Distribusi Stok" subtitle="Komposisi kondisi stok saat ini">
                <div id="warehouse-stock-distribution" style="min-height: 340px; height: 340px;"></div>
            </x-metronic.card>
        </div>
    </div>

    <x-metronic.card title="Mutasi Besar Terbaru">
        <div class="text-muted mb-4">Last updated: {{ $dashboard['last_updated_at']->format('d/m/Y H:i:s') }}</div>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Waktu</th><th>Produk</th><th>Lokasi</th><th>Jenis</th><th>Perubahan</th></tr></thead>
                <tbody>
                @forelse ($dashboard['large_mutations'] as $mutation)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($mutation['occurred_at'])->format('d/m/Y H:i') }}</td>
                        <td>{{ $mutation['sku'] }} — {{ $mutation['product'] }}</td>
                        <td>{{ $mutation['location'] ?: '-' }}</td>
                        <td>{{ $mutation['mutation_type'] }}</td>
                        <td class="fw-bold">{{ qty($mutation['quantity_on_hand_change']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-metronic.empty-state title="Belum ada mutasi besar" description="Mutasi besar akan tampil setelah stok bergerak." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-metronic.card>
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const movementEl = document.getElementById('warehouse-daily-movement');
        const distributionEl = document.getElementById('warehouse-stock-distribution');

        @php($dailyMovement = $dashboard['charts']['daily_movement'] ?? [])
        @php($monthlyMovement = $dashboard['charts']['monthly_movement'] ?? [])
        @php($yearlyMovement = $dashboard['charts']['yearly_movement'] ?? [])

        if (movementEl) {
            const datasets = {
                daily: @json($dailyMovement),
                monthly: @json($monthlyMovement),
                yearly: @json($yearlyMovement),
            };

            const formatDates = (rows, period) => rows.map(row => {
                if (period === 'monthly' && row.date) {
                    const [y, m] = row.date.split('-');
                    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                    return monthNames[parseInt(m, 10) - 1] + ' ' + y;
                }
                return row.date;
            });

            const baseOptions = {
                series: [],
                chart: {
                    type: 'area',
                    height: 320,
                    toolbar: { show: false },
                    fontFamily: 'Inter, sans-serif',
                },
                colors: ['#4f46e5', '#ef4444'],
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { opacityFrom: 0.45, opacityTo: 0.05 } },
                dataLabels: { enabled: false },
                xaxis: { categories: [], labels: { style: { colors: '#94a3b8' } } },
                yaxis: { labels: { style: { colors: '#94a3b8' } } },
                grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
                legend: { position: 'top' },
                tooltip: { theme: 'light' },
            };

            const renderMovementChart = (period) => {
                const rows = datasets[period] || [];
                const dates = formatDates(rows, period);
                const incoming = rows.map(row => parseFloat(row.incoming || 0));
                const outgoing = rows.map(row => parseFloat(row.outgoing || 0));

                if (movementEl._apexChart) {
                    movementEl._apexChart.destroy();
                }

                movementEl.innerHTML = '';

                if (dates.length === 0) {
                    movementEl.innerHTML = '<div class="text-center text-muted py-10">Belum ada gerakan stok pada periode ini</div>';
                    return;
                }

                baseOptions.series = [
                    { name: 'Masuk', data: incoming },
                    { name: 'Keluar', data: outgoing },
                ];
                baseOptions.xaxis.categories = dates;

                const chart = new ApexCharts(movementEl, baseOptions);
                chart.render();
                movementEl._apexChart = chart;
            };

            renderMovementChart('daily');

            const periodButtons = document.querySelectorAll('#warehouse-movement-period button[data-period]');
            periodButtons.forEach((button) => {
                button.addEventListener('click', function () {
                    periodButtons.forEach((b) => b.classList.remove('active'));
                    this.classList.add('active');
                    renderMovementChart(this.dataset.period);
                });
            });
        }

        if (distributionEl) {
            const available = {{ (float) ($kpis['available_quantity'] ?? 0) }};
            const reserved = {{ (float) ($kpis['reserved_quantity'] ?? 0) }};
            const damaged = {{ (float) ($kpis['damaged_quantity'] ?? 0) }};

            if ((available + reserved + damaged) === 0) {
                distributionEl.innerHTML = '<div class="text-center text-muted py-10">Belum ada data stok</div>';
            } else {
                const options = {
                    series: [available, reserved, damaged],
                    chart: { type: 'donut', height: 320, fontFamily: 'Inter, sans-serif' },
                    labels: ['Tersedia', 'Reserved', 'Rusak'],
                    colors: ['#10b981', '#f59e0b', '#ef4444'],
                    legend: { position: 'bottom' },
                    dataLabels: {
                        enabled: true,
                        formatter: function (val) {
                            return val.toFixed(1) + '%';
                        },
                    },
                    plotOptions: {
                        pie: {
                            donut: {
                                labels: {
                                    show: true,
                                    total: {
                                        show: true,
                                        label: 'Total Unit',
                                        formatter: function () {
                                            return (available + reserved + damaged).toLocaleString('id-ID');
                                        },
                                    },
                                },
                            },
                        },
                    },
                };
                new ApexCharts(distributionEl, options).render();
            }
        }
    });
    </script>
    @endpush
@endsection
