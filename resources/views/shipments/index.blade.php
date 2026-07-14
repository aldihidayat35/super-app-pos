@extends('layouts.metronic.app')

@section('title', 'Pengiriman B2B')
@section('page_title', 'Pengiriman B2B')

@section('content')
    <x-metronic.page-title title="Daftar Pengiriman" description="Queue pengiriman, status POD, dan tracking.">
        <a href="{{ route('shipments.create') }}" class="btn btn-primary">Buat Shipment</a>
    </x-metronic.page-title>
    <x-metronic.card title="Filter Shipment">
        <form class="row g-3 mb-5"><div class="col-md-5"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nomor shipment"></div><div class="col-md-3"><select name="status" class="form-select"><option value="">Semua Status</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></div><div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div></form>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nomor</th><th>Order</th><th>Pelanggan</th><th>Kurir/Resi</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
            @forelse($shipments as $shipment)
                <tr><td class="fw-bold">{{ $shipment->number }}</td><td>{{ $shipment->order?->number }}</td><td>{{ $shipment->customer?->business_name }}</td><td>{{ $shipment->courier_name ?: '-' }}<div class="text-muted">{{ $shipment->tracking_no ?: '-' }}</div></td><td><x-metronic.status-badge :status="$shipment->status->value" :label="$shipment->status->label()" /></td><td class="text-end"><a href="{{ route('shipments.show', $shipment) }}" class="btn btn-sm btn-light">Detail</a></td></tr>
            @empty
                <tr><td colspan="6"><x-metronic.empty-state title="Belum ada shipment" description="Buat shipment dari order B2B yang sudah siap packing." /></td></tr>
            @endforelse
        </tbody></table></div>
        {{ $shipments->links() }}
    </x-metronic.card>
@endsection
