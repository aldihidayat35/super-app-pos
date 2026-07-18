@extends('layouts.metronic.app')

@section('title', 'Buat Shipment')
@section('page_title', 'Buat Shipment')

@section('content')
    <x-metronic.page-title title="Buat Shipment" description="Pilih order dan qty yang akan dikirim." />
    <x-metronic.card title="Form Shipment">
        <form method="GET" class="row g-3 mb-5"><div class="col-md-8"><select name="order_id" class="form-select"><option value="">Pilih Order</option>@foreach($orders as $row)<option value="{{ $row->id }}" @selected($order?->id===$row->id)>{{ $row->number }} · {{ $row->customer?->business_name }} · {{ $row->status?->label() }}</option>@endforeach</select></div><div class="col-md-2"><button class="btn btn-light-primary w-100">Pilih</button></div></form>
        @if($order)
            <form method="POST" action="{{ route('shipments.store') }}" class="row g-4">
                @csrf <input type="hidden" name="b2b_order_id" value="{{ $order->id }}">
                <div class="col-md-3"><label class="form-label">Metode</label><select name="delivery_method" class="form-select"><option value="courier">Kurir</option><option value="pickup">Pickup</option><option value="expedition">Ekspedisi</option><option value="internal">Internal</option></select></div>
                <div class="col-md-3"><label class="form-label">Kurir</label><input name="courier_name" value="{{ $order->courier_name }}" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Driver</label><input name="driver_name" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Kendaraan/Resi</label><input name="tracking_no" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Jadwal</label><input name="scheduled_date" type="date" value="{{ now()->toDateString() }}" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Biaya Kirim</label><input name="shipping_cost_amount" type="number" step="0.01" min="0" value="{{ $order->shipping_cost_amount }}" class="form-control"></div>
                <div class="col-12"><div class="table-responsive"><table class="table"><thead><tr><th>Produk</th><th>Reserved</th><th>Qty Shipment</th></tr></thead><tbody>@foreach($order->items as $item)<tr><td>{{ $item->product_name_snapshot }}</td><td>{{ qty($item->reserved_quantity) }}</td><td><input name="planned_quantities[{{ $item->id }}]" type="number" step="1" min="0" max="{{ qty_input($item->reserved_quantity) }}" value="{{ qty_input($item->reserved_quantity) }}" class="form-control"></td></tr>@endforeach</tbody></table></div></div>
                <div class="col-12"><button class="btn btn-primary">Buat Shipment</button></div>
            </form>
        @else
            <x-metronic.empty-state title="Pilih order" description="Order yang siap packing akan tampil dalam daftar pilihan." />
        @endif
    </x-metronic.card>
@endsection
