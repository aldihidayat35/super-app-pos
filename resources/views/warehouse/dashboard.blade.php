@extends('layouts.metronic.app')

@section('title', 'Dashboard Gudang - ' . config('app.name'))
@section('page_title', 'Dashboard Gudang')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-dashboard" title="Panduan Halaman Dashboard Gudang">
        <x-slot:function>
            <p>Dashboard ini merangkum stok dan pekerjaan operasional pada satu gudang aktif. Semua angka, grafik, aktivitas, dan peringatan mengikuti gudang yang terlihat pada bagian konteks gudang.</p>
            <p>Pengguna dengan akses beberapa gudang dapat berpindah gudang tanpa memuat ulang halaman. Petugas yang hanya ditugaskan ke satu gudang akan langsung menggunakan gudang tersebut dan tidak dapat menggantinya.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Sistem membaca role serta penugasan lokasi kerja akun Anda.</li><li>Sistem memilih gudang pertama yang dapat diakses, atau gudang yang dipilih melalui filter.</li><li>Server memvalidasi bahwa gudang tersebut benar-benar termasuk cakupan akses akun.</li><li>Seluruh KPI dan widget dihitung hanya dari gudang aktif.</li><li>Perubahan pilihan gudang mengambil data terbaru melalui AJAX.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Konteks Gudang:</strong> menunjukkan gudang yang menjadi sumber seluruh data.</li><li><strong>Periode:</strong> membatasi aktivitas, receipt, dan mutasi yang dihitung.</li><li><strong>Statistik Stok:</strong> memperlihatkan stok fisik, tersedia, dipesan, rusak, dan nilai persediaan.</li><li><strong>Peringatan Stok:</strong> produk yang sudah kosong atau mencapai batas minimum.</li><li><strong>Dokumen Operasional:</strong> pekerjaan PO, transfer, B2B, receipt, dan opname.</li><li><strong>Grafik:</strong> pergerakan dan komposisi stok gudang aktif.</li><li><strong>Mutasi Besar:</strong> pergerakan stok signifikan untuk pemeriksaan cepat.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Memilih gudang atau periode hanya mengubah data yang dibaca; tindakan ini tidak mengubah saldo stok. Perubahan saldo tetap wajib dilakukan melalui transaksi resmi agar mutasi dan audit tercatat.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Pastikan nama gudang aktif sudah benar.</li><li>Jika tersedia, gunakan <strong>Pilih Gudang</strong> untuk berpindah konteks.</li><li>Tentukan periode lalu klik <strong>Terapkan</strong>.</li><li>Periksa stok kritis/kosong dan dokumen pending.</li><li>Tinjau grafik serta mutasi besar sebelum membuka modul tindak lanjut.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Data gudang yang tidak termasuk cakupan akun akan ditolak oleh server.</li><li>Stok dipesan dan rusak bukan stok bebas digunakan.</li><li>Dashboard bukan tempat melakukan koreksi stok.</li><li>Order B2B dihitung pada gudang ketika reservasinya sudah terbentuk.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Staff Gudang A hanya melihat Dashboard Gudang A tanpa dropdown. Owner memilih Gudang B pada dropdown; statistik, grafik, peringatan, dan tabel mutasi langsung berubah ke data Gudang B.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <x-metronic.permission-button permission="stock.create" :href="route('warehouse.location-transfers.index')" icon="ki-outline ki-arrow-right-left">Transfer Lokasi</x-metronic.permission-button>
@endsection

@section('content')
    <x-metronic.page-title title="Dashboard Gudang" description="Pantau stok dan pekerjaan operasional berdasarkan gudang aktif.">
        <x-slot:actions>
            <a id="warehouse-report-link"
               href="{{ route('reports.warehouse.index', ['work_location_id' => $activeWarehouse?->work_location_id, 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date']]) }}"
               class="btn btn-light-primary">
                <i class="ki-outline ki-chart-simple fs-5"></i> Laporan Gudang
            </a>
        </x-slot:actions>
    </x-metronic.page-title>

    <x-metronic.card class="mb-5">
        <form id="warehouse-dashboard-filter" method="GET" action="{{ route('warehouse.dashboard') }}">
            <div class="row g-4 align-items-end">
                <div class="{{ $canSelectWarehouse ? 'col-xl-5 col-lg-6' : 'col-xl-5 col-lg-6' }}">
                    <label class="form-label fw-semibold">Gudang Aktif</label>
                    @if ($canSelectWarehouse)
                        <select id="warehouse-dashboard-selector" name="warehouse_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Gudang" data-hide-search="false">
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected($activeWarehouse?->is($warehouse))>
                                    {{ $warehouse->code }} — {{ $warehouse->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Pilihan dibatasi sesuai hak akses akun Anda.</div>
                    @else
                        <div class="d-flex align-items-center rounded bg-light-primary px-4 py-3 min-h-45px">
                            <i class="ki-outline ki-geolocation fs-2 text-primary me-3"></i>
                            <div>
                                <div id="warehouse-active-name" class="fw-bold text-gray-800">{{ $activeWarehouse ? $activeWarehouse->code.' — '.$activeWarehouse->name : 'Belum ada gudang' }}</div>
                                <div class="text-muted fs-8">Konteks otomatis dari penugasan lokasi kerja</div>
                            </div>
                        </div>
                        @if($activeWarehouse)<input type="hidden" name="warehouse_id" value="{{ $activeWarehouse->id }}">@endif
                    @endif
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="warehouse-start-date" class="form-label fw-semibold">Tanggal Mulai</label>
                    <input id="warehouse-start-date" type="date" name="start_date" value="{{ $filters['start_date'] }}" class="form-control form-control-solid">
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="warehouse-end-date" class="form-label fw-semibold">Tanggal Selesai</label>
                    <input id="warehouse-end-date" type="date" name="end_date" value="{{ $filters['end_date'] }}" class="form-control form-control-solid">
                </div>
                <div class="col-xl-3 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="ki-outline ki-filter fs-5"></i> Terapkan
                    </button>
                    <button type="button" id="warehouse-dashboard-refresh" class="btn btn-icon btn-light-primary" title="Muat ulang data" aria-label="Muat ulang data">
                        <i class="ki-outline ki-arrows-circle fs-4"></i>
                    </button>
                </div>
            </div>
        </form>
    </x-metronic.card>

    <div id="warehouse-dashboard-loading" class="alert alert-primary d-none align-items-center mb-5" role="status">
        <span class="spinner-border spinner-border-sm me-3" aria-hidden="true"></span>
        <span>Mengambil data gudang...</span>
    </div>

    <div id="warehouse-dashboard-content">
        @include('warehouse.partials.dashboard-content')
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('warehouse-dashboard-filter');
                const selector = document.getElementById('warehouse-dashboard-selector');
                const content = document.getElementById('warehouse-dashboard-content');
                const loading = document.getElementById('warehouse-dashboard-loading');
                const refresh = document.getElementById('warehouse-dashboard-refresh');
                const reportLink = document.getElementById('warehouse-report-link');
                const dataUrl = @json(route('warehouse.dashboard.data'));
                const reportUrl = @json(route('reports.warehouse.index'));
                let currentCharts = @json($dashboard['charts']);
                let currentKpis = @json($dashboard['kpis']);
                let requestController = null;

                const numberValue = (value) => Number.parseFloat(value || 0);
                const chartInstances = { movement: null, distribution: null };

                const destroyCharts = () => {
                    Object.keys(chartInstances).forEach((key) => {
                        if (chartInstances[key] && typeof chartInstances[key].destroy === 'function') {
                            chartInstances[key].destroy();
                        }
                        chartInstances[key] = null;
                    });
                };

                const formatDates = (rows, period) => rows.map((row) => {
                    if (period === 'monthly' && row.date) {
                        const [year, month] = row.date.split('-');
                        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                        return `${months[Number.parseInt(month, 10) - 1]} ${year}`;
                    }
                    return row.date;
                });

                const renderCharts = (charts, kpis) => {
                    destroyCharts();
                    const movementEl = document.getElementById('warehouse-daily-movement');
                    const distributionEl = document.getElementById('warehouse-stock-distribution');

                    if (typeof window.ApexCharts === 'undefined') {
                        [movementEl, distributionEl].forEach((element) => {
                            if (element) element.innerHTML = '<div class="text-center text-muted py-10">Grafik belum dapat dimuat.</div>';
                        });
                        return;
                    }

                    const renderMovement = (period) => {
                        if (!movementEl) return;
                        if (chartInstances.movement) chartInstances.movement.destroy();
                        chartInstances.movement = null;
                        movementEl.innerHTML = '';

                        const rows = charts[`${period}_movement`] || [];
                        if (rows.length === 0) {
                            movementEl.innerHTML = '<div class="text-center text-muted py-10">Belum ada pergerakan stok pada periode ini.</div>';
                            return;
                        }

                        chartInstances.movement = new ApexCharts(movementEl, {
                            series: [
                                { name: 'Masuk', data: rows.map((row) => numberValue(row.incoming)) },
                                { name: 'Keluar', data: rows.map((row) => numberValue(row.outgoing)) },
                            ],
                            chart: { type: 'area', height: 320, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
                            colors: ['#17c653', '#f8285a'],
                            stroke: { curve: 'smooth', width: 2 },
                            fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
                            dataLabels: { enabled: false },
                            xaxis: { categories: formatDates(rows, period) },
                            grid: { borderColor: '#e4e6ef', strokeDashArray: 4 },
                            legend: { position: 'top' },
                        });
                        chartInstances.movement.render();
                    };

                    renderMovement('daily');
                    document.querySelectorAll('#warehouse-movement-period [data-period]').forEach((button) => {
                        button.addEventListener('click', function () {
                            document.querySelectorAll('#warehouse-movement-period [data-period]').forEach((item) => item.classList.remove('active'));
                            this.classList.add('active');
                            renderMovement(this.dataset.period);
                        });
                    });

                    if (!distributionEl) return;
                    const values = [numberValue(kpis.available_quantity), numberValue(kpis.reserved_quantity), numberValue(kpis.damaged_quantity)];
                    if (values.reduce((total, value) => total + value, 0) <= 0) {
                        distributionEl.innerHTML = '<div class="text-center text-muted py-10">Belum ada data stok.</div>';
                        return;
                    }

                    chartInstances.distribution = new ApexCharts(distributionEl, {
                        series: values,
                        chart: { type: 'donut', height: 320, fontFamily: 'Inter, sans-serif' },
                        labels: ['Tersedia', 'Dipesan', 'Rusak'],
                        colors: ['#17c653', '#f6c000', '#f8285a'],
                        legend: { position: 'bottom' },
                        dataLabels: { enabled: true, formatter: (value) => `${value.toFixed(1)}%` },
                        plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Total Unit' } } } } },
                    });
                    chartInstances.distribution.render();
                };

                const paramsFromForm = () => new URLSearchParams(new FormData(form));

                const updateLinks = (params) => {
                    const warehouseId = params.get('warehouse_id');
                    const selected = selector?.options[selector.selectedIndex];
                    const workLocationId = selected?.dataset.workLocationId;
                    const reportParams = new URLSearchParams({
                        start_date: params.get('start_date') || '',
                        end_date: params.get('end_date') || '',
                    });
                    if (workLocationId) reportParams.set('work_location_id', workLocationId);
                    reportLink.href = `${reportUrl}?${reportParams.toString()}`;

                    const pageParams = new URLSearchParams(params);
                    if (!warehouseId) pageParams.delete('warehouse_id');
                    window.history.replaceState({}, '', `${form.action}?${pageParams.toString()}`);
                };

                const loadDashboard = async () => {
                    const params = paramsFromForm();
                    if (requestController) requestController.abort();
                    requestController = new AbortController();

                    loading.classList.remove('d-none');
                    loading.classList.add('d-flex');
                    content.classList.add('opacity-50', 'pe-none');
                    if (selector) selector.disabled = true;
                    refresh.disabled = true;

                    try {
                        const response = await fetch(`${dataUrl}?${params.toString()}`, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            signal: requestController.signal,
                        });
                        if (!response.ok) throw new Error(response.status === 403 ? 'Anda tidak memiliki akses ke gudang tersebut.' : 'Data dashboard gagal dimuat.');

                        const payload = await response.json();
                        destroyCharts();
                        content.innerHTML = payload.html;
                        currentCharts = payload.charts;
                        currentKpis = payload.kpis;
                        renderCharts(currentCharts, currentKpis);
                        updateLinks(params);
                    } catch (error) {
                        if (error.name !== 'AbortError') {
                            content.insertAdjacentHTML('afterbegin', `<div class="alert alert-danger">${error.message}</div>`);
                        }
                    } finally {
                        loading.classList.add('d-none');
                        loading.classList.remove('d-flex');
                        content.classList.remove('opacity-50', 'pe-none');
                        if (selector) selector.disabled = false;
                        refresh.disabled = false;
                    }
                };

                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    loadDashboard();
                });
                refresh.addEventListener('click', loadDashboard);

                if (selector) {
                    Array.from(selector.options).forEach((option) => {
                        const warehouse = @json($warehouses->mapWithKeys(fn ($warehouse) => [$warehouse->id => $warehouse->work_location_id]));
                        option.dataset.workLocationId = warehouse[option.value] || '';
                    });
                    if (window.jQuery) {
                        window.jQuery(selector).off('change.warehouseDashboard').on('change.warehouseDashboard', loadDashboard);
                    } else {
                        selector.addEventListener('change', loadDashboard);
                    }
                }

                renderCharts(currentCharts, currentKpis);
            });
        </script>
    @endpush
@endsection
