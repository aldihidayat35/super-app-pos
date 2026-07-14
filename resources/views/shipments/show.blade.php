@extends('layouts.metronic.app')

@section('title', $shipment->number)
@section('page_title', 'Detail Pengiriman')

@section('content')
    <x-metronic.page-title :title="$shipment->number" description="Surat jalan, item, status, dan bukti terima.">
        <a href="{{ route('shipments.index') }}" class="btn btn-light">Kembali</a>
        <a href="{{ route('shipments.proof', $shipment) }}" class="btn btn-light-primary">Upload Proof</a>
        @if($shipment->status === App\Enums\ShipmentStatus::PACKING)<form method="POST" action="{{ route('shipments.post', $shipment) }}" class="d-inline">@csrf<button class="btn btn-success">Post Kirim</button></form>@endif
    </x-metronic.page-title>
    <div class="row g-5 mb-5"><div class="col-lg-4"><x-metronic.card title="Order"><div>{{ $shipment->order?->number }}</div><div>{{ $shipment->customer?->business_name }}</div></x-metronic.card></div><div class="col-lg-4"><x-metronic.card title="Tracking"><div>Status: <x-metronic.status-badge :status="$shipment->status->value" :label="$shipment->status->label()" /></div><div>Kurir: {{ $shipment->courier_name ?: '-' }}</div><div>Resi: {{ $shipment->tracking_no ?: '-' }}</div></x-metronic.card></div><div class="col-lg-4"><x-metronic.card title="Penerima"><div>{{ $shipment->receiver_name ?: '-' }}</div><div>{{ $shipment->delivered_at?->format('d/m/Y H:i') ?: '-' }}</div></x-metronic.card></div></div>
    <x-metronic.card title="Item Shipment" class="mb-5"><div class="table-responsive"><table class="table"><thead><tr><th>Produk</th><th>Planned</th><th>Shipped</th><th>Delivered</th><th>Status</th></tr></thead><tbody>@foreach($shipment->items as $item)<tr><td>{{ $item->orderItem?->product_name_snapshot }}</td><td>{{ $item->quantity_planned }}</td><td>{{ $item->quantity_shipped }}</td><td>{{ $item->quantity_delivered }}</td><td>{{ $item->status }}</td></tr>@endforeach</tbody></table></div></x-metronic.card>
    <x-metronic.card title="Proof"><div class="table-responsive"><table class="table"><thead><tr><th>Tipe</th><th>Penerima</th><th>Catatan</th><th>Waktu</th></tr></thead><tbody>@forelse($shipment->proofs as $proof)<tr><td>{{ $proof->type }}</td><td>{{ $proof->receiver_name ?: '-' }}</td><td>{{ $proof->notes ?: '-' }}</td><td>{{ $proof->created_at->format('d/m/Y H:i') }}</td></tr>@empty<tr><td colspan="4"><x-metronic.empty-state title="Belum ada bukti" description="Upload bukti kirim atau bukti terima dari halaman proof." /></td></tr>@endforelse</tbody></table></div></x-metronic.card>
@endsection
