@extends('layouts.metronic.app')

@php($branchLocationMap = $branches->mapWithKeys(fn ($branch) => [$branch->id => $branch->work_location_id]))

@section('title', 'Dashboard Retail - ' . config('app.name'))
@section('page_title', 'Dashboard Retail')

@section('page_guide')
    <x-metronic.page-guide id="retail-dashboard" title="Panduan Dashboard Retail / Cabang">
        <x-slot:function>
            <p>Dashboard ini menjadi pusat pemantauan penjualan, kas, stok, dan pekerjaan operasional pada satu cabang aktif.</p>
            <p>Pengguna dengan akses beberapa cabang dapat berpindah cabang tanpa memuat ulang halaman. Petugas yang hanya ditugaskan ke satu cabang otomatis memakai cabang tersebut dan tidak dapat menggantinya.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Sistem membaca role dan penugasan lokasi kerja akun.</li><li>Cabang aktif ditentukan dan divalidasi di server.</li><li>Semua KPI, grafik, stok, shift, dan transaksi dihitung hanya dari cabang aktif.</li><li>Pergantian cabang mengambil data melalui AJAX.</li><li>Tindakan lanjutan dilakukan melalui POS, shift, piutang, stok, atau laporan.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Kinerja Cabang:</strong> target dan pencapaian omzet.</li><li><strong>KPI:</strong> omzet, transaksi, stok, shift, closing, piutang, void, dan retur.</li><li><strong>Tren:</strong> grafik omzet serta volume transaksi harian, bulanan, atau tahunan.</li><li><strong>Produk:</strong> produk terlaris, belum bergerak, serta peringatan stok.</li><li><strong>Operasional:</strong> transaksi terbaru dan shift yang masih berjalan.</li><li><strong>Pembayaran:</strong> komposisi dan rincian metode pembayaran.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Filter hanya mengubah data yang dibaca dan tidak mengubah transaksi. Margin dan nilai stok hanya dikirim kepada pengguna yang memiliki izin melihat margin sensitif.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Pastikan cabang aktif sudah benar.</li><li>Pilih periode lalu klik <strong>Terapkan</strong>.</li><li>Periksa target, closing pending, selisih kas, dan stok kritis.</li><li>Tinjau produk terlaris serta produk belum bergerak.</li><li>Gunakan aktivitas terbaru dan tombol tindakan untuk membuka proses terkait.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Server menolak cabang di luar cakupan penugasan akun.</li><li>Void dan retur tidak boleh dinilai sebagai penjualan normal.</li><li>Selisih kas harus diperiksa melalui proses closing, bukan diubah dari dashboard.</li><li>Stok kritis perlu ditindaklanjuti melalui restock atau transfer resmi.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Kepala Toko A hanya melihat Cabang A. Owner memilih Cabang B; omzet, grafik, stok, shift, dan aktivitas langsung berubah ke data Cabang B tanpa refresh halaman.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <x-metronic.permission-button permission="pos.view" :href="route('retail.pos.index')" icon="ki-outline ki-handcart">Buka POS</x-metronic.permission-button>
@endsection

@section('content')
    <x-metronic.page-title title="Dashboard Retail / Cabang" description="Ringkasan penjualan dan operasional berdasarkan cabang aktif.">
        <x-slot:actions>
            <x-metronic.permission-button permission="cash_shifts.view" :href="route('retail.shifts.current')" variant="light-success" icon="ki-outline ki-time">Shift Saat Ini</x-metronic.permission-button>
            <a id="retail-report-link" href="{{ route('reports.retail.index', ['work_location_id' => $activeBranch?->work_location_id, 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date']]) }}" class="btn btn-light-primary"><i class="ki-outline ki-chart-simple fs-5"></i> Laporan Toko</a>
        </x-slot:actions>
    </x-metronic.page-title>

    <x-metronic.card class="mb-5">
        <form id="retail-dashboard-filter" method="GET" action="{{ route('retail.dashboard') }}">
            <input id="retail-dashboard-range" type="hidden" name="range" value="{{ $filters['range'] ?? 'daily' }}">
            <div class="row g-4 align-items-end">
                <div class="col-xl-5 col-lg-6">
                    <label class="form-label fw-semibold">Cabang Aktif</label>
                    @if ($canSelectBranch)
                        <select id="retail-dashboard-selector" name="branch_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Cabang" data-hide-search="false">
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected($activeBranch?->is($branch))>{{ $branch->code }} — {{ $branch->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Pilihan dibatasi sesuai hak akses akun Anda.</div>
                    @else
                        <div class="d-flex align-items-center rounded bg-light-primary px-4 py-3 min-h-45px">
                            <i class="ki-outline ki-shop fs-2 text-primary me-3"></i>
                            <div><div class="fw-bold text-gray-800">{{ $activeBranch ? $activeBranch->code.' — '.$activeBranch->name : 'Belum ada cabang' }}</div><div class="text-muted fs-8">Konteks otomatis dari penugasan lokasi kerja</div></div>
                        </div>
                        @if ($activeBranch)<input type="hidden" name="branch_id" value="{{ $activeBranch->id }}">@endif
                    @endif
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="retail-start-date" class="form-label fw-semibold">Tanggal Mulai</label>
                    <input id="retail-start-date" type="date" name="start_date" value="{{ $filters['start_date'] }}" class="form-control form-control-solid">
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="retail-end-date" class="form-label fw-semibold">Tanggal Selesai</label>
                    <input id="retail-end-date" type="date" name="end_date" value="{{ $filters['end_date'] }}" class="form-control form-control-solid">
                </div>
                <div class="col-xl-3 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="ki-outline ki-filter fs-5"></i> Terapkan</button>
                    <button type="button" id="retail-dashboard-refresh" class="btn btn-icon btn-light-primary" title="Muat ulang data" aria-label="Muat ulang data"><i class="ki-outline ki-arrows-circle fs-4"></i></button>
                </div>
            </div>
        </form>
    </x-metronic.card>

    <div id="retail-dashboard-loading" class="alert alert-primary d-none align-items-center mb-5" role="status"><span class="spinner-border spinner-border-sm me-3" aria-hidden="true"></span><span>Mengambil data cabang...</span></div>

    <div id="retail-dashboard-content">
        @include('dashboards.partials.retail-content')
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('retail-dashboard-filter');
                const selector = document.getElementById('retail-dashboard-selector');
                const rangeInput = document.getElementById('retail-dashboard-range');
                const content = document.getElementById('retail-dashboard-content');
                const loading = document.getElementById('retail-dashboard-loading');
                const refresh = document.getElementById('retail-dashboard-refresh');
                const reportLink = document.getElementById('retail-report-link');
                const dataUrl = @json(route('retail.dashboard.data'));
                const reportUrl = @json(route('reports.retail.index'));
                const branchLocations = @json($branchLocationMap);
                const fixedWorkLocationId = @json($activeBranch?->work_location_id);
                let currentCharts = @json($dashboard['charts']);
                let currentKpis = @json($dashboard['kpis']);
                let requestController = null;
                const chartInstances = { revenue: null, transactions: null, payment: null };

                const numberValue = (value) => Number.parseFloat(value || 0);
                const formatRupiah = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(numberValue(value));
                const rangeLabel = (raw, range) => {
                    if (!raw) return '';
                    if (range === 'monthly' && /^\d{4}-\d{2}$/.test(raw)) {
                        const [year, month] = raw.split('-');
                        return `${['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][Number.parseInt(month, 10) - 1]} ${year}`;
                    }
                    return raw;
                };

                const destroyCharts = () => {
                    Object.keys(chartInstances).forEach((key) => {
                        if (chartInstances[key] && typeof chartInstances[key].destroy === 'function') chartInstances[key].destroy();
                        chartInstances[key] = null;
                    });
                };

                const renderCharts = (charts, range) => {
                    destroyCharts();
                    const revenueEl = document.getElementById('retail-revenue-chart');
                    const transactionsEl = document.getElementById('retail-transactions-chart');
                    const paymentEl = document.getElementById('retail-payment-chart');

                    if (typeof window.ApexCharts === 'undefined') {
                        [revenueEl, transactionsEl, paymentEl].forEach((element) => {
                            if (element) element.innerHTML = '<div class="text-center text-muted py-10">Grafik belum dapat dimuat.</div>';
                        });
                        return;
                    }

                    const revenueRows = charts[`${range}_revenue`] || [];
                    if (revenueEl) {
                        if (revenueRows.length === 0) {
                            revenueEl.innerHTML = '<div class="text-center text-muted py-10">Belum ada omzet pada periode ini.</div>';
                        } else {
                            chartInstances.revenue = new ApexCharts(revenueEl, {
                                series: [{ name: 'Omzet', data: revenueRows.map((row) => numberValue(row.retail)) }],
                                chart: { type: 'area', height: 330, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
                                colors: ['#1b84ff'], stroke: { curve: 'smooth', width: 3 }, dataLabels: { enabled: false },
                                fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.04 } },
                                xaxis: { categories: revenueRows.map((row) => rangeLabel(row.date, range)) },
                                yaxis: { labels: { formatter: formatRupiah } }, grid: { borderColor: '#e4e6ef', strokeDashArray: 4 },
                                tooltip: { y: { formatter: formatRupiah } },
                            });
                            chartInstances.revenue.render();
                        }
                    }

                    const transactionRows = charts[`${range}_transactions`] || [];
                    if (transactionsEl) {
                        if (transactionRows.length === 0) {
                            transactionsEl.innerHTML = '<div class="text-center text-muted py-10">Belum ada transaksi pada periode ini.</div>';
                        } else {
                            chartInstances.transactions = new ApexCharts(transactionsEl, {
                                series: [{ name: 'Transaksi', data: transactionRows.map((row) => Number(row.count || 0)) }],
                                chart: { type: 'bar', height: 305, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
                                colors: ['#17c653'], plotOptions: { bar: { borderRadius: 6, columnWidth: '48%' } }, dataLabels: { enabled: false },
                                xaxis: { categories: transactionRows.map((row) => rangeLabel(row.date, range)) },
                                yaxis: { labels: { formatter: (value) => Math.round(value) } }, grid: { borderColor: '#e4e6ef', strokeDashArray: 4 },
                            });
                            chartInstances.transactions.render();
                        }
                    }

                    const paymentRows = charts.payment_methods || [];
                    if (paymentEl) {
                        if (paymentRows.length === 0) {
                            paymentEl.innerHTML = '<div class="text-center text-muted py-10">Belum ada pembayaran pada periode ini.</div>';
                        } else {
                            chartInstances.payment = new ApexCharts(paymentEl, {
                                series: paymentRows.map((row) => numberValue(row.value)), labels: paymentRows.map((row) => String(row.label).replaceAll('_', ' ')),
                                chart: { type: 'donut', height: 330, fontFamily: 'Inter, sans-serif' }, colors: ['#1b84ff', '#17c653', '#f6c000', '#f8285a', '#7239ea', '#43ced7'],
                                legend: { position: 'bottom' }, dataLabels: { enabled: true, formatter: (value) => `${value.toFixed(1)}%` },
                                tooltip: { y: { formatter: formatRupiah } }, plotOptions: { pie: { donut: { size: '66%' } } },
                            });
                            chartInstances.payment.render();
                        }
                    }

                };

                const paramsFromForm = () => new URLSearchParams(new FormData(form));
                const updateLinks = (params) => {
                    const branchId = params.get('branch_id');
                    const workLocationId = branchId ? branchLocations[branchId] : fixedWorkLocationId;
                    const reportParams = new URLSearchParams({ start_date: params.get('start_date') || '', end_date: params.get('end_date') || '' });
                    if (workLocationId) reportParams.set('work_location_id', workLocationId);
                    reportLink.href = `${reportUrl}?${reportParams.toString()}`;
                    window.history.replaceState({}, '', `${form.action}?${params.toString()}`);
                };

                const loadDashboard = async () => {
                    const params = paramsFromForm();
                    if (requestController) requestController.abort();
                    const controller = new AbortController();
                    requestController = controller;
                    loading.classList.remove('d-none');
                    loading.classList.add('d-flex');
                    content.classList.add('opacity-50', 'pe-none');
                    if (selector) selector.disabled = true;
                    refresh.disabled = true;

                    try {
                        const response = await fetch(`${dataUrl}?${params.toString()}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal });
                        if (!response.ok) throw new Error(response.status === 403 ? 'Anda tidak memiliki akses ke cabang tersebut.' : 'Data dashboard gagal dimuat.');
                        const payload = await response.json();
                        destroyCharts();
                        content.innerHTML = payload.html;
                        currentCharts = payload.charts;
                        currentKpis = payload.kpis;
                        renderCharts(currentCharts, rangeInput.value || 'daily');
                        updateLinks(params);
                    } catch (error) {
                        if (error.name !== 'AbortError') content.insertAdjacentHTML('afterbegin', `<div class="alert alert-danger">${error.message}</div>`);
                    } finally {
                        if (requestController === controller) {
                            loading.classList.add('d-none');
                            loading.classList.remove('d-flex');
                            content.classList.remove('opacity-50', 'pe-none');
                            if (selector) selector.disabled = false;
                            refresh.disabled = false;
                        }
                    }
                };

                form.addEventListener('submit', function (event) { event.preventDefault(); loadDashboard(); });
                refresh.addEventListener('click', loadDashboard);
                content.addEventListener('click', function (event) {
                    const button = event.target.closest('#retail-range-buttons [data-range]');
                    if (!button) return;
                    document.querySelectorAll('#retail-range-buttons [data-range]').forEach((item) => item.classList.remove('active'));
                    button.classList.add('active');
                    rangeInput.value = button.dataset.range;
                    renderCharts(currentCharts, button.dataset.range);
                });
                if (selector) {
                    if (window.jQuery) window.jQuery(selector).off('change.retailDashboard').on('change.retailDashboard', loadDashboard);
                    else selector.addEventListener('change', loadDashboard);
                }
                renderCharts(currentCharts, rangeInput.value || 'daily');
            });
        </script>
    @endpush
@endsection
