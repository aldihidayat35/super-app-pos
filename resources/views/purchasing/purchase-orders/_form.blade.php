@php
    $existingItems = old('items');
    if ($existingItems === null && $purchaseOrder->exists) {
        $existingItems = $purchaseOrder->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'unit_id' => $item->unit_id,
            'quantity_ordered' => $item->quantity_ordered,
            'unit_price' => $item->unit_price,
            'discount_amount' => $item->discount_amount,
            'tax_amount' => $item->tax_amount,
        ])->values()->all();
    }
    if ($existingItems === null && $purchaseRequest) {
        $existingItems = $purchaseRequest->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'unit_id' => $item->unit_id ?: $item->product?->base_unit_id,
            'quantity_ordered' => $item->quantity,
            'unit_price' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
        ])->values()->all();
    }
    $existingItems = $existingItems ?: [['product_id' => '', 'unit_id' => '', 'quantity_ordered' => 1, 'unit_price' => 0, 'discount_amount' => 0, 'tax_amount' => 0]];
@endphp
@csrf
@isset($method) @method($method) @endisset
<div class="row g-5">
    <div class="col-md-4"><x-metronic.form-group name="warehouse_id" label="Gudang"><select name="warehouse_id" class="form-select form-select-solid" required><option value="">Pilih gudang</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $purchaseOrder->warehouse_id) == $warehouse->id)>{{ $warehouse->code }} — {{ $warehouse->name }}</option>@endforeach</select></x-metronic.form-group></div>
    <div class="col-md-4"><x-metronic.form-group name="supplier_id" label="Supplier"><select name="supplier_id" class="form-select form-select-solid" required><option value="">Pilih supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id', $purchaseOrder->supplier_id) == $supplier->id)>{{ $supplier->code }} — {{ $supplier->name }}</option>@endforeach</select></x-metronic.form-group></div>
    <div class="col-md-2"><x-metronic.form-group name="order_date" label="Tanggal"><input type="date" name="order_date" value="{{ old('order_date', optional($purchaseOrder->order_date)->format('Y-m-d') ?: now()->toDateString()) }}" class="form-control form-control-solid" required></x-metronic.form-group></div>
    <div class="col-md-2"><x-metronic.form-group name="expected_at" label="ETA"><input type="date" name="expected_at" value="{{ old('expected_at', optional($purchaseOrder->expected_at)->format('Y-m-d')) }}" class="form-control form-control-solid"></x-metronic.form-group></div>
    <div class="col-md-2"><x-metronic.form-group name="payment_term_days" label="Termin Hari"><input type="number" name="payment_term_days" value="{{ old('payment_term_days', $purchaseOrder->payment_term_days ?? 0) }}" min="0" class="form-control form-control-solid"></x-metronic.form-group></div>
    <div class="col-md-10"><x-metronic.form-group name="notes" label="Catatan"><input name="notes" value="{{ old('notes', $purchaseOrder->notes) }}" class="form-control form-control-solid"></x-metronic.form-group></div>
</div>
@if($purchaseRequest)<input type="hidden" name="purchase_request_id" value="{{ $purchaseRequest->id }}">@endif

<div class="table-responsive mt-5">
    <table class="table table-row-dashed align-middle" id="po-items-table">
        <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Unit Beli</th><th>Qty</th><th>Harga</th><th>Diskon</th><th>Pajak</th><th>Subtotal</th></tr></thead>
        <tbody>
        @foreach($existingItems as $i => $item)
            <tr>
                <td><select name="items[{{ $i }}][product_id]" class="form-select form-select-solid" required><option value="">Produk</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(($item['product_id'] ?? '') == $product->id)>{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></td>
                <td><select name="items[{{ $i }}][unit_id]" class="form-select form-select-solid" required><option value="">Unit</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected(($item['unit_id'] ?? '') == $unit->id)>{{ $unit->name }}</option>@endforeach</select></td>
                <td><input name="items[{{ $i }}][quantity_ordered]" value="{{ qty_input($item['quantity_ordered'] ?? 1) }}" type="number" step="1" min="1" class="form-control form-control-solid po-calc po-qty" required></td>
                <td><input name="items[{{ $i }}][unit_price]" value="{{ $item['unit_price'] ?? 0 }}" type="number" step="0.01" min="0" class="form-control form-control-solid po-calc po-price" required></td>
                <td><input name="items[{{ $i }}][discount_amount]" value="{{ $item['discount_amount'] ?? 0 }}" type="number" step="0.01" min="0" class="form-control form-control-solid po-calc po-discount"></td>
                <td><input name="items[{{ $i }}][tax_amount]" value="{{ $item['tax_amount'] ?? 0 }}" type="number" step="0.01" min="0" class="form-control form-control-solid po-calc po-tax"></td>
                <td class="fw-bold po-line-subtotal">0</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="row g-5 justify-content-end">
    <div class="col-md-3"><x-metronic.form-group name="header_discount" label="Diskon Header"><input name="header_discount" value="{{ old('header_discount', $purchaseOrder->header_discount ?? 0) }}" type="number" step="0.01" min="0" class="form-control form-control-solid po-total-input"></x-metronic.form-group></div>
    <div class="col-md-3"><x-metronic.form-group name="freight_cost" label="Ongkir"><input name="freight_cost" value="{{ old('freight_cost', $purchaseOrder->freight_cost ?? 0) }}" type="number" step="0.01" min="0" class="form-control form-control-solid po-total-input"></x-metronic.form-group></div>
    <div class="col-md-3"><x-metronic.form-group name="additional_cost" label="Biaya Tambahan"><input name="additional_cost" value="{{ old('additional_cost', $purchaseOrder->additional_cost ?? 0) }}" type="number" step="0.01" min="0" class="form-control form-control-solid po-total-input"></x-metronic.form-group></div>
    <div class="col-md-3"><div class="p-5 bg-light rounded"><div class="text-muted">Total Akhir</div><div class="fs-2 fw-bold" id="po-grand-total">Rp 0</div></div></div>
</div>
<div class="mt-5 d-flex gap-3"><button class="btn btn-primary">Simpan Draft</button><a href="{{ route('purchasing.purchase-orders.index') }}" class="btn btn-light">Batal</a></div>

@push('scripts')
<script>
document.addEventListener('input', function (event) {
    if (!event.target.classList.contains('po-calc') && !event.target.classList.contains('po-total-input')) return;
    window.calculatePurchaseOrderTotal();
});
window.calculatePurchaseOrderTotal = function () {
    let subtotal = 0;
    document.querySelectorAll('#po-items-table tbody tr').forEach(function(row) {
        const qty = parseFloat(row.querySelector('.po-qty')?.value || 0);
        const price = parseFloat(row.querySelector('.po-price')?.value || 0);
        const discount = parseFloat(row.querySelector('.po-discount')?.value || 0);
        const tax = parseFloat(row.querySelector('.po-tax')?.value || 0);
        const line = Math.max((qty * price) - discount + tax, 0);
        row.querySelector('.po-line-subtotal').innerText = new Intl.NumberFormat('id-ID').format(line);
        subtotal += line;
    });
    const headerDiscount = parseFloat(document.querySelector('[name="header_discount"]')?.value || 0);
    const freight = parseFloat(document.querySelector('[name="freight_cost"]')?.value || 0);
    const additional = parseFloat(document.querySelector('[name="additional_cost"]')?.value || 0);
    const total = Math.max(subtotal - headerDiscount + freight + additional, 0);
    document.getElementById('po-grand-total').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(total);
};
window.calculatePurchaseOrderTotal();
</script>
@endpush
