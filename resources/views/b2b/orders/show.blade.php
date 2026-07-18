@extends('layouts.metronic.app')

@section('title', $order->number)
@section('page_title', 'Detail Order Langganan')

@section('content')
    <x-metronic.page-title :title="$order->number" description="Detail order pelanggan langganan.">
        <a href="{{ route('langganan.orders.index') }}" class="btn btn-light">Kembali</a>
        @if($order->invoices->isNotEmpty())
            <a href="{{ route('invoices.show', $order->invoices->first()) }}" class="btn btn-light-primary">Invoice</a>
        @endif
        @if($order->shipments->isNotEmpty())
            <a href="{{ route('langganan.shipments.show', $order->shipments->last()) }}" class="btn btn-primary">Tracking Shipment</a>
        @endif
    </x-metronic.page-title>
    <div class="row g-5 mb-5">
        <div class="col-lg-4"><x-metronic.card title="Ringkasan">
            <div class="mb-2">Status: <x-metronic.status-badge :status="$order->status->value" :label="$order->status->label()" /></div>
            <div class="mb-2">Tanggal: <span class="fw-bold">{{ $order->submitted_at?->format('d/m/Y H:i') }}</span></div>
            <div class="mb-2">Total: <span class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($order->grand_total_amount) }}</span></div>
            <div class="mb-2">Pembayaran: <span class="fw-bold">{{ ucfirst(str_replace('_', ' ', $order->payment_preference)) }}</span></div>
            <div class="mb-2">Pengiriman: <span class="fw-bold">{{ ucfirst($order->delivery_method) }} {{ $order->courier_name ? '· '.$order->courier_name : '' }}</span></div>
            <div class="mb-2">Request by: <span class="fw-bold">{{ $order->requester?->name }}</span></div>
            @if($order->status->canCustomerCancel())
                <form method="POST" action="{{ route('langganan.orders.cancel', $order) }}" class="mt-4">@csrf<input name="reason" class="form-control form-control-sm mb-2" required placeholder="Alasan pembatalan"><button class="btn btn-sm btn-light-danger">Batalkan Order</button></form>
            @endif
            @if($order->status === App\Enums\B2bOrderStatus::SHIPPED)
                <form method="POST" action="{{ route('langganan.orders.receive', $order) }}" class="mt-4">@csrf<button class="btn btn-sm btn-success">Konfirmasi Diterima</button></form>
            @endif
        </x-metronic.card></div>
        <div class="col-lg-4"><x-metronic.card title="Alamat Kirim">
            @if($order->address)<div class="fw-bold">{{ $order->address->label }}</div><div>{{ $order->address->address }}</div><div class="text-muted">{{ $order->address->recipient_name }} · {{ $order->address->phone_number }}</div>@else<div class="text-muted">Alamat usaha/utama pelanggan.</div>@endif
        </x-metronic.card></div>
        <div class="col-lg-4"><x-metronic.card title="Kredit Snapshot">
            <div>Limit saat order: {{ App\Support\CurrencyFormatter::rupiah($order->credit_limit_snapshot) }}</div>
            <div>Piutang saat order: {{ App\Support\CurrencyFormatter::rupiah($order->receivable_balance_snapshot) }}</div>
            <div>Catatan: {{ $order->notes ?: '-' }}</div>
        </x-metronic.card></div>
    </div>
    <x-metronic.card title="Timeline Status" class="mb-5">
        <div class="timeline-label">
            @foreach($order->statusHistories as $history)
                <div class="timeline-item mb-4"><div class="timeline-label fw-bold text-gray-800 fs-7">{{ $history->created_at->format('d/m H:i') }}</div><div class="timeline-badge"><i class="fa fa-genderless text-primary fs-1"></i></div><div class="fw-semibold ps-3">{{ $history->to_status }}<div class="text-muted">{{ $history->note }} · {{ $history->actor?->name ?: 'Sistem' }}</div></div></div>
            @endforeach
        </div>
    </x-metronic.card>
    <x-metronic.card title="Item Order">
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Produk</th><th>Satuan</th><th>Qty Request</th><th>Qty Approved</th><th>Reserved</th><th>Issued</th><th>Harga</th><th>Subtotal</th></tr></thead><tbody>
            @foreach($order->items as $item)
                <tr><td><div class="fw-bold">{{ $item->product_name_snapshot }}</div><div class="text-muted">{{ $item->sku_snapshot }}</div></td><td>{{ $item->unit_name_snapshot }}<div class="text-muted">x{{ qty($item->conversion_factor_snapshot) }}</div></td><td>{{ qty($item->quantity) }}</td><td>{{ $item->approved_quantity ? qty($item->approved_quantity) : '-' }}</td><td>{{ qty($item->reserved_quantity) }}</td><td>{{ qty($item->issued_quantity) }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($item->selected_price) }}</td><td class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($item->line_total) }}</td></tr>
            @endforeach
        </tbody></table></div>
    </x-metronic.card>
    <x-metronic.card title="Pesan Gudang" class="mt-5">
        @forelse($order->messages->whereIn('visibility', ['customer', 'public']) as $message)
            <div class="border-bottom py-2"><div>{{ $message->message }}</div><div class="text-muted fs-8">{{ $message->created_at->format('d/m/Y H:i') }} · {{ $message->user?->name ?: 'Sistem' }}</div></div>
        @empty
            <x-metronic.empty-state title="Belum ada pesan" description="Pesan dari gudang akan tampil di sini." />
        @endforelse
    </x-metronic.card>
@endsection
