@extends('layouts.metronic.app')

@section('title', $customer->business_name)
@section('page_title', 'Detail Customer Saya')
@section('content')
    <x-metronic.page-title :title="$customer->business_name" :description="$customer->business_name . ' — ' . $customer->code">
        <x-slot:actions><a href="{{ route('sales.orders.create', ['customer_id' => $customer->id]) }}" class="btn btn-primary">Buat Order</a><a href="{{ route('sales.customers.index') }}" class="btn btn-light">Kembali</a></x-slot:actions>
    </x-metronic.page-title>
    <div class="row g-5 mb-5">
        <div class="col-lg-6"><x-metronic.card title="Kontak"><div class="mb-2"><strong>PIC:</strong> {{ $customer->pic_name ?: '-' }}</div><div class="mb-2"><strong>WhatsApp:</strong> {{ $customer->whatsapp_number ?: '-' }}</div><div><strong>Email:</strong> {{ $customer->email ?: '-' }}</div></x-metronic.card></div>
        <div class="col-lg-6"><x-metronic.card title="Alamat"><div>{{ $customer->business_address ?: '-' }}</div><div class="text-muted">{{ $customer->city }}</div></x-metronic.card></div>
    </div>
    <x-metronic.card title="Order Customer Ini">
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nomor</th><th>Tanggal</th><th>Total</th><th>Status</th><th>Pengiriman</th></tr></thead><tbody>
            @forelse($orders as $order)<tr><td><a href="{{ route('sales.orders.show', $order) }}" class="fw-bold">{{ $order->number }}</a></td><td>{{ $order->submitted_at?->format('d/m/Y H:i') }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($order->grand_total_amount) }}</td><td><x-metronic.status-badge :status="$order->status->value" :label="$order->status->label()" /></td><td>{{ $order->latestShipment?->status?->label() ?? '-' }}</td></tr>
            @empty<tr><td colspan="5"><x-metronic.empty-state title="Belum ada order" description="Buat order pertama untuk customer ini." /></td></tr>@endforelse
        </tbody></table></div><div class="mt-4">{{ $orders->links() }}</div>
    </x-metronic.card>
@endsection
