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
                    <thead>
                        <tr class="text-muted fw-bold text-uppercase fs-7">
                            <th>Produk</th>
                            <th>
                                Dipesan / Sebelumnya / Sisa
                                <i class="ki-outline ki-information-5 fs-7 text-gray-500" data-bs-toggle="tooltip" title="Sisa yang Belum Diterima adalah jumlah pada PO yang masih boleh diterima."></i>
                            </th>
                            <th>Jumlah Datang <i class="ki-outline ki-information-5 fs-7 text-gray-500" data-bs-toggle="tooltip" title="Jumlah barang yang benar-benar tiba dari supplier."></i></th>
                            <th>Diterima Baik <i class="ki-outline ki-information-5 fs-7 text-gray-500" data-bs-toggle="tooltip" title="Barang yang lolos pemeriksaan dan masuk stok normal. Dihitung otomatis oleh sistem."></i></th>
                            <th>Ditolak <i class="ki-outline ki-information-5 fs-7 text-gray-500" data-bs-toggle="tooltip" title="Barang yang ditolak dan tidak masuk stok."></i></th>
                            <th>Rusak <i class="ki-outline ki-information-5 fs-7 text-gray-500" data-bs-toggle="tooltip" title="Barang datang dalam keadaan rusak dan dicatat sebagai stok rusak."></i></th>
                            <th>Retur Supplier <i class="ki-outline ki-information-5 fs-7 text-gray-500" data-bs-toggle="tooltip" title="Barang yang langsung dikembalikan kepada supplier."></i></th>
                            <th>Total QC</th>
                            <th>Selisih QC</th>
                            <th>Lokasi</th>
                            <th>Batch</th>
                            <th>Alasan QC</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($selectedPo->items as $index => $item)
                        @php
                            $draftItem = $receiptItems->get($item->id);
                            $outstanding = $item->outstandingQuantity();
                        @endphp
                        <tr class="qc-row" data-outstanding="{{ qty_input($outstanding) }}">
                            <td>
                                <input type="hidden" name="items[{{ $index }}][purchase_order_item_id]" value="{{ $item->id }}">
                                <div class="fw-bold">{{ $item->product_sku_snapshot }}</div>
                                <div>{{ $item->product_name_snapshot }}</div>
                                <div class="text-muted">{{ $item->unit_name_snapshot }} x {{ qty($item->conversion_factor_snapshot) }}</div>
                            </td>
                            <td>{{ qty($item->quantity_ordered) }} / {{ qty($item->quantity_received) }} / <span class="fw-bold">{{ qty($outstanding) }}</span></td>
                            <td><input type="number" step="1" min="0" name="items[{{ $index }}][quantity_received]" value="{{ old("items.$index.quantity_received", qty_input($draftItem?->quantity_received ?? $outstanding)) }}" class="form-control form-control-sm qc-received"></td>
                            <td><input type="number" step="1" min="0" name="items[{{ $index }}][quantity_accepted]" value="{{ old("items.$index.quantity_accepted", qty_input($draftItem?->quantity_accepted ?? $outstanding)) }}" class="form-control form-control-sm qc-accepted bg-light-success" readonly></td>
                            <td><input type="number" step="1" min="0" name="items[{{ $index }}][quantity_rejected]" value="{{ old("items.$index.quantity_rejected", qty_input($draftItem?->quantity_rejected ?? 0)) }}" class="form-control form-control-sm qc-category"></td>
                            <td><input type="number" step="1" min="0" name="items[{{ $index }}][quantity_damaged]" value="{{ old("items.$index.quantity_damaged", qty_input($draftItem?->quantity_damaged ?? 0)) }}" class="form-control form-control-sm qc-category"></td>
                            <td><input type="number" step="1" min="0" name="items[{{ $index }}][quantity_returned_to_supplier]" value="{{ old("items.$index.quantity_returned_to_supplier", qty_input($draftItem?->quantity_returned_to_supplier ?? 0)) }}" class="form-control form-control-sm qc-category"></td>
                            <td><span class="fw-bold qc-total">0</span></td>
                            <td><span class="badge qc-status">Memeriksa...</span><div class="small mt-1 qc-difference"></div></td>
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
                <button name="action" value="post" class="btn btn-primary" id="goods-receipt-post" data-confirm="Posting receipt akan menambah stok dan memperbarui HPP. Lanjutkan?">Simpan & Posting</button>
            </div>
        </x-metronic.card>
    </form>
@else
    <x-metronic.card><x-metronic.empty-state title="Tidak ada PO siap diterima" description="Setujui atau kirim PO ke supplier terlebih dahulu." /></x-metronic.card>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = Array.from(document.querySelectorAll('.qc-row'));
    const postButton = document.getElementById('goods-receipt-post');

    const numberValue = (input) => {
        const value = Number.parseFloat(input?.value || '0');
        return Number.isFinite(value) && value >= 0 ? value : 0;
    };
    const displayNumber = (value) => Number.isInteger(value) ? String(value) : value.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');

    const calculateRow = (row) => {
        const received = numberValue(row.querySelector('.qc-received'));
        const categoryTotal = Array.from(row.querySelectorAll('.qc-category')).reduce((total, input) => total + numberValue(input), 0);
        const acceptedRaw = received - categoryTotal;
        const accepted = Math.max(0, acceptedRaw);
        const total = accepted + categoryTotal;
        const difference = received - total;
        const outstanding = Number.parseFloat(row.dataset.outstanding || '0');
        const valid = acceptedRaw >= 0 && Math.abs(difference) < 0.000001 && accepted <= outstanding;

        row.querySelector('.qc-accepted').value = displayNumber(accepted);
        row.querySelector('.qc-total').textContent = displayNumber(total);
        row.querySelector('.qc-difference').textContent = `Selisih: ${displayNumber(difference)}`;

        const status = row.querySelector('.qc-status');
        if (acceptedRaw < 0) {
            status.className = 'badge badge-light-danger qc-status';
            status.textContent = 'Kategori QC melebihi jumlah datang';
        } else if (accepted > outstanding) {
            status.className = 'badge badge-light-danger qc-status';
            status.textContent = 'Melebihi sisa PO';
        } else if (valid) {
            status.className = 'badge badge-light-success qc-status';
            status.textContent = 'QC sudah sesuai';
        } else {
            status.className = 'badge badge-light-danger qc-status';
            status.textContent = 'Pembagian QC belum sesuai';
        }

        row.dataset.qcValid = valid ? '1' : '0';
        return valid;
    };

    const refresh = () => {
        const rowResults = rows.map(calculateRow);
        const valid = rowResults.length > 0 && rowResults.every(Boolean);
        if (postButton) {
            postButton.disabled = !valid;
            postButton.title = valid ? '' : 'Perbaiki pembagian QC sebelum posting.';
        }
    };

    rows.forEach((row) => row.querySelectorAll('.qc-received, .qc-category').forEach((input) => input.addEventListener('input', refresh)));
    refresh();
});
</script>
@endpush
