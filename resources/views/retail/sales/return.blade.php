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
                    <table class="table pos-return-entry-table" data-mobile-table="off">
                        <thead><tr><th>Pilih</th><th>Produk</th><th>Terjual/Sudah Retur</th><th>Qty Retur</th><th>Qty normal</th><th>Qty darurat</th><th>Kondisi</th></tr></thead>
                        <tbody>
                        @foreach($sale->items as $index => $item)
                            @php
                                $normalAllocation = $item->allocations->firstWhere('source', 'normal');
                                $emergencyAllocation = $item->allocations->firstWhere('source', 'emergency');
                                $normalRemaining = $item->allocations->isEmpty()
                                    ? \App\Support\Decimal::sub((string) $item->base_quantity, (string) $item->returned_quantity, 4)
                                    : ($normalAllocation ? \App\Support\Decimal::sub((string) $normalAllocation->base_quantity, (string) $normalAllocation->returned_quantity, 4) : '0.0000');
                                $emergencyRemaining = $emergencyAllocation ? \App\Support\Decimal::sub((string) $emergencyAllocation->base_quantity, (string) $emergencyAllocation->returned_quantity, 4) : '0.0000';
                            @endphp
                            <tr>
                                <td><input type="hidden" name="items[{{ $index }}][pos_sale_item_id]" value="{{ $item->id }}"></td>
                                <td data-label="Produk">{{ $item->product_name_snapshot }}</td>
                                <td data-label="Terjual / sudah retur">{{ qty($item->base_quantity) }} / {{ qty($item->returned_quantity) }}</td>
                                <td data-label="Qty retur"><input type="number" step="0.0001" min="0" name="items[{{ $index }}][quantity]" value="0" class="form-control"></td>
                                <td data-label="Qty normal"><input type="number" step="0.0001" min="0" name="items[{{ $index }}][normal_quantity]" value="0" class="form-control"><small class="text-muted">Sisa: {{ qty($normalRemaining) }}</small></td>
                                <td data-label="Qty darurat"><input type="number" step="0.0001" min="0" name="items[{{ $index }}][emergency_quantity]" value="0" class="form-control"><small class="text-muted">Sisa: {{ qty($emergencyRemaining) }}</small></td>
                                <td data-label="Kondisi"><select name="items[{{ $index }}][condition]" class="form-select"><option value="good">Baik</option><option value="damaged">Rusak</option></select></td>
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

@push('styles')
<style>
@media (max-width: 767.98px) {
    .pos-return-entry-table, .pos-return-entry-table tbody, .pos-return-entry-table tr, .pos-return-entry-table td { display: block; width: 100%; }
    .pos-return-entry-table thead, .pos-return-entry-table td:first-child { display: none; }
    .pos-return-entry-table tr { border: 1px solid #e4e6ef; border-radius: .5rem; padding: .75rem; margin-bottom: .75rem; }
    .pos-return-entry-table td { border: 0; padding: .35rem 0; }
    .pos-return-entry-table td::before { content: attr(data-label); display: block; font-weight: 600; margin-bottom: .35rem; color: #3f4254; }
}
</style>
@endpush
