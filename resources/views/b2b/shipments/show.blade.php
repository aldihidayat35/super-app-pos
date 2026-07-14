@extends('layouts.metronic.app')

@section('title', $shipment->number)
@section('page_title', 'Tracking Pengiriman')

@section('content')
    <x-metronic.page-title :title="$shipment->number" description="Status, timeline, dan bukti pengiriman.">
        <a href="{{ route('langganan.orders.show', $shipment->order) }}" class="btn btn-light">Kembali ke Order</a>
        @if($shipment->status === App\Enums\ShipmentStatus::DELIVERED)<form method="POST" action="{{ route('langganan.shipments.confirm', $shipment) }}" class="d-inline">@csrf<button class="btn btn-success">Konfirmasi Selesai</button></form>@endif
    </x-metronic.page-title>
    <div class="row g-5 mb-5"><div class="col-lg-6"><x-metronic.card title="Tracking"><div>Status: <x-metronic.status-badge :status="$shipment->status->value" :label="$shipment->status->label()" /></div><div>Kurir: {{ $shipment->courier_name ?: '-' }}</div><div>Resi: {{ $shipment->tracking_no ?: '-' }}</div><div>Estimasi/Jadwal: {{ $shipment->scheduled_date?->format('d/m/Y') ?: '-' }}</div></x-metronic.card></div><div class="col-lg-6"><x-metronic.card title="Proof">@forelse($shipment->proofs as $proof)<div class="border-bottom py-2"><strong>{{ $proof->type }}</strong><div class="text-muted">{{ $proof->receiver_name ?: '-' }} · {{ $proof->created_at->format('d/m/Y H:i') }}</div><div>{{ $proof->notes }}</div></div>@empty<x-metronic.empty-state title="Belum ada proof" description="Bukti akan tampil setelah gudang/kurir mengunggah." />@endforelse</x-metronic.card></div></div>
    <x-metronic.card title="Item"><div class="table-responsive"><table class="table"><thead><tr><th>Produk</th><th>Dikirim</th><th>Diterima</th><th>Status</th></tr></thead><tbody>@foreach($shipment->items as $item)<tr><td>{{ $item->orderItem?->product_name_snapshot }}</td><td>{{ $item->quantity_shipped }}</td><td>{{ $item->quantity_delivered }}</td><td>{{ $item->status }}</td></tr>@endforeach</tbody></table></div></x-metronic.card>
@endsection
