@extends('layouts.metronic.app')

@section('title', 'Dashboard Piutang')
@section('page_title', 'Dashboard Piutang')

@section('page_guide')
    <x-metronic.page-guide id="receivables-dashboard" title="Panduan Dashboard Piutang">
        <x-slot:function>Memantau kesehatan piutang, kecepatan penagihan, pelanggan berisiko, dan pekerjaan koleksi yang harus diprioritaskan.</x-slot:function>
        <x-slot:workflow><ol><li>Pilih kanal dan periode analisis.</li><li>Periksa rasio overdue serta pembayaran periode berjalan.</li><li>Tinjau aging dan pelanggan berisiko.</li><li>Lanjutkan ke dokumen prioritas atau jadwal follow-up untuk mengambil tindakan.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>KPI:</strong> posisi saldo dan efektivitas pembayaran.</li><li><strong>Arus piutang:</strong> perbandingan tagihan baru dengan pembayaran.</li><li><strong>Aging:</strong> umur saldo outstanding.</li><li><strong>Prioritas:</strong> dokumen tertua, pelanggan berisiko, dan follow-up jatuh jadwal.</li></ul></x-slot:parts>
        <x-slot:warnings><div class="alert alert-warning mb-0">Rasio pembayaran membandingkan pembayaran dan tagihan baru pada periode terpilih. Nilai di atas 100% dapat terjadi ketika pembayaran menyelesaikan saldo dari periode sebelumnya.</div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    @php
        use App\Support\CurrencyFormatter;

        $kpis = [
            ['Total Outstanding', CurrencyFormatter::rupiah($summary['total']), 'ki-wallet', 'primary', $summary['open_documents'].' dokumen dari '.$summary['open_customers'].' pelanggan'],
            ['Sudah Overdue', CurrencyFormatter::rupiah($summary['overdue']), 'ki-information-5', 'danger', $summary['overdue_percentage'].'% dari outstanding'],
            ['Jatuh Tempo 7 Hari', CurrencyFormatter::rupiah($summary['due_next_7_days']), 'ki-calendar', 'warning', CurrencyFormatter::rupiah($summary['due_today']).' jatuh tempo hari ini'],
            ['Pembayaran Hari Ini', CurrencyFormatter::rupiah($summary['paid_today']), 'ki-check-circle', 'success', CurrencyFormatter::rupiah($summary['period_paid']).' dalam periode'],
            ['Rasio Pembayaran', $summary['collection_ratio'].'%', 'ki-chart-line-up', 'info', 'Dibanding '.CurrencyFormatter::rupiah($summary['period_invoiced']).' tagihan baru'],
            ['Kontrol Kredit', $summary['over_limit_customers'].' pelanggan', 'ki-shield-cross', $summary['over_limit_customers'] > 0 ? 'danger' : 'success', $summary['follow_ups_due'].' follow-up perlu ditangani'],
        ];
    @endphp

    <x-metronic.page-title title="Dashboard Piutang" description="Kesehatan piutang, efektivitas pembayaran, dan prioritas penagihan dalam satu tampilan.">
        <x-slot:actions>
            <a href="{{ route('receivables.reminders') }}" class="btn btn-light-warning"><i class="ki-outline ki-notification-on fs-5"></i> Penagihan</a>
            <a href="{{ route('receivables.index', array_filter(['channel' => $filters['channel']])) }}" class="btn btn-light-primary"><i class="ki-outline ki-document fs-5"></i> Semua Piutang</a>
            <a href="{{ route('receivables.payments.create') }}" class="btn btn-primary"><i class="ki-outline ki-dollar fs-5"></i> Input Pembayaran</a>
        </x-slot:actions>
    </x-metronic.page-title>

    <x-metronic.card class="mb-5 receivable-filter-card">
        <form method="GET" class="row g-4 align-items-end">
            <div class="col-lg-4 col-md-5"><label class="form-label fw-semibold">Kanal Piutang</label><select name="channel" class="form-select form-select-solid"><option value="">Semua kanal</option><option value="warehouse" @selected($filters['channel'] === 'warehouse')>Gudang / B2B</option><option value="retail" @selected($filters['channel'] === 'retail')>Toko Internal</option></select></div>
            <div class="col-lg-4 col-md-4"><label class="form-label fw-semibold">Periode Arus Piutang</label><select name="range" class="form-select form-select-solid">@foreach ([7 => '7 hari terakhir', 30 => '30 hari terakhir', 90 => '90 hari terakhir'] as $days => $label)<option value="{{ $days }}" @selected($filters['range'] === $days)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-lg-2 col-md-3"><button class="btn btn-primary w-100"><i class="ki-outline ki-filter fs-5"></i> Terapkan</button></div>
            <div class="col-lg-2 text-lg-end text-muted fs-8"><div>Data diperbarui</div><div class="fw-semibold text-gray-700">{{ $refreshedAt->format('d/m/Y H:i') }}</div></div>
        </form>
    </x-metronic.card>

    @if (App\Support\Decimal::isPositive($summary['overdue'], 2))
        <div class="alert alert-light-danger d-flex align-items-center mb-5">
            <i class="ki-outline ki-information-5 fs-2x text-danger me-4"></i>
            <div class="flex-grow-1"><div class="fw-bold text-gray-900">{{ $summary['overdue_percentage'] }}% saldo piutang telah melewati jatuh tempo</div><div class="text-muted">Fokuskan koleksi pada dokumen paling tua dan pelanggan dengan total overdue terbesar di bagian prioritas.</div></div>
            <a href="{{ route('receivables.index', array_filter(['status' => 'overdue', 'channel' => $filters['channel']])) }}" class="btn btn-sm btn-danger">Tinjau Overdue</a>
        </div>
    @endif

    <div class="row g-5 mb-5">
        @foreach ($kpis as [$label, $value, $icon, $tone, $help])
            <div class="col-xxl-2 col-xl-4 col-md-6"><div class="card receivable-kpi-card h-100"><div class="card-body p-5"><div class="d-flex align-items-center justify-content-between mb-4"><span class="receivable-kpi-icon bg-light-{{ $tone }} text-{{ $tone }}"><i class="ki-outline {{ $icon }} fs-2"></i></span><span class="text-muted text-uppercase fs-8 fw-bold">{{ $label }}</span></div><div class="fs-3 fw-bolder text-gray-900">{{ $value }}</div><div class="text-muted fs-8 mt-2">{{ $help }}</div></div></div></div>
        @endforeach
    </div>

    <div class="row g-5 mb-5">
        <div class="col-xl-8">
            <x-metronic.card title="Arus Tagihan dan Pembayaran" class="h-100">
                <div class="d-flex flex-wrap justify-content-between gap-3 mb-3"><div class="text-muted fs-7">Perbandingan tagihan baru dan pembayaran selama {{ $filters['range'] }} hari terakhir</div><div class="d-flex gap-4 fs-8"><span><i class="bullet bullet-dot bg-primary me-2"></i>Tagihan baru</span><span><i class="bullet bullet-dot bg-success me-2"></i>Pembayaran</span></div></div>
                <div id="receivable-flow-chart" class="receivable-chart"></div>
            </x-metronic.card>
        </div>
        <div class="col-xl-4">
            <x-metronic.card title="Komposisi Aging" class="h-100">
                <div class="text-muted fs-7 mb-4">Distribusi saldo berdasarkan umur keterlambatan</div>
                @foreach ($agingRows as $row)
                    <a href="{{ route('receivables.index', array_filter(['aging' => $row['bucket'], 'channel' => $filters['channel']])) }}" class="d-block text-gray-800 mb-4">
                        <div class="d-flex justify-content-between align-items-start mb-2"><div><div class="fw-semibold">{{ $row['label'] }}</div><div class="text-muted fs-8">{{ $row['document_count'] }} dokumen</div></div><div class="text-end"><div class="fw-bold">{{ CurrencyFormatter::rupiah($row['amount']) }}</div><div class="text-muted fs-8">{{ $row['percentage'] }}%</div></div></div>
                        <div class="progress h-6px"><div class="progress-bar bg-{{ $row['tone'] }}" style="width: {{ $row['percentage'] }}%" role="progressbar" aria-label="{{ $row['label'] }}" aria-valuenow="{{ $row['percentage'] }}" aria-valuemin="0" aria-valuemax="100"></div></div>
                    </a>
                @endforeach
            </x-metronic.card>
        </div>
    </div>

    <div class="row g-5 mb-5">
        <div class="col-xl-8">
            <x-metronic.card title="Dokumen Prioritas Ditagih" class="h-100">
                <div class="text-muted fs-7 mb-3">Diurutkan dari jatuh tempo paling lama, termasuk yang jatuh tempo hari ini</div>
                <div class="table-responsive"><table class="table table-row-dashed align-middle mb-0"><thead><tr class="text-muted text-uppercase fs-8 fw-bold"><th>Dokumen</th><th>Pelanggan</th><th>Jatuh Tempo</th><th class="text-end">Outstanding</th><th class="text-end">Aksi</th></tr></thead><tbody>
                    @forelse ($priorityReceivables as $receivable)
                        @php($daysLate = max(0, (int) $receivable->due_date->diffInDays($refreshedAt->copy()->startOfDay())))
                        <tr><td><div class="fw-semibold text-gray-900">{{ $receivable->number }}</div><div class="text-muted fs-8">{{ $receivable->source_no ?: ucfirst($receivable->channel) }}</div></td><td><a href="{{ route('receivables.customers.show', $receivable->customer) }}" class="fw-semibold">{{ $receivable->customer?->business_name }}</a></td><td><div>{{ $receivable->due_date?->format('d/m/Y') }}</div><span @class(['badge mt-1', 'badge-light-danger' => $daysLate > 0, 'badge-light-warning' => $daysLate === 0])>{{ $daysLate > 0 ? $daysLate.' hari terlambat' : 'Jatuh tempo hari ini' }}</span></td><td class="text-end fw-bold text-danger">{{ CurrencyFormatter::rupiah($receivable->outstanding_amount) }}</td><td class="text-end"><a href="{{ route('receivables.payments.create', ['customer_id' => $receivable->customer_id]) }}" class="btn btn-sm btn-light-success">Bayar</a></td></tr>
                    @empty
                        <tr><td colspan="5"><x-metronic.empty-state title="Tidak ada piutang prioritas" description="Belum ada saldo yang jatuh tempo pada kanal ini." /></td></tr>
                    @endforelse
                </tbody></table></div>
            </x-metronic.card>
        </div>
        <div class="col-xl-4">
            <x-metronic.card title="Kontrol Kanal" class="h-100">
                <div class="text-muted fs-7 mb-4">Kontribusi saldo outstanding per kanal</div>
                @foreach ($channelRows as $row)
                    <div class="border border-gray-300 rounded p-4 mb-4"><div class="d-flex align-items-center justify-content-between mb-3"><span class="badge badge-light-{{ $row['tone'] }}">{{ $row['label'] }}</span><span class="fw-bold">{{ $row['percentage'] }}%</span></div><div class="fs-4 fw-bolder text-gray-900">{{ CurrencyFormatter::rupiah($row['amount']) }}</div><div class="text-muted fs-8 mt-1">{{ $row['document_count'] }} dokumen terbuka</div></div>
                @endforeach
                <div class="d-flex justify-content-between border-top py-3"><span class="text-muted">Belum jatuh tempo</span><span class="fw-bold text-success">{{ CurrencyFormatter::rupiah($summary['not_due']) }}</span></div>
                <div class="d-flex justify-content-between border-top py-3"><span class="text-muted">Follow-up terlewat</span><span @class(['badge', 'badge-light-danger' => $summary['follow_ups_due'] > 0, 'badge-light-success' => $summary['follow_ups_due'] === 0])>{{ $summary['follow_ups_due'] }}</span></div>
                <a href="{{ route('receivables.credit-limits') }}" class="btn btn-light-primary w-100 mt-2">Kelola Limit Kredit</a>
            </x-metronic.card>
        </div>
    </div>

    <div class="row g-5">
        <div class="col-xl-7">
            <x-metronic.card title="Pelanggan dengan Risiko Tertinggi" class="h-100">
                <div class="text-muted fs-7 mb-3">Peringkat berdasarkan total saldo overdue</div>
                <div class="table-responsive"><table class="table table-row-dashed align-middle mb-0"><thead><tr class="text-muted text-uppercase fs-8 fw-bold"><th>Pelanggan</th><th>Dokumen</th><th>Terlama</th><th class="text-end">Total Overdue</th></tr></thead><tbody>
                    @forelse ($riskCustomers as $risk)
                        @php($oldestDays = max(0, (int) \Illuminate\Support\Carbon::parse($risk->oldest_due_date)->diffInDays($refreshedAt->copy()->startOfDay())))
                        <tr><td><a href="{{ route('receivables.customers.show', $risk->customer) }}" class="fw-semibold text-gray-900">{{ $risk->customer?->business_name }}</a><div class="text-muted fs-8">{{ $risk->customer?->code }}</div></td><td><span class="badge badge-light-danger">{{ $risk->document_count }} overdue</span></td><td>{{ $oldestDays }} hari</td><td class="text-end fw-bolder text-danger">{{ CurrencyFormatter::rupiah($risk->overdue_total) }}</td></tr>
                    @empty
                        <tr><td colspan="4"><x-metronic.empty-state title="Tidak ada pelanggan berisiko" description="Seluruh saldo masih dalam jatuh tempo atau sudah lunas." /></td></tr>
                    @endforelse
                </tbody></table></div>
            </x-metronic.card>
        </div>
        <div class="col-xl-5">
            <x-metronic.card title="Follow-up Perlu Ditangani" class="h-100">
                <div class="text-muted fs-7 mb-3">Jadwal hari ini dan jadwal sebelumnya yang belum diperbarui</div>
                @forelse ($followUps as $note)
                    <div class="d-flex align-items-start border-bottom py-4"><span class="symbol symbol-40px me-4"><span class="symbol-label bg-light-warning"><i class="ki-outline ki-notification-on fs-2 text-warning"></i></span></span><div class="flex-grow-1 min-w-0"><div class="d-flex justify-content-between gap-3"><a href="{{ route('receivables.customers.show', $note->customer) }}" class="fw-semibold text-gray-900 text-truncate">{{ $note->customer?->business_name }}</a><span class="badge badge-light-danger flex-shrink-0">{{ $note->next_follow_up_date?->format('d/m') }}</span></div><div class="text-muted fs-8 text-truncate mt-1">{{ $note->contact_person ?: 'Kontak belum ditentukan' }} · {{ $note->note }}</div></div></div>
                @empty
                    <x-metronic.empty-state title="Tidak ada follow-up terlewat" description="Jadwal penagihan yang jatuh tempo akan muncul di sini." />
                @endforelse
                <a href="{{ route('receivables.reminders') }}" class="btn btn-light-warning w-100 mt-5">Buka Pusat Penagihan</a>
            </x-metronic.card>
        </div>
    </div>

    @push('styles')
        <style>.receivable-filter-card,.receivable-kpi-card{border:1px solid var(--bs-gray-200);box-shadow:none}.receivable-kpi-icon{align-items:center;border-radius:.65rem;display:inline-flex;height:42px;justify-content:center;width:42px}.receivable-chart{min-height:330px}.h-6px{height:6px}.receivable-kpi-card{transition:transform .2s ease,box-shadow .2s ease}.receivable-kpi-card:hover{box-shadow:0 .5rem 1.5rem rgba(0,0,0,.06);transform:translateY(-2px)}</style>
    @endpush

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const rows = @json($flowTrend);
                const target = document.getElementById('receivable-flow-chart');
                const hasData = rows.some((row) => Number(row.invoiced) > 0 || Number(row.paid) > 0);
                if (!target) return;
                if (typeof window.ApexCharts === 'undefined') { target.innerHTML = '<div class="d-flex align-items-center justify-content-center text-muted h-300px">Grafik belum dapat dimuat.</div>'; return; }
                if (!hasData) { target.innerHTML = '<div class="d-flex align-items-center justify-content-center text-muted h-300px">Belum ada arus tagihan atau pembayaran pada periode ini.</div>'; return; }
                const rupiah = (value) => `Rp${Number(value).toLocaleString('id-ID', { maximumFractionDigits: 0 })}`;
                new ApexCharts(target, {
                    series: [{ name: 'Tagihan Baru', data: rows.map((row) => Number(row.invoiced)) }, { name: 'Pembayaran', data: rows.map((row) => Number(row.paid)) }],
                    chart: { type: 'area', height: 330, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' }, colors: ['#1b84ff', '#17c653'], stroke: { curve: 'smooth', width: 3 }, fill: { type: 'gradient', gradient: { opacityFrom: .28, opacityTo: .03 } }, dataLabels: { enabled: false },
                    xaxis: { categories: rows.map((row) => row.date), labels: { hideOverlappingLabels: true, rotate: 0 } }, yaxis: { labels: { formatter: rupiah } }, grid: { borderColor: '#e4e6ef', strokeDashArray: 4 }, legend: { show: false }, tooltip: { shared: true, y: { formatter: rupiah } },
                }).render();
            });
        </script>
    @endpush
@endsection
