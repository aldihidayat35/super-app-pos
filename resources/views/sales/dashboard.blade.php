@extends('layouts.metronic.app')

@section('title', 'Dashboard Sales')
@section('page_title', 'Dashboard Sales')
@section('page_guide')
    <x-metronic.page-guide id="sales-dashboard" title="Panduan Dashboard Sales">
        <x-slot:function><p>Memantau target, omzet order B2B yang sudah selesai, jumlah order, customer, dan estimasi bonus periode berjalan.</p></x-slot:function>
        <x-slot:workflow><ol><li>Buat order untuk customer Anda.</li><li>Gudang memproses order sampai selesai.</li><li>Order berstatus Selesai otomatis menambah pencapaian.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li>Kartu metrik menampilkan ringkasan bulan ini.</li><li>Progress bar membandingkan penjualan dan target.</li><li>Order terbaru menampilkan status order dan pengiriman.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Order draft, dibatalkan, ditolak, atau belum selesai tidak dihitung sebagai penjualan.</p></x-slot:impacts>
        <x-slot:operation><p>Gunakan menu Customer Saya dan Buat Order untuk menjalankan aktivitas penjualan.</p></x-slot:operation>
        <x-slot:warnings><p>Bonus baru bernilai jika penjualan selesai sudah mencapai target.</p></x-slot:warnings>
        <x-slot:example><p>Target Rp100 juta dan penjualan selesai Rp75 juta menghasilkan pencapaian 75% dan bonus Rp0.</p></x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title title="Dashboard Sales" description="Performa {{ $period }} berdasarkan order B2B yang telah selesai." />

    <div class="row g-5 mb-5">
        @foreach([
            ['Target Bulan Ini', $metrics['target_amount'], 'ki-chart-line-up-2', 'primary'],
            ['Penjualan Bulan Ini', $metrics['sales_amount'], 'ki-chart-line-up', 'success'],
            ['Sisa Target', $metrics['remaining_target'], 'ki-time', 'warning'],
            ['Estimasi Bonus', $metrics['bonus_amount'], 'ki-dollar', 'info'],
        ] as [$label, $amount, $icon, $color])
            <div class="col-sm-6 col-xl-3">
                <x-metronic.card>
                    <i class="ki-outline {{ $icon }} fs-2x text-{{ $color }} mb-3"></i>
                    <div class="text-muted fw-semibold">{{ $label }}</div>
                    <div class="fs-2 fw-bold">{{ App\Support\CurrencyFormatter::rupiah($amount) }}</div>
                </x-metronic.card>
            </div>
        @endforeach
    </div>

    <div class="row g-5 mb-5">
        <div class="col-lg-8">
            <x-metronic.card title="Pencapaian Target">
                <div class="d-flex justify-content-between mb-2">
                    <span class="fw-semibold">{{ number_format((float) $metrics['achievement_percentage'], 2, ',', '.') }}%</span>
                    <span class="text-muted">Target {{ App\Support\CurrencyFormatter::rupiah($metrics['target_amount']) }}</span>
                </div>
                <div class="progress h-20px">
                    <div class="progress-bar bg-success" role="progressbar" style="width: {{ min(100, max(0, (float) $metrics['achievement_percentage'])) }}%" aria-valuenow="{{ min(100, max(0, (float) $metrics['achievement_percentage'])) }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </x-metronic.card>
        </div>
        <div class="col-lg-4">
            <x-metronic.card title="Aktivitas Bulan Ini">
                <div class="d-flex justify-content-around text-center">
                    <div><div class="fs-2 fw-bold">{{ $metrics['order_count'] }}</div><div class="text-muted">Order Selesai</div></div>
                    <div><div class="fs-2 fw-bold">{{ $metrics['customer_count'] }}</div><div class="text-muted">Customer</div></div>
                </div>
            </x-metronic.card>
        </div>
    </div>

    <x-metronic.card title="Order Terbaru">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Nomor</th><th>Customer</th><th>Tanggal</th><th>Total</th><th>Status</th><th>Pengiriman</th></tr></thead>
                <tbody>
                    @forelse($recentOrders as $order)
                        <tr>
                            <td><a href="{{ route('sales.orders.show', $order) }}" class="fw-bold">{{ $order->number }}</a></td>
                            <td>{{ $order->customer?->business_name }}</td>
                            <td>{{ $order->submitted_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ App\Support\CurrencyFormatter::rupiah($order->grand_total_amount) }}</td>
                            <td><x-metronic.status-badge :status="$order->status->value" :label="$order->status->label()" /></td>
                            <td>{{ $order->latestShipment?->status?->label() ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-metronic.empty-state title="Belum ada order" description="Order yang Anda buat akan tampil di sini." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-metronic.card>
@endsection
