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
@isset($method)
    @method($method)
@endisset

@if ($purchaseRequest)
    <div class="alert alert-light-primary d-flex align-items-start mb-5">
        <i class="ki-outline ki-document fs-2 text-primary me-3"></i>
        <div>
            <div class="fw-bold">Referensi Permintaan Pembelian</div>
            <div class="fs-7">PO ini berasal dari <a href="{{ route('purchasing.requests.show', $purchaseRequest) }}" class="fw-semibold">{{ $purchaseRequest->number }}</a>. Referensi ini bersifat read-only; alasan dan prioritas PR tidak menjadi field PO.</div>
        </div>
    </div>
    <input type="hidden" name="purchase_request_id" value="{{ $purchaseRequest->id }}">
@endif

<div class="mb-6">
    <h3 class="fs-5 fw-bold mb-1">Informasi Dokumen dan Tujuan</h3>
    <div class="text-muted fs-7 mb-4">Tentukan supplier, gudang penerima, tanggal, dan ketentuan pembelian.</div>
    <div class="row g-5">
        <div class="col-md-6">
            <x-metronic.form-group name="supplier_id" label="Supplier" required help="Pemasok aktif yang akan menerima pesanan pembelian ini.">
                <select name="supplier_id" class="form-select form-select-solid" required>
                    <option value="">Pilih supplier</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(old('supplier_id', $purchaseOrder->supplier_id) == $supplier->id)>{{ $supplier->code }} - {{ $supplier->name }} (termin {{ $supplier->payment_term_days }} hari)</option>
                    @endforeach
                </select>
            </x-metronic.form-group>
        </div>
        <div class="col-md-6">
            <x-metronic.form-group name="warehouse_id" label="Gudang Tujuan" required help="Gudang dalam akses lokasi kerja Anda yang akan menerima barang dari supplier.">
                <select name="warehouse_id" class="form-select form-select-solid" required>
                    <option value="">Pilih gudang tujuan</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $purchaseOrder->warehouse_id ?: $purchaseRequest?->warehouse_id) == $warehouse->id)>{{ $warehouse->code }} - {{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </x-metronic.form-group>
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-metronic.form-group name="order_date" label="Tanggal Pesanan" required help="Tanggal resmi Purchase Order dibuat.">
                <input type="date" name="order_date" value="{{ old('order_date', optional($purchaseOrder->order_date)->format('Y-m-d') ?: now()->toDateString()) }}" class="form-control form-control-solid" required>
            </x-metronic.form-group>
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-metronic.form-group name="expected_at" label="Perkiraan Tanggal Tiba" help="Perkiraan tanggal barang sampai di gudang tujuan.">
                <input type="date" name="expected_at" value="{{ old('expected_at', optional($purchaseOrder->expected_at)->format('Y-m-d')) }}" class="form-control form-control-solid">
            </x-metronic.form-group>
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-metronic.form-group name="payment_term_days" label="Termin Pembayaran (Hari)" help="Jumlah hari yang diberikan untuk membayar supplier sesuai kesepakatan.">
                <input type="number" name="payment_term_days" value="{{ old('payment_term_days', $purchaseOrder->payment_term_days ?? 0) }}" min="0" max="365" class="form-control form-control-solid">
            </x-metronic.form-group>
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-metronic.form-group name="notes" label="Catatan PO" help="Informasi tambahan untuk dokumen pembelian atau supplier.">
                <input name="notes" value="{{ old('notes', $purchaseOrder->notes) }}" class="form-control form-control-solid">
            </x-metronic.form-group>
        </div>
    </div>
</div>

<div class="separator mb-6"></div>

<div class="mb-6">
    <h3 class="fs-5 fw-bold mb-1">Item Purchase Order</h3>
    <div class="text-muted fs-7 mb-4">Harga, diskon, dan pajak diisi per item. Satuan pembelian harus terdaftar pada produk.</div>
    <div class="table-responsive">
        <table class="table table-row-dashed align-middle mb-0" id="po-items-table">
            <thead>
                <tr class="text-muted fw-bold text-uppercase fs-7"><th class="min-w-200px">Produk</th><th class="min-w-130px">Satuan Beli</th><th class="min-w-100px">Jumlah Dipesan</th><th class="min-w-120px">Harga Satuan</th><th class="min-w-110px">Diskon Item</th><th class="min-w-110px">Pajak Item</th><th class="min-w-120px text-end">Subtotal</th></tr>
            </thead>
            <tbody>
                @foreach ($existingItems as $i => $item)
                    <tr>
                        <td>
                            <select name="items[{{ $i }}][product_id]" class="form-select form-select-solid" required aria-label="Produk item {{ $i + 1 }}">
                                <option value="">Pilih produk</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" @selected(($item['product_id'] ?? '') == $product->id)>{{ $product->sku }} - {{ $product->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select name="items[{{ $i }}][unit_id]" class="form-select form-select-solid" required aria-label="Satuan pembelian item {{ $i + 1 }}">
                                <option value="">Pilih satuan</option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->id }}" @selected(($item['unit_id'] ?? '') == $unit->id)>{{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input name="items[{{ $i }}][quantity_ordered]" value="{{ qty_input($item['quantity_ordered'] ?? 1) }}" type="number" step="0.0001" min="0.0001" class="form-control form-control-solid po-calc po-qty" required aria-label="Jumlah dipesan item {{ $i + 1 }}"></td>
                        <td><input name="items[{{ $i }}][unit_price]" value="{{ $item['unit_price'] ?? 0 }}" type="number" step="0.01" min="0" class="form-control form-control-solid po-calc po-price" required aria-label="Harga satuan item {{ $i + 1 }}"></td>
                        <td><input name="items[{{ $i }}][discount_amount]" value="{{ $item['discount_amount'] ?? 0 }}" type="number" step="0.01" min="0" class="form-control form-control-solid po-calc po-discount" aria-label="Diskon item {{ $i + 1 }}"></td>
                        <td><input name="items[{{ $i }}][tax_amount]" value="{{ $item['tax_amount'] ?? 0 }}" type="number" step="0.01" min="0" class="form-control form-control-solid po-calc po-tax" aria-label="Pajak item {{ $i + 1 }}"></td>
                        <td class="fw-bold text-end po-line-subtotal">Rp 0</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="separator mb-6"></div>

<div>
    <h3 class="fs-5 fw-bold mb-1">Nilai Purchase Order</h3>
    <div class="text-muted fs-7 mb-4">Biaya berikut berlaku untuk keseluruhan dokumen, bukan per item.</div>
    <div class="row g-5 align-items-end">
        <div class="col-sm-6 col-lg-3"><x-metronic.form-group name="header_discount" label="Diskon Dokumen" help="Diskon nominal untuk keseluruhan PO."><input name="header_discount" value="{{ old('header_discount', $purchaseOrder->header_discount ?? 0) }}" type="number" step="0.01" min="0" class="form-control form-control-solid po-total-input"></x-metronic.form-group></div>
        <div class="col-sm-6 col-lg-3"><x-metronic.form-group name="freight_cost" label="Biaya Pengiriman" help="Ongkos pengiriman barang dari supplier ke gudang."><input name="freight_cost" value="{{ old('freight_cost', $purchaseOrder->freight_cost ?? 0) }}" type="number" step="0.01" min="0" class="form-control form-control-solid po-total-input"></x-metronic.form-group></div>
        <div class="col-sm-6 col-lg-3"><x-metronic.form-group name="additional_cost" label="Biaya Tambahan" help="Biaya dokumen lain di luar harga item dan pengiriman."><input name="additional_cost" value="{{ old('additional_cost', $purchaseOrder->additional_cost ?? 0) }}" type="number" step="0.01" min="0" class="form-control form-control-solid po-total-input"></x-metronic.form-group></div>
        <div class="col-sm-6 col-lg-3"><div class="border rounded p-4 mb-5"><div class="text-muted fs-8">PERKIRAAN GRAND TOTAL</div><div class="fs-3 fw-bold text-primary" id="po-grand-total">Rp 0</div></div></div>
    </div>
</div>

<div class="mt-5 d-flex flex-wrap gap-3">
    <button class="btn btn-primary"><i class="ki-outline ki-check fs-5 me-2"></i>Simpan Draft</button>
    <a href="{{ $purchaseOrder->exists ? route('purchasing.purchase-orders.show', $purchaseOrder) : route('purchasing.purchase-orders.index') }}" class="btn btn-light">Batal</a>
</div>

@push('scripts')
    <script>
        document.addEventListener('input', function (event) {
            if (!event.target.classList.contains('po-calc') && !event.target.classList.contains('po-total-input')) return;
            window.calculatePurchaseOrderTotal();
        });

        window.calculatePurchaseOrderTotal = function () {
            let subtotal = 0;
            document.querySelectorAll('#po-items-table tbody tr').forEach(function (row) {
                const quantity = parseFloat(row.querySelector('.po-qty')?.value || 0);
                const price = parseFloat(row.querySelector('.po-price')?.value || 0);
                const discount = parseFloat(row.querySelector('.po-discount')?.value || 0);
                const tax = parseFloat(row.querySelector('.po-tax')?.value || 0);
                const lineTotal = Math.max((quantity * price) - discount + tax, 0);
                row.querySelector('.po-line-subtotal').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(lineTotal);
                subtotal += lineTotal;
            });

            const documentDiscount = parseFloat(document.querySelector('[name="header_discount"]')?.value || 0);
            const freight = parseFloat(document.querySelector('[name="freight_cost"]')?.value || 0);
            const additional = parseFloat(document.querySelector('[name="additional_cost"]')?.value || 0);
            const total = Math.max(subtotal - documentDiscount + freight + additional, 0);
            document.getElementById('po-grand-total').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(total);
        };

        window.calculatePurchaseOrderTotal();
    </script>
@endpush
