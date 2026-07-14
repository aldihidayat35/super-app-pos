@extends('layouts.metronic.app')

@section('title', 'Dashboard Langganan')
@section('page_title', 'Dashboard Langganan')

@section('content')
    <x-metronic.page-title title="Dashboard Langganan" description="Ringkasan order, limit, dan aktivitas {{ $customer->business_name }}.">
        <a href="{{ route('langganan.katalog.index') }}" class="btn btn-primary">Belanja Lagi</a>
    </x-metronic.page-title>
    <div class="row g-5 mb-5">
        <div class="col-md-3"><x-metronic.card title="Order Aktif"><div class="fs-2 fw-bold">{{ $dashboard['kpis']['active_orders'] }}</div><div class="text-muted">Sedang diajukan/diproses</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Invoice Belum Lunas"><div class="fs-2 fw-bold">{{ $dashboard['kpis']['open_invoices'] }}</div><div class="text-muted">Issued/partial/overdue</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Sisa Limit"><div class="fs-2 fw-bold">{{ App\Support\CurrencyFormatter::rupiah($creditAvailable) }}</div><div class="text-muted">Piutang: {{ App\Support\CurrencyFormatter::rupiah($customer->receivable_balance) }}</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Keranjang"><div class="fs-2 fw-bold">{{ $cart->items->count() }}</div><div class="text-muted">Item siap checkout</div></x-metronic.card></div>
    </div>
    <div class="row g-5 mb-5">
        <div class="col-md-4"><x-metronic.card title="Order Periode"><div class="fs-3 fw-bold">{{ App\Support\CurrencyFormatter::rupiah($dashboard['kpis']['revenue']) }}</div><div class="text-muted">Nilai order non-cancelled periode aktif</div></x-metronic.card></div>
        <div class="col-md-4"><x-metronic.card title="Outstanding"><div class="fs-3 fw-bold">{{ App\Support\CurrencyFormatter::rupiah($dashboard['kpis']['outstanding_receivable']) }}</div><div class="text-muted">Invoice/piutang belum lunas</div></x-metronic.card></div>
        <div class="col-md-4"><x-metronic.card title="Pengiriman Pending"><div class="fs-3 fw-bold">{{ $dashboard['kpis']['shipment_pending'] }}</div><div class="text-muted">Waiting/packing/ready/shipped</div></x-metronic.card></div>
    </div>
    <div class="row g-5">
        <div class="col-lg-7"><x-metronic.card title="Order Terbaru">
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nomor</th><th>Status</th><th>Total</th><th></th></tr></thead><tbody>
                @forelse($latestOrders as $order)<tr><td class="fw-bold">{{ $order->number }}</td><td><x-metronic.status-badge :status="$order->status->value" :label="$order->status->label()" /></td><td>{{ App\Support\CurrencyFormatter::rupiah($order->grand_total_amount) }}</td><td class="text-end"><a href="{{ route('langganan.orders.show', $order) }}" class="btn btn-sm btn-light">Detail</a></td></tr>
                @empty<tr><td colspan="4"><x-metronic.empty-state title="Belum ada order" description="Mulai dari katalog untuk membuat order pertama." /></td></tr>@endforelse
            </tbody></table></div>
        </x-metronic.card></div>
        <div class="col-lg-5"><x-metronic.card title="Aksi Cepat">
            <div class="d-grid gap-3">
                <a href="{{ route('langganan.katalog.index') }}" class="btn btn-light-primary">Buka Katalog</a>
                <a href="{{ route('langganan.keranjang.index') }}" class="btn btn-light">Lihat Keranjang</a>
                <a href="{{ route('langganan.reorder.index') }}" class="btn btn-light">Reorder Cepat</a>
                <a href="{{ route('langganan.profil.edit') }}" class="btn btn-light">Profil & Alamat</a>
            </div>
        </x-metronic.card></div>
    </div>
@endsection
