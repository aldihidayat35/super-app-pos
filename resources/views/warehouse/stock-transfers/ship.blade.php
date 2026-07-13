@extends('layouts.metronic.app')

@section('title', 'Kirim Transfer - ' . config('app.name'))
@section('page_title', 'Pengiriman Transfer')

@section('content')
    <x-metronic.card title="{{ $transfer->number }}">
        <div class="alert alert-info">Saat dikirim, stok sumber resmi keluar dan quantity in-transit dicatat pada dokumen transfer.</div>
        <form method="POST" action="{{ route('warehouse.stock-transfers.ship', $transfer) }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-4">
                <div class="col-md-4"><label class="form-label">Kurir/Ekspedisi</label><input name="carrier" class="form-control form-control-solid"></div>
                <div class="col-md-4"><label class="form-label">Kendaraan</label><input name="vehicle_number" class="form-control form-control-solid"></div>
                <div class="col-md-4"><label class="form-label">Resi</label><input name="tracking_number" class="form-control form-control-solid"></div>
                <div class="col-md-4"><label class="form-label">Biaya Kirim</label><input name="shipping_cost" type="number" step="0.01" min="0" class="form-control form-control-solid"></div>
                <div class="col-md-8"><label class="form-label">Bukti/Surat Jalan</label><input type="file" name="proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></div>
            </div>
            <div class="table-responsive mt-6">
                <table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Approved</th><th>Picked</th><th>Akan Dikirim</th></tr></thead><tbody>@foreach($transfer->items as $item)<tr><td>{{ $item->product_sku_snapshot }}<div class="text-muted">{{ $item->product_name_snapshot }}</div></td><td>{{ $item->quantity_approved }}</td><td>{{ $item->quantity_picked }}</td><td class="fw-bold">{{ (float) $item->quantity_picked > 0 ? $item->quantity_picked : $item->quantity_approved }}</td></tr>@endforeach</tbody></table>
            </div>
            <div class="d-flex justify-content-end"><button class="btn btn-primary" data-confirm="Kirim transfer dan keluarkan stok sumber?">Kirim Transfer</button></div>
        </form>
    </x-metronic.card>
@endsection
