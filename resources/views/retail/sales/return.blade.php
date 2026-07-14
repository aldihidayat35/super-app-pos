@extends('layouts.metronic.app')

@section('title', 'Retur Pelanggan POS - ' . config('app.name'))
@section('page_title', 'Retur Pelanggan POS')

@section('content')
    <x-metronic.card title="Retur {{ $sale->number }}">
        <form method="POST" action="{{ route('retail.sales.return.store', $sale) }}" class="row g-3">
            @csrf
            <div class="col-md-4"><x-metronic.form-group name="resolution" label="Resolusi" required><select name="resolution" class="form-select"><option value="refund">Refund</option><option value="exchange">Tukar Barang</option><option value="credit">Kredit</option></select></x-metronic.form-group></div>
            <div class="col-md-4"><x-metronic.form-group name="refund_method" label="Metode Pengembalian"><select name="refund_method" class="form-select"><option value="cash">Tunai</option><option value="bank_transfer">Transfer</option><option value="qris">QRIS</option><option value="manual">Manual</option></select></x-metronic.form-group></div>
            <div class="col-md-12"><x-metronic.form-group name="reason" label="Alasan Retur" required><textarea name="reason" class="form-control" rows="2" required></textarea></x-metronic.form-group></div>
            <div class="col-md-12">
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Pilih</th><th>Produk</th><th>Terjual/Sudah Retur</th><th>Qty Retur</th><th>Kondisi</th></tr></thead>
                        <tbody>
                        @foreach($sale->items as $index => $item)
                            <tr>
                                <td><input type="hidden" name="items[{{ $index }}][pos_sale_item_id]" value="{{ $item->id }}"></td>
                                <td>{{ $item->product_name_snapshot }}</td>
                                <td>{{ $item->base_quantity }} / {{ $item->returned_quantity }}</td>
                                <td><input type="number" step="0.0001" min="0" name="items[{{ $index }}][quantity]" value="0" class="form-control"></td>
                                <td><select name="items[{{ $index }}][condition]" class="form-select"><option value="good">Baik</option><option value="damaged">Rusak</option></select></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-12"><button class="btn btn-warning">Simpan Retur</button></div>
        </form>
    </x-metronic.card>
@endsection
