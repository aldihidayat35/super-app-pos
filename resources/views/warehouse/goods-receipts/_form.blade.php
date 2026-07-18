@php
    $receiptItems = $receipt->exists ? $receipt->items->keyBy('purchase_order_item_id') : collect();
@endphp

<form method="GET" action="{{ route('warehouse.goods-receipts.create') }}" class="mb-5">
    <x-metronic.card title="Pilih Purchase Order">
        <div class="row g-3 align-items-end">
            <div class="col-md-10">
                <label class="form-label">PO siap diterima</label>
                <select name="purchase_order_id" class="form-select form-select-solid">
                    @foreach($purchaseOrders as $po)
                        <option value="{{ $po->id }}" @selected($selectedPo?->id === $po->id)>{{ $po->number }} — {{ $po->supplier?->name }} — {{ $po->warehouse?->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Tampilkan</button></div>
        </div>
    </x-metronic.card>
</form>

@if($selectedPo)
    <form method="POST" action="{{ $action }}" enctype="multipart/form-data">
        @csrf
        @if(($method ?? 'POST') !== 'POST') @method($method) @endif
        <input type="hidden" name="purchase_order_id" value="{{ $selectedPo->id }}">

        <x-metronic.card title="Header Receipt">
            <div class="row g-4">
                <div class="col-md-3"><label class="form-label">PO</label><input class="form-control form-control-solid" value="{{ $selectedPo->number }}" readonly></div>
                <div class="col-md-3"><label class="form-label">Supplier</label><input class="form-control form-control-solid" value="{{ $selectedPo->supplier?->name }}" readonly></div>
                <div class="col-md-3"><label class="form-label">Gudang</label><input class="form-control form-control-solid" value="{{ $selectedPo->warehouse?->name }}" readonly></div>
                <div class="col-md-3"><label class="form-label required">Tanggal Datang</label><input type="date" name="received_at" value="{{ old('received_at', optional($receipt->received_at)->format('Y-m-d') ?: now()->toDateString()) }}" class="form-control @error('received_at') is-invalid @enderror" required>@error('received_at')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label">Nomor Surat Jalan</label><input name="delivery_note_number" value="{{ old('delivery_note_number', $receipt->delivery_note_number) }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Ongkir Aktual</label><input type="number" step="0.01" min="0" name="actual_freight_cost" value="{{ old('actual_freight_cost', $receipt->actual_freight_cost ?? 0) }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Biaya Tambahan Aktual</label><input type="number" step="0.01" min="0" name="actual_additional_cost" value="{{ old('actual_additional_cost', $receipt->actual_additional_cost ?? 0) }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Foto/Bukti</label><input type="file" name="proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></div>
                <div class="col-md-6"><label class="form-label">Catatan</label><input name="notes" value="{{ old('notes', $receipt->notes) }}" class="form-control"></div>
            </div>
        </x-metronic.card>

        <x-metronic.card title="Item Penerimaan dan Quality Control" class="mt-5">
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle">
                    <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Ordered / Prev / Outstanding</th><th>Qty Datang</th><th>Accepted</th><th>Rejected</th><th>Damaged</th><th>Retur Supplier</th><th>Lokasi</th><th>Batch</th><th>Alasan QC</th></tr></thead>
                    <tbody>
                    @foreach($selectedPo->items as $index => $item)
                        @php
                            $draftItem = $receiptItems->get($item->id);
                            $outstanding = $item->outstandingQuantity();
                        @endphp
                        <tr>
                            <td>
                                <input type="hidden" name="items[{{ $index }}][purchase_order_item_id]" value="{{ $item->id }}">
                                <div class="fw-bold">{{ $item->product_sku_snapshot }}</div>
                                <div>{{ $item->product_name_snapshot }}</div>
                                <div class="text-muted">{{ $item->unit_name_snapshot }} x {{ qty($item->conversion_factor_snapshot) }}</div>
                            </td>
                            <td>{{ qty($item->quantity_ordered) }} / {{ qty($item->quantity_received) }} / <span class="fw-bold">{{ $outstanding }}</span></td>
                            <td><input type="number" step="1" min="0" name="items[{{ $index }}][quantity_received]" value="{{ old("items.$index.quantity_received", qty_input($draftItem?->quantity_received ?? $outstanding)) }}" class="form-control form-control-sm"></td>
                            <td><input type="number" step="1" min="0" name="items[{{ $index }}][quantity_accepted]" value="{{ old("items.$index.quantity_accepted", qty_input($draftItem?->quantity_accepted ?? $outstanding)) }}" class="form-control form-control-sm"></td>
                            <td><input type="number" step="1" min="0" name="items[{{ $index }}][quantity_rejected]" value="{{ old("items.$index.quantity_rejected", qty_input($draftItem?->quantity_rejected ?? 0)) }}" class="form-control form-control-sm"></td>
                            <td><input type="number" step="1" min="0" name="items[{{ $index }}][quantity_damaged]" value="{{ old("items.$index.quantity_damaged", qty_input($draftItem?->quantity_damaged ?? 0)) }}" class="form-control form-control-sm"></td>
                            <td><input type="number" step="1" min="0" name="items[{{ $index }}][quantity_returned_to_supplier]" value="{{ old("items.$index.quantity_returned_to_supplier", qty_input($draftItem?->quantity_returned_to_supplier ?? 0)) }}" class="form-control form-control-sm"></td>
                            <td><select name="items[{{ $index }}][warehouse_location_id]" class="form-select form-select-sm"><option value="">Default gudang</option>@foreach($warehouseLocations as $location)<option value="{{ $location->id }}" @selected(old("items.$index.warehouse_location_id", $draftItem?->warehouse_location_id) == $location->id)>{{ $location->full_code }}</option>@endforeach</select></td>
                            <td><input name="items[{{ $index }}][batch_no]" value="{{ old("items.$index.batch_no", $draftItem?->batch_no) }}" class="form-control form-control-sm"></td>
                            <td><input name="items[{{ $index }}][qc_notes]" value="{{ old("items.$index.qc_notes", $draftItem?->qc_notes) }}" class="form-control form-control-sm"></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($errors->any())<div class="alert alert-danger mt-4">Periksa kembali form penerimaan. Total QC harus sama dengan qty datang dan accepted tidak boleh melebihi outstanding.</div>@endif
            <div class="d-flex justify-content-end gap-3">
                <button name="action" value="draft" class="btn btn-light">Simpan Draft</button>
                <button name="action" value="post" class="btn btn-primary" data-confirm="Posting receipt akan menambah stok dan memperbarui HPP. Lanjutkan?">Simpan & Posting</button>
            </div>
        </x-metronic.card>
    </form>
@else
    <x-metronic.card><x-metronic.empty-state title="Tidak ada PO siap diterima" description="Setujui atau kirim PO ke supplier terlebih dahulu." /></x-metronic.card>
@endif
