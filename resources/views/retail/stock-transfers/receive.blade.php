@extends('layouts.metronic.app')

@section('title', 'Terima Transfer - ' . config('app.name'))
@section('page_title', 'Penerimaan di Cabang')

@section('content')
    <x-metronic.card title="{{ $transfer->number }}">
        <form method="POST" action="{{ route('retail.stock-transfers.receive', $transfer) }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) str()->uuid() }}">
            <div class="row g-4 mb-5">
                <div class="col-md-4"><label class="form-label">Tanggal Terima</label><input type="datetime-local" name="received_at" value="{{ now()->format('Y-m-d\\TH:i') }}" class="form-control form-control-solid"></div>
                <div class="col-md-4"><label class="form-label">Foto/Bukti</label><input type="file" name="proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></div>
                <div class="col-md-4"><label class="form-label">Catatan</label><input name="notes" class="form-control form-control-solid"></div>
            </div>
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle">
                    <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Dikirim</th><th>Sudah Terima</th><th>In Transit</th><th>Qty Baik</th><th>Rusak</th><th>Kurang/Selisih</th><th>Catatan</th></tr></thead>
                    <tbody>@foreach($transfer->items as $item)<tr><td>{{ $item->product_sku_snapshot }}<div class="text-muted">{{ $item->product_name_snapshot }}</div></td><td>{{ qty($item->quantity_shipped) }}</td><td>{{ qty($item->quantity_received) }}</td><td class="fw-bold">{{ qty($item->inTransitQuantity()) }}</td><td><input name="items[{{ $item->id }}][quantity_received]" type="number" min="0" step="1" value="{{ qty_input($item->inTransitQuantity()) }}" class="form-control form-control-sm"></td><td><input name="items[{{ $item->id }}][quantity_damaged]" type="number" min="0" step="1" value="0" class="form-control form-control-sm"></td><td><input name="items[{{ $item->id }}][quantity_discrepancy]" type="number" min="0" step="1" value="0" class="form-control form-control-sm"></td><td><input name="items[{{ $item->id }}][notes]" class="form-control form-control-sm"></td></tr>@endforeach</tbody>
                </table>
            </div>
            <div class="d-flex justify-content-end"><button class="btn btn-primary" data-confirm="Simpan penerimaan transfer?">Konfirmasi Terima</button></div>
        </form>
    </x-metronic.card>
@endsection
