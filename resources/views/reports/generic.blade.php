@extends('layouts.metronic.app')

@php
    use App\Support\CurrencyFormatter;
    use App\Enums\B2bOrderStatus;
    use App\Enums\PricingChannel;
    use App\Enums\ReceivableStatus;
    use Illuminate\Support\Carbon;

    $type = $report['type'];
    $summary = $report['summary'];
    $rows = $report['rows'];
    $filters = $report['filters'];
    $charts = $report['charts'] ?? [];

    $meta = match ($type) {
        'warehouse' => ['title' => 'Laporan Gudang', 'description' => 'Kesehatan persediaan, arus barang, dan prioritas penanganan stok.', 'icon' => 'ki-package', 'tone' => 'primary'],
        'retail' => ['title' => 'Laporan Toko', 'description' => 'Kinerja penjualan, transaksi, kas, dan stok cabang dalam satu tampilan.', 'icon' => 'ki-shop', 'tone' => 'success'],
        'b2b' => ['title' => 'Laporan Langganan/B2B', 'description' => 'Nilai order, pelanggan aktif, dan progres fulfillment pesanan langganan.', 'icon' => 'ki-people', 'tone' => 'info'],
        'pricing' => ['title' => 'Laporan Harga dan Margin', 'description' => 'Perubahan harga, approval, dan pengawasan margin per channel.', 'icon' => 'ki-price-tag', 'tone' => 'warning'],
        'receivables' => ['title' => 'Laporan Piutang', 'description' => 'Saldo outstanding, umur piutang, dan akun yang perlu ditagih lebih dahulu.', 'icon' => 'ki-wallet', 'tone' => 'danger'],
        default => ['title' => $labels[$type] ?? 'Laporan', 'description' => 'Ringkasan data operasional berdasarkan periode terpilih.', 'icon' => 'ki-chart-simple', 'tone' => 'primary'],
    };

    $kpiDefinitions = match ($type) {
        'warehouse' => [
            ['stock_value', 'Nilai Persediaan', 'currency', 'ki-dollar', 'primary', 'Nilai stok berdasarkan HPP'],
            ['available_quantity', 'Stok Tersedia', 'quantity', 'ki-check-circle', 'success', 'Siap dipakai atau dijual'],
            ['reserved_quantity', 'Direservasi', 'quantity', 'ki-lock', 'info', 'Sudah dialokasikan ke order'],
            ['critical_count', 'SKU Kritis', 'number', 'ki-information-5', 'warning', 'Menyentuh batas minimum'],
            ['empty_count', 'SKU Kosong', 'number', 'ki-cross-circle', 'danger', 'Tidak memiliki stok tersedia'],
            ['pending_transfer', 'Transfer Berjalan', 'number', 'ki-arrow-right-left', 'primary', 'Belum selesai diterima'],
        ],
        'retail' => [
            ['revenue', 'Omzet', 'currency', 'ki-chart-line-up', 'success', 'Transaksi selesai pada periode ini'],
            ['transaction_count', 'Transaksi', 'number', 'ki-receipt-square', 'primary', 'Nota selesai dan retur'],
            ['average_ticket', 'Rata-rata Nota', 'currency', 'ki-calculator', 'info', 'Omzet rata-rata per transaksi'],
            ['critical_stock_count', 'Stok Kritis', 'number', 'ki-information-5', 'warning', 'SKU yang perlu direstock'],
            ['closing_pending_count', 'Closing Pending', 'number', 'ki-time', 'warning', 'Shift menunggu pemeriksaan'],
            ['cash_difference', 'Selisih Kas', 'currency', 'ki-wallet', 'danger', 'Akumulasi selisih closing'],
        ],
        'b2b' => [
            ['revenue', 'Nilai Order', 'currency', 'ki-chart-line-up', 'primary', 'Order aktif pada periode ini'],
            ['order_count', 'Jumlah Order', 'number', 'ki-parcel', 'info', 'Tidak termasuk batal dan ditolak'],
            ['average_order', 'Rata-rata Order', 'currency', 'ki-calculator', 'success', 'Nilai rata-rata per order'],
            ['active_customers', 'Pelanggan Aktif', 'number', 'ki-people', 'primary', 'Pelanggan dengan order'],
            ['pending_fulfillment', 'Dalam Fulfillment', 'number', 'ki-delivery-3', 'warning', 'Validasi hingga pengiriman'],
        ],
        'pricing' => [
            ['price_changes', 'Perubahan Harga', 'number', 'ki-price-tag', 'primary', 'Revisi harga pada periode ini'],
            ['average_price_change', 'Rerata Perubahan', 'percent', 'ki-chart-line-up-2', 'info', 'Naik atau turun dari harga lama'],
            ['pending_approvals', 'Menunggu Approval', 'number', 'ki-check-square', 'warning', 'Belum diputuskan approver'],
            ['sensitive_anomalies', 'Anomali Terbuka', 'number', 'ki-shield-cross', 'danger', 'Perlu pemeriksaan harga'],
            ['average_margin', 'Rerata Margin', 'percent', 'ki-chart-pie-4', 'success', 'Hanya untuk akses margin sensitif'],
        ],
        'receivables' => [
            ['outstanding', 'Total Outstanding', 'currency', 'ki-wallet', 'primary', 'Saldo yang belum diselesaikan'],
            ['overdue', 'Lewat Jatuh Tempo', 'currency', 'ki-information-5', 'danger', 'Prioritas penagihan'],
            ['open_accounts', 'Tagihan Aktif', 'number', 'ki-document', 'info', 'Open, partial, dan overdue'],
            ['overdue_accounts', 'Akun Overdue', 'number', 'ki-profile-circle', 'warning', 'Jumlah tagihan terlambat'],
        ],
        default => [],
    };

    $focusItems = match ($type) {
        'warehouse' => [
            ['label' => 'SKU kosong', 'value' => $summary['empty_count'] ?? 0, 'tone' => 'danger', 'note' => 'Tindak lanjuti restock atau transfer.'],
            ['label' => 'SKU kritis', 'value' => $summary['critical_count'] ?? 0, 'tone' => 'warning', 'note' => 'Periksa kebutuhan dan lead time.'],
            ['label' => 'Opname berjalan', 'value' => $summary['open_opname'] ?? 0, 'tone' => 'info', 'note' => 'Pastikan counting diselesaikan.'],
        ],
        'retail' => [
            ['label' => 'Closing pending', 'value' => $summary['closing_pending_count'] ?? 0, 'tone' => 'warning', 'note' => 'Periksa sebelum pergantian hari.'],
            ['label' => 'Void', 'value' => $summary['void_count'] ?? 0, 'tone' => 'danger', 'note' => 'Pastikan alasan dan approval sesuai.'],
            ['label' => 'Retur', 'value' => CurrencyFormatter::rupiah($summary['return_amount'] ?? 0), 'tone' => 'info', 'note' => 'Nilai pengembalian periode ini.'],
        ],
        'b2b' => [
            ['label' => 'Dalam fulfillment', 'value' => $summary['pending_fulfillment'] ?? 0, 'tone' => 'warning', 'note' => 'Validasi, reservasi, packing, atau kirim.'],
            ['label' => 'Pelanggan aktif', 'value' => $summary['active_customers'] ?? 0, 'tone' => 'success', 'note' => 'Memiliki order pada periode ini.'],
            ['label' => 'Rata-rata order', 'value' => CurrencyFormatter::rupiah($summary['average_order'] ?? 0), 'tone' => 'primary', 'note' => 'Gunakan untuk membaca kualitas order.'],
        ],
        'pricing' => [
            ['label' => 'Approval tertunda', 'value' => $summary['pending_approvals'] ?? 0, 'tone' => 'warning', 'note' => 'Harga belum boleh dianggap final.'],
            ['label' => 'Anomali sensitif', 'value' => $summary['sensitive_anomalies'] ?? 0, 'tone' => 'danger', 'note' => 'Periksa margin dan batas minimum.'],
            ['label' => 'Rerata perubahan', 'value' => number_format((float) ($summary['average_price_change'] ?? 0), 2, ',', '.').'%', 'tone' => 'info', 'note' => 'Dibandingkan harga sebelumnya.'],
        ],
        'receivables' => [
            ['label' => 'Lewat jatuh tempo', 'value' => CurrencyFormatter::rupiah($summary['overdue'] ?? 0), 'tone' => 'danger', 'note' => 'Dahulukan pada jadwal penagihan.'],
            ['label' => 'Akun overdue', 'value' => $summary['overdue_accounts'] ?? 0, 'tone' => 'warning', 'note' => 'Jumlah dokumen terlambat.'],
            ['label' => 'Tagihan aktif', 'value' => $summary['open_accounts'] ?? 0, 'tone' => 'info', 'note' => 'Masih memiliki saldo berjalan.'],
        ],
        default => [],
    };

    $tableColumns = match ($type) {
        'warehouse' => ['sku' => 'SKU', 'product' => 'Produk', 'location' => 'Lokasi', 'quantity_on_hand' => 'On Hand', 'quantity_reserved' => 'Reservasi', 'quantity_damaged' => 'Rusak', 'available' => 'Tersedia', 'cost_value' => 'Nilai Stok'],
        'retail' => ['date' => 'Tanggal', 'location' => 'Toko', 'cashier' => 'Kasir', 'transaction_count' => 'Transaksi', 'revenue' => 'Omzet', 'margin' => 'Margin'],
        'b2b' => ['customer' => 'Pelanggan', 'status' => 'Status Order', 'order_count' => 'Order', 'revenue' => 'Nilai Order'],
        'pricing' => ['created_at' => 'Waktu', 'sku' => 'SKU', 'product' => 'Produk', 'channel' => 'Channel', 'old_price' => 'Harga Lama', 'new_price' => 'Harga Baru', 'price_change_percent' => 'Perubahan', 'hpp_snapshot' => 'HPP', 'margin_percent' => 'Margin', 'reason' => 'Alasan'],
        'receivables' => ['number' => 'Nomor', 'customer' => 'Pelanggan', 'location' => 'Lokasi', 'channel' => 'Channel', 'issue_date' => 'Terbit', 'due_date' => 'Jatuh Tempo', 'principal_amount' => 'Pokok', 'paid_amount' => 'Terbayar', 'outstanding_amount' => 'Outstanding', 'aging_bucket' => 'Aging', 'status' => 'Status'],
        default => array_combine(array_keys($rows[0] ?? []), array_keys($rows[0] ?? [])) ?: [],
    };

    $moneyColumns = ['cost_value', 'revenue', 'margin', 'old_price', 'new_price', 'hpp_snapshot', 'principal_amount', 'paid_amount', 'outstanding_amount'];
    $quantityColumns = ['quantity_on_hand', 'quantity_reserved', 'quantity_damaged', 'available'];
    $percentColumns = ['price_change_percent', 'margin_percent'];
    $dateColumns = ['date', 'issue_date', 'due_date'];
    $dateTimeColumns = ['created_at'];
    $formatKpi = static function (mixed $value, string $format): string {
        if ($value === null) return 'Akses terbatas';
        return match ($format) {
            'currency' => CurrencyFormatter::rupiah($value),
            'quantity' => qty($value),
            'percent' => number_format((float) $value, 2, ',', '.').'%',
            default => number_format((float) $value, 0, ',', '.'),
        };
    };

    $movement = $charts['movement'] ?? [];
    $revenue = $charts['revenue'] ?? [];
    $trend = $charts['trend'] ?? [];
    $primaryChart = match ($type) {
        'warehouse' => ['title' => 'Arus Stok', 'subtitle' => 'Kuantitas masuk dan keluar pada periode terpilih', 'type' => 'area', 'format' => 'quantity', 'categories' => array_column($movement, 'date'), 'series' => [
            ['name' => 'Masuk', 'data' => array_map(fn ($row) => (float) $row['incoming'], $movement)],
            ['name' => 'Keluar', 'data' => array_map(fn ($row) => (float) $row['outgoing'], $movement)],
        ]],
        'retail' => ['title' => 'Tren Omzet', 'subtitle' => 'Pergerakan omzet toko pada periode terpilih', 'type' => 'area', 'format' => 'currency', 'categories' => array_column($revenue, 'date'), 'series' => [
            ['name' => 'Omzet', 'data' => array_map(fn ($row) => (float) $row['retail'], $revenue)],
        ]],
        'b2b' => ['title' => 'Tren Nilai Order', 'subtitle' => 'Nilai order B2B yang tidak dibatalkan atau ditolak', 'type' => 'area', 'format' => 'currency', 'categories' => array_column($revenue, 'date'), 'series' => [
            ['name' => 'Nilai Order', 'data' => array_map(fn ($row) => (float) $row['b2b'], $revenue)],
        ]],
        'pricing' => ['title' => 'Tren Perubahan Harga', 'subtitle' => 'Rata-rata persentase perubahan harga per hari', 'type' => 'line', 'format' => 'percent', 'categories' => array_column($trend, 'date'), 'series' => [
            ['name' => 'Perubahan', 'data' => array_map(fn ($row) => (float) $row['average_change'], $trend)],
        ]],
        'receivables' => ['title' => 'Outstanding per Channel', 'subtitle' => 'Kontribusi saldo piutang dari tiap channel', 'type' => 'bar', 'format' => 'currency', 'categories' => array_column($charts['channel_distribution'] ?? [], 'label'), 'series' => [
            ['name' => 'Outstanding', 'data' => array_map(fn ($row) => (float) $row['value'], $charts['channel_distribution'] ?? [])],
        ]],
        default => ['title' => 'Tren', 'subtitle' => '', 'type' => 'area', 'format' => 'number', 'categories' => [], 'series' => []],
    };

    $secondaryRows = match ($type) {
        'warehouse' => $charts['stock_composition'] ?? [],
        'retail' => $charts['payment_methods'] ?? [],
        'b2b' => $charts['status_distribution'] ?? [],
        'pricing' => $charts['channel_distribution'] ?? [],
        'receivables' => $charts['aging'] ?? [],
        default => [],
    };
    $secondaryChart = [
        'title' => match ($type) {
            'warehouse' => 'Komposisi Persediaan', 'retail' => 'Metode Pembayaran', 'b2b' => 'Status Order',
            'pricing' => 'Perubahan per Channel', 'receivables' => 'Umur Piutang', default => 'Komposisi',
        },
        'subtitle' => match ($type) {
            'warehouse' => 'Tersedia, direservasi, dan rusak', 'retail' => 'Nilai pembayaran transaksi selesai',
            'b2b' => 'Distribusi jumlah order per status', 'pricing' => 'Frekuensi revisi harga per channel',
            'receivables' => 'Saldo outstanding per aging bucket', default => '',
        },
        'format' => in_array($type, ['retail', 'receivables'], true) ? 'currency' : ($type === 'warehouse' ? 'quantity' : 'number'),
        'labels' => array_map(fn ($row) => ucwords(str_replace('_', ' ', (string) $row['label'])), $secondaryRows),
        'series' => array_map(fn ($row) => (float) $row['value'], $secondaryRows),
    ];
@endphp

@section('title', $meta['title'].' - '.config('app.name'))
@section('page_title', $meta['title'])

@section('page_guide')
    <x-metronic.page-guide :id="'report-'.$type" :title="'Panduan '.$meta['title']">
        <x-slot:function><p>{{ $meta['description'] }} Angka selalu mengikuti periode dan lokasi yang dipilih.</p></x-slot:function>
        <x-slot:workflow><ol><li>Pilih periode dan lokasi.</li><li>Baca KPI utama dari kiri ke kanan.</li><li>Gunakan grafik untuk melihat pola, bukan hanya total.</li><li>Tindak lanjuti bagian Perlu Perhatian melalui modul operasional terkait.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>KPI:</strong> posisi angka utama.</li><li><strong>Grafik:</strong> tren dan komposisi.</li><li><strong>Perlu Perhatian:</strong> indikator operasional yang membutuhkan tindakan.</li><li><strong>Detail:</strong> sumber angka yang dapat ditelusuri.</li></ul></x-slot:parts>
        <x-slot:warnings><div class="alert alert-warning mb-0">Laporan bersifat baca-saja. Perbaikan data harus dilakukan melalui transaksi sumber, bukan dari laporan.</div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title :title="$meta['title']" :description="$meta['description']">
        <x-slot:actions>
            @can('reports.export')
                <a href="{{ route('reports.exports.index', ['report_type' => $type, 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date']]) }}" class="btn btn-light-primary"><i class="ki-outline ki-exit-down fs-5"></i> Export</a>
            @endcan
        </x-slot:actions>
    </x-metronic.page-title>

    <x-metronic.card class="mb-5">
        <form method="GET" class="row g-4 align-items-end" id="report-filter-form">
            <div class="col-xl-2 col-md-4"><label class="form-label fw-semibold">Mulai</label><input id="report-start-date" type="date" name="start_date" value="{{ $filters['start_date'] }}" class="form-control form-control-solid"></div>
            <div class="col-xl-2 col-md-4"><label class="form-label fw-semibold">Selesai</label><input id="report-end-date" type="date" name="end_date" value="{{ $filters['end_date'] }}" class="form-control form-control-solid"></div>
            @if(in_array($type, ['warehouse', 'retail', 'receivables'], true))
                <div class="col-xl-3 col-md-4"><label class="form-label fw-semibold">Lokasi</label><select name="work_location_id" class="form-select form-select-solid" data-control="select2" data-searchable="false"><option value="">Semua lokasi yang diizinkan</option>@foreach($workLocations as $location)<option value="{{ $location->id }}" @selected((int) ($filters['work_location_id'] ?? 0) === (int) $location->id)>{{ $location->code }} — {{ $location->name }}</option>@endforeach</select></div>
            @endif
            @if(in_array($type, ['b2b', 'receivables'], true))
                <div class="col-xl-3 col-md-4"><label class="form-label fw-semibold">Pelanggan</label><select name="customer_id" class="form-select form-select-solid" data-control="select2"><option value="">Semua pelanggan</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((int) ($filters['customer_id'] ?? 0) === (int) $customer->id)>{{ $customer->code }} — {{ $customer->business_name }}</option>@endforeach</select></div>
            @endif
            @if($type === 'b2b')
                <div class="col-xl-2 col-md-4"><label class="form-label fw-semibold">Status Order</label><select name="status" class="form-select form-select-solid"><option value="">Semua status</option>@foreach(B2bOrderStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
            @elseif($type === 'pricing')
                <div class="col-xl-2 col-md-4"><label class="form-label fw-semibold">Channel</label><select name="channel" class="form-select form-select-solid"><option value="">Semua channel</option>@foreach(PricingChannel::cases() as $channel)@continue($channel === PricingChannel::ALL)<option value="{{ $channel->value }}" @selected(($filters['channel'] ?? '') === $channel->value)>{{ $channel->label() }}</option>@endforeach</select></div>
            @elseif($type === 'receivables')
                <div class="col-xl-2 col-md-4"><label class="form-label fw-semibold">Channel</label><select name="channel" class="form-select form-select-solid"><option value="">Semua channel</option><option value="retail" @selected(($filters['channel'] ?? '') === 'retail')>Retail</option><option value="b2b" @selected(($filters['channel'] ?? '') === 'b2b')>B2B</option></select></div>
                <div class="col-xl-2 col-md-4"><label class="form-label fw-semibold">Status</label><select name="status" class="form-select form-select-solid"><option value="">Semua status</option>@foreach(ReceivableStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
            @endif
            @if(in_array($type, ['warehouse', 'retail', 'b2b'], true))
                <div class="col-xl-2 col-md-4"><label class="form-label fw-semibold">Interval Grafik</label><select name="range" class="form-select form-select-solid"><option value="daily" @selected($filters['range'] === 'daily')>Harian</option><option value="monthly" @selected($filters['range'] === 'monthly')>Bulanan</option><option value="yearly" @selected($filters['range'] === 'yearly')>Tahunan</option></select></div>
            @endif
            <div class="col-xl-auto col-md-4 d-flex gap-2 ms-xl-auto"><button type="button" class="btn btn-light" data-report-period="month">Bulan ini</button><button type="submit" class="btn btn-primary"><i class="ki-outline ki-filter fs-5"></i> Terapkan</button></div>
        </form>
    </x-metronic.card>

    <div class="row g-5 mb-5">
        @foreach($kpiDefinitions as [$key, $label, $format, $icon, $tone, $help])
            <div class="col-xxl-2 col-xl-4 col-md-6"><div class="card report-kpi-card h-100"><div class="card-body p-5"><div class="d-flex align-items-center justify-content-between mb-4"><span class="report-kpi-icon bg-light-{{ $tone }} text-{{ $tone }}"><i class="ki-outline {{ $icon }} fs-2"></i></span><span class="text-muted fs-8 text-uppercase fw-bold">{{ $label }}</span></div><div class="fs-3 fw-bolder text-gray-900 text-nowrap">{{ $formatKpi($summary[$key] ?? null, $format) }}</div><div class="text-muted fs-8 mt-2">{{ $help }}</div></div></div></div>
        @endforeach
    </div>

    <div class="row g-5 mb-5">
        <div class="col-xl-8"><x-metronic.card class="h-100" :title="$primaryChart['title']"><div class="text-muted fs-7 mb-3">{{ $primaryChart['subtitle'] }}</div><div id="report-primary-chart" class="report-chart"></div></x-metronic.card></div>
        <div class="col-xl-4"><x-metronic.card class="h-100" :title="$secondaryChart['title']"><div class="text-muted fs-7 mb-3">{{ $secondaryChart['subtitle'] }}</div><div id="report-secondary-chart" class="report-chart"></div></x-metronic.card></div>
    </div>

    <div class="row g-5 mb-5">
        <div class="col-xl-7"><x-metronic.card title="Perlu Perhatian" class="h-100"><div class="report-focus-list">@foreach($focusItems as $item)<div class="report-focus-item"><span class="report-focus-dot bg-{{ $item['tone'] }}"></span><div class="flex-grow-1"><div class="fw-semibold text-gray-800">{{ $item['label'] }}</div><div class="text-muted fs-8">{{ $item['note'] }}</div></div><div class="fw-bold fs-4 text-gray-900">{{ $item['value'] }}</div></div>@endforeach</div></x-metronic.card></div>
        <div class="col-xl-5">@include('reports.partials.definitions', ['definitions' => $report['definitions']])</div>
    </div>

    <x-metronic.card title="Rincian Data">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-5"><div class="text-muted fs-7">Menampilkan maksimal 200 baris · diperbarui {{ $report['last_updated_at']->format('d/m/Y H:i') }}</div><span class="badge badge-light-{{ $meta['tone'] }}">{{ count($rows) }} baris</span></div>
        <div class="table-responsive"><table class="table table-row-dashed align-middle report-table"><thead><tr class="text-muted fw-bold text-uppercase fs-8">@foreach($tableColumns as $column => $heading)<th>{{ $heading }}</th>@endforeach</tr></thead><tbody>
            @forelse($rows as $row)
                <tr>@foreach($tableColumns as $column => $heading) @php($value = $row[$column] ?? null) <td @class(['fw-semibold text-gray-800' => in_array($column, ['product', 'customer', 'number'], true)])>
                    @if($value === null || $value === '') <span class="text-muted">—</span>
                    @elseif(in_array($column, $moneyColumns, true)) {{ CurrencyFormatter::rupiah($value) }}
                    @elseif(in_array($column, $quantityColumns, true)) {{ qty($value) }}
                    @elseif(in_array($column, $percentColumns, true)) <span @class(['text-success' => (float) $value >= 0, 'text-danger' => (float) $value < 0])>{{ number_format((float) $value, 2, ',', '.') }}%</span>
                    @elseif(in_array($column, $dateColumns, true)) {{ Carbon::parse($value)->format('d/m/Y') }}
                    @elseif(in_array($column, $dateTimeColumns, true)) {{ Carbon::parse($value)->format('d/m/Y H:i') }}
                    @elseif($column === 'status') <x-metronic.status-badge :status="$value" :label="ucwords(str_replace('_', ' ', $value))" />
                    @elseif($column === 'channel' || $column === 'aging_bucket') <span class="badge badge-light">{{ ucwords(str_replace('_', ' ', $value)) }}</span>
                    @elseif(is_numeric($value)) {{ number_format((float) $value, 0, ',', '.') }}
                    @else {{ $value }} @endif
                </td> @endforeach</tr>
            @empty
                <tr><td colspan="{{ max(1, count($tableColumns)) }}"><x-metronic.empty-state title="Belum ada data pada periode ini" description="Ubah periode atau lokasi untuk memperluas hasil laporan." /></td></tr>
            @endforelse
        </tbody></table></div>
    </x-metronic.card>

    @push('styles')
        <style>
            .report-kpi-card { border: 1px solid var(--bs-gray-200); box-shadow: none; }
            .report-kpi-icon { align-items: center; border-radius: .65rem; display: inline-flex; height: 42px; justify-content: center; width: 42px; }
            .report-chart { min-height: 315px; }
            .report-focus-list { display: grid; gap: 0; }
            .report-focus-item { align-items: center; border-bottom: 1px solid var(--bs-gray-200); display: flex; gap: 1rem; padding: 1rem 0; }
            .report-focus-item:first-child { padding-top: 0; }.report-focus-item:last-child { border-bottom: 0; padding-bottom: 0; }
            .report-focus-dot { border-radius: 999px; flex: 0 0 9px; height: 9px; width: 9px; }.report-table td { white-space: nowrap; }
            @media (max-width: 767.98px) { .report-chart { min-height: 270px; } }
        </style>
    @endpush

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const primary = @json($primaryChart); const secondary = @json($secondaryChart);
                const primaryTarget = document.getElementById('report-primary-chart'); const secondaryTarget = document.getElementById('report-secondary-chart');
                const numberValue = value => Number.parseFloat(value || 0);
                const formatValue = (value, format) => { if (format === 'currency') return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(numberValue(value)); if (format === 'percent') return `${numberValue(value).toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`; return numberValue(value).toLocaleString('id-ID', { maximumFractionDigits: format === 'quantity' ? 2 : 0 }); };
                const emptyChart = (target, message) => { target.innerHTML = `<div class="d-flex align-items-center justify-content-center text-muted text-center h-300px">${message}</div>`; };
                if (typeof window.ApexCharts === 'undefined') { emptyChart(primaryTarget, 'Grafik belum dapat dimuat.'); emptyChart(secondaryTarget, 'Grafik belum dapat dimuat.'); return; }
                if (!primary.categories.length || !primary.series.some(series => series.data.some(value => numberValue(value) !== 0))) emptyChart(primaryTarget, 'Belum ada pergerakan data pada periode ini.');
                else new window.ApexCharts(primaryTarget, { series: primary.series, chart: { type: primary.type, height: 315, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' }, colors: ['#1b84ff', '#17c653', '#f6c000'], stroke: { curve: 'smooth', width: primary.type === 'bar' ? 0 : 3 }, dataLabels: { enabled: false }, fill: primary.type === 'area' ? { type: 'gradient', gradient: { opacityFrom: .32, opacityTo: .03 } } : { opacity: 1 }, plotOptions: { bar: { borderRadius: 5, columnWidth: '46%' } }, xaxis: { categories: primary.categories, labels: { style: { colors: '#99a1b7' } } }, yaxis: { labels: { formatter: value => formatValue(value, primary.format), style: { colors: '#99a1b7' } } }, grid: { borderColor: '#e4e6ef', strokeDashArray: 4 }, legend: { position: 'top', horizontalAlign: 'right' }, tooltip: { y: { formatter: value => formatValue(value, primary.format) } } }).render();
                if (!secondary.series.length || !secondary.series.some(value => numberValue(value) !== 0)) emptyChart(secondaryTarget, 'Belum ada komposisi data pada periode ini.');
                else new window.ApexCharts(secondaryTarget, { series: secondary.series, labels: secondary.labels, chart: { type: 'donut', height: 315, fontFamily: 'Inter, sans-serif' }, colors: ['#1b84ff', '#17c653', '#f6c000', '#f8285a', '#7239ea', '#43ced7', '#99a1b7'], dataLabels: { enabled: false }, legend: { position: 'bottom', fontSize: '12px' }, plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Total', formatter: chart => formatValue(chart.globals.seriesTotals.reduce((a, b) => a + b, 0), secondary.format) } } } } }, tooltip: { y: { formatter: value => formatValue(value, secondary.format) } } }).render();
                document.querySelector('[data-report-period="month"]')?.addEventListener('click', function () { const now = new Date(); const start = new Date(now.getFullYear(), now.getMonth(), 1); document.getElementById('report-start-date').value = start.toISOString().slice(0, 10); document.getElementById('report-end-date').value = now.toISOString().slice(0, 10); document.getElementById('report-filter-form').submit(); });
            });
        </script>
    @endpush
@endsection
