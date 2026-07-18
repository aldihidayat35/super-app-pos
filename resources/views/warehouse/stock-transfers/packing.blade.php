@extends('layouts.metronic.app')

@section('title', 'Packing Transfer - ' . config('app.name'))
@section('page_title', 'Picking dan Packing')

@section('content')
    <x-metronic.card title="{{ $transfer->number }}">
        <form method="POST" action="{{ route('warehouse.stock-transfers.pack', $transfer) }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-4 mb-5">
                <div class="col-md-4"><label class="form-label">Nomor Paket</label><input name="package_no" value="PKG-{{ now()->format('His') }}" class="form-control form-control-solid"></div>
                <div class="col-md-4"><label class="form-label">Foto/Bukti Packing</label><input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></div>
                <div class="col-md-4"><label class="form-label">Catatan Paket</label><input name="package_notes" class="form-control form-control-solid"></div>
            </div>
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle">
                    <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Lokasi Ambil</th><th>Approved</th><th>Qty Picked</th><th>Short</th><th>Catatan</th></tr></thead>
                    <tbody>
                    @foreach($transfer->items as $item)
                        <tr>
                            <td>{{ $item->product_sku_snapshot }}<div class="text-muted">{{ $item->product_name_snapshot }}</div></td>
                            <td>{{ $item->sourceWarehouseLocation?->full_code ?: '-' }}</td>
                            <td>{{ qty($item->quantity_approved) }}</td>
                            <td><input type="number" step="1" min="0" max="{{ qty_input($item->quantity_approved) }}" name="items[{{ $item->id }}][quantity_picked]" value="{{ old("items.$item->id.quantity_picked", qty_input($item->quantity_picked ?: $item->quantity_approved)) }}" class="form-control form-control-sm"></td>
                            <td>{{ qty($item->quantity_short) }}</td>
                            <td><input name="items[{{ $item->id }}][notes]" value="{{ $item->notes }}" class="form-control form-control-sm"></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-end"><button class="btn btn-primary">Simpan Packing</button></div>
        </form>
    </x-metronic.card>
@endsection
