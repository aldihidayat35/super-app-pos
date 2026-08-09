@extends('layouts.metronic.app')

@section('title', 'Terima Transfer - '.config('app.name'))
@section('page_title', 'Penerimaan Transfer')

@section('toolbar_actions')<a href="{{ route('retail.stock-transfers.receiving') }}" class="btn btn-light"><i class="ki-outline ki-arrow-left fs-5 me-1"></i>Daftar Penerimaan</a>@endsection

@section('content')
    <x-metronic.card title="Transfer {{ $transfer->number }}" class="mb-6">
        <div class="row g-4"><div class="col-md-2"><div class="text-muted fs-8">STATUS</div><x-metronic.status-badge :status="$transfer->status" /></div><div class="col-md-2"><div class="text-muted fs-8">SUMBER</div><strong>{{ $transfer->sourceWorkLocation?->name }}</strong></div><div class="col-md-2"><div class="text-muted fs-8">TUJUAN</div><strong>{{ $transfer->destinationWorkLocation?->name }}</strong></div><div class="col-md-2"><div class="text-muted fs-8">TANGGAL KIRIM</div><strong>{{ $transfer->shipped_at?->format('d/m/Y H:i') ?: '-' }}</strong></div><div class="col-md-2"><div class="text-muted fs-8">PENGIRIM</div><strong>{{ $transfer->shipper?->name ?: '-' }}</strong></div><div class="col-md-2"><div class="text-muted fs-8">NOMOR</div><strong>{{ $transfer->number }}</strong></div></div>
    </x-metronic.card>
    <x-metronic.card title="Item yang Diterima">
        <form method="POST" action="{{ route('retail.stock-transfers.receive', $transfer) }}" enctype="multipart/form-data">@csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) str()->uuid() }}">
            <div class="row g-4 mb-5"><div class="col-md-4"><x-metronic.form-group name="received_at" label="Tanggal Terima"><input type="datetime-local" name="received_at" value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}" class="form-control form-control-solid"></x-metronic.form-group></div><div class="col-md-4"><x-metronic.form-group name="proof" label="Foto/Bukti"><input type="file" name="proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></x-metronic.form-group></div><div class="col-md-4"><x-metronic.form-group name="notes" label="Catatan Penerimaan"><input name="notes" value="{{ old('notes') }}" class="form-control form-control-solid"></x-metronic.form-group></div></div>
            <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Qty Dikirim</th><th>Sudah Diterima</th><th>Sisa</th><th>Qty Diterima Sekarang</th><th>Qty Rusak</th><th>Selisih</th><th>Catatan</th></tr></thead><tbody>
            @foreach($transfer->items as $item)
                <tr><td><strong>{{ $item->product_sku_snapshot }}</strong><div class="text-muted">{{ $item->product_name_snapshot }}</div></td><td>{{ qty($item->quantity_shipped) }}</td><td>{{ qty(\App\Support\Decimal::add((string) $item->quantity_received, \App\Support\Decimal::add((string) $item->quantity_damaged, (string) $item->quantity_discrepancy))) }}</td><td class="fw-bold text-primary">{{ qty($item->inTransitQuantity()) }}</td><td><input name="items[{{ $item->id }}][quantity_received]" type="number" min="0" step="0.0001" value="{{ old("items.{$item->id}.quantity_received", qty_input($item->inTransitQuantity())) }}" class="form-control form-control-sm"></td><td><input name="items[{{ $item->id }}][quantity_damaged]" type="number" min="0" step="0.0001" value="{{ old("items.{$item->id}.quantity_damaged", 0) }}" class="form-control form-control-sm"></td><td><input name="items[{{ $item->id }}][quantity_discrepancy]" type="number" min="0" step="0.0001" value="{{ old("items.{$item->id}.quantity_discrepancy", 0) }}" class="form-control form-control-sm"></td><td><input name="items[{{ $item->id }}][notes]" value="{{ old("items.{$item->id}.notes") }}" class="form-control form-control-sm"></td></tr>
            @endforeach
            </tbody></table></div>
            <div class="alert alert-light-warning fs-7">Qty baik, rusak, dan selisih tidak boleh melebihi sisa barang dalam perjalanan. Penerimaan sebagian tetap dapat dilanjutkan pada penerimaan berikutnya.</div>
            <div class="d-flex justify-content-end"><button class="btn btn-primary" data-confirm="Simpan penerimaan transfer ini?"><i class="ki-outline ki-check fs-4 me-1"></i>Konfirmasi Terima</button></div>
        </form>
    </x-metronic.card>
@endsection
