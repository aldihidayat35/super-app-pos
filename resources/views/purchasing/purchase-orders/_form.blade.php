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
            'unit_price' => $item->product?->cost_price ?: 0,
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
    <div class="alert alert-light-primary d-flex align-items-center mb-6 border border-primary-subtle p-4">
        <i class="ki-outline ki-document fs-2x text-primary me-3"></i>
        <div class="flex-grow-1">
            <div class="fw-bold text-gray-900 fs-6">Referensi Pengajuan Belanja Internal</div>
            <div class="text-muted fs-7">PO ini dibuat berdasarkan pengajuan resmi <a href="{{ route('purchasing.requests.show', $purchaseRequest) }}" class="fw-bold text-primary">{{ $purchaseRequest->number }}</a>.</div>
        </div>
        <span class="badge badge-light-primary fw-semibold">Terkonversi</span>
    </div>
    <input type="hidden" name="purchase_request_id" value="{{ $purchaseRequest->id }}">
@endif

{{-- 1. IDENTITAS REKANAN & PENGIRIMAN --}}
<div class="card card-bordered mb-6 bg-light-subtle">
    <div class="card-header border-bottom border-gray-200 py-3">
        <div class="card-title">
            <i class="ki-outline ki-shop fs-4 text-primary me-2"></i>
            <span class="fs-6 fw-bold">1. Informasi Rekanan Supplier & Lokasi Pengiriman</span>
        </div>
    </div>
    <div class="card-body p-5">
        <div class="row g-5">
            <div class="col-md-6">
                <x-metronic.form-group name="supplier_id" label="Supplier Rekanan" required tooltip="Pemasok aktif yang akan menerima pesanan pembelian ini.">
                    <select name="supplier_id" id="po-supplier-select" class="form-select form-select-solid" required>
                        <option value="">-- Pilih rekanan supplier --</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" 
                                    data-term="{{ $supplier->payment_term_days }}"
                                    @selected(old('supplier_id', $purchaseOrder->supplier_id) == $supplier->id)>
                                {{ $supplier->code }} - {{ $supplier->name }} (Termin {{ $supplier->payment_term_days }} hari)
                            </option>
                        @endforeach
                    </select>
                </x-metronic.form-group>
            </div>

            <div class="col-md-6">
                @php
                    $defaultDestinationId = $purchaseOrder->destination_work_location_id
                        ?: $purchaseOrder->warehouse?->work_location_id
                        ?: $purchaseRequest?->warehouse?->work_location_id;
                @endphp
                <x-metronic.form-group name="destination_work_location_id" label="Lokasi Tujuan Pengiriman" required tooltip="Pilih gudang utama atau cabang toko yang akan menerima fisik barang langsung dari supplier.">
                    <select name="destination_work_location_id" class="form-select form-select-solid" required>
                        <option value="">-- Pilih lokasi penerima fisik --</option>
                        @foreach ($destinations as $destination)
                            <option value="{{ $destination->id }}" @selected(old('destination_work_location_id', $defaultDestinationId) == $destination->id)>
                                {{ $destination->typeLabel() }} - {{ $destination->code }} - {{ $destination->name }}
                            </option>
                        @endforeach
                    </select>
                </x-metronic.form-group>
            </div>

            <div class="col-sm-6 col-lg-3">
                <x-metronic.form-group name="order_date" label="Tanggal Pesanan" required tooltip="Tanggal resmi surat pesanan diterbitkan.">
                    <input type="date" name="order_date" value="{{ old('order_date', optional($purchaseOrder->order_date)->format('Y-m-d') ?: now()->toDateString()) }}" class="form-control form-control-solid" required>
                </x-metronic.form-group>
            </div>

            <div class="col-sm-6 col-lg-3">
                <x-metronic.form-group name="expected_at" label="Estimasi Tiba (ETA)" tooltip="Perkiraan tanggal barang sampai di lokasi penerima.">
                    <input type="date" name="expected_at" value="{{ old('expected_at', optional($purchaseOrder->expected_at)->format('Y-m-d')) }}" class="form-control form-control-solid">
                </x-metronic.form-group>
            </div>

            <div class="col-sm-6 col-lg-3">
                <x-metronic.form-group name="payment_term_days" label="Termin Pembayaran (Hari)" tooltip="Jangka waktu pembayaran jatuh tempo ke supplier (0 untuk tunai).">
                    <div class="input-group input-group-solid">
                        <input type="number" id="po-term-days" name="payment_term_days" value="{{ old('payment_term_days', $purchaseOrder->payment_term_days ?? 0) }}" min="0" max="365" class="form-control form-control-solid text-end">
                        <span class="input-group-text">Hari</span>
                    </div>
                </x-metronic.form-group>
            </div>

            <div class="col-sm-6 col-lg-3">
                <x-metronic.form-group name="notes" label="Catatan Tambahan" tooltip="Instruksi khusus kepada supplier atau catatan dokumen intern.">
                    <input name="notes" value="{{ old('notes', $purchaseOrder->notes) }}" class="form-control form-control-solid" placeholder="Contoh: Kirim via ekspedisi langganan">
                </x-metronic.form-group>
            </div>
        </div>
    </div>
</div>

{{-- 2. RINCIAN BARANG YANG DIPESAN --}}
<div class="card card-bordered mb-6">
    <div class="card-header border-bottom border-gray-200 py-3">
        <div class="card-title">
            <i class="ki-outline ki-basket fs-4 text-primary me-2"></i>
            <span class="fs-6 fw-bold">2. Rincian Barang yang Dipesan</span>
            <span class="badge badge-light-primary ms-2" id="po-items-counter">{{ count($existingItems) }} item</span>
        </div>
        <div class="card-toolbar">
            <button type="button" class="btn btn-sm btn-light-primary" id="btn-add-po-row">
                <i class="ki-outline ki-plus fs-6 me-1"></i> Tambah Baris Barang
            </button>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-200 align-middle mb-0 gs-4 gy-3" id="po-items-table">
                <thead>
                    <tr class="text-muted fw-bold text-uppercase fs-8 bg-light">
                        <th class="w-40px ps-4">#</th>
                        <th class="min-w-260px">Produk</th>
                        <th class="min-w-130px">Satuan Beli</th>
                        <th class="min-w-100px text-end">Qty Order</th>
                        <th class="min-w-130px text-end">Harga Satuan <i class="ki-outline ki-information-5 text-gray-500 fs-8 ms-1 cursor-pointer" data-bs-toggle="tooltip" title="Harga beli per satuan sebelum diskon & pajak."></i></th>
                        <th class="min-w-100px text-end">Diskon Item <i class="ki-outline ki-information-5 text-gray-500 fs-8 ms-1 cursor-pointer" data-bs-toggle="tooltip" title="Potongan diskon nominal untuk baris barang ini."></i></th>
                        <th class="min-w-100px text-end">Pajak Item <i class="ki-outline ki-information-5 text-gray-500 fs-8 ms-1 cursor-pointer" data-bs-toggle="tooltip" title="Nominal pajak/PPN untuk baris barang ini."></i></th>
                        <th class="min-w-130px text-end">Subtotal</th>
                        <th class="w-50px text-end pe-4">Hapus</th>
                    </tr>
                </thead>
                <tbody id="po-items-body">
                    @foreach ($existingItems as $i => $item)
                        <tr class="po-item-row" data-index="{{ $i }}">
                            <td class="ps-4 text-muted fw-semibold po-row-num">
                                {{ $i + 1 }}
                            </td>
                            <td>
                                <div class="d-flex gap-2 align-items-center">
                                    <div class="flex-grow-1">
                                        <select name="items[{{ $i }}][product_id]" class="form-select form-select-sm form-select-solid po-product-select" required aria-label="Produk item {{ $i + 1 }}">
                                            <option value="">-- Pilih produk pesanan --</option>
                                            @foreach ($products as $product)
                                                @php
                                                    $prodSuppliers = $product->supplierProducts->mapWithKeys(fn ($sp) => [$sp->supplier_id => (float) $sp->last_price]);
                                                @endphp
                                                <option value="{{ $product->id }}" 
                                                        data-sku="{{ $product->sku }}" 
                                                        data-name="{{ $product->name }}" 
                                                        data-category="{{ $product->category?->name ?? 'Tanpa Kategori' }}" 
                                                        data-image="{{ $product->main_image_url }}"
                                                        data-base-unit="{{ $product->base_unit_id }}"
                                                        data-cost="{{ (float) $product->cost_price }}"
                                                        data-supplier-prices="{{ e(json_encode($prodSuppliers)) }}"
                                                        @selected(($item['product_id'] ?? '') == $product->id)>
                                                    {{ $product->sku }} - {{ $product->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-icon btn-sm btn-light-primary btn-catalog flex-shrink-0" data-bs-toggle="modal" data-bs-target="#visualCatalogModal" data-row-index="{{ $i }}" title="Pilih dari Katalog Visual">
                                        <i class="ki-outline ki-element-11 fs-6"></i>
                                    </button>
                                </div>
                                <div class="last-price-info text-muted fs-8 mt-1"></div>
                            </td>
                            <td>
                                <select name="items[{{ $i }}][unit_id]" class="form-select form-select-sm form-select-solid po-unit-select" required aria-label="Satuan pembelian item {{ $i + 1 }}">
                                    <option value="">Satuan</option>
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->id }}" @selected(($item['unit_id'] ?? '') == $unit->id)>{{ $unit->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input name="items[{{ $i }}][quantity_ordered]" value="{{ qty_input($item['quantity_ordered'] ?? 1) }}" type="number" step="any" min="0.0001" class="form-control form-control-sm form-control-solid po-calc po-qty text-end" required aria-label="Jumlah dipesan item {{ $i + 1 }}">
                            </td>
                            <td>
                                <input name="items[{{ $i }}][unit_price]" value="{{ $item['unit_price'] ?? 0 }}" type="number" step="0.01" min="0" class="form-control form-control-sm form-control-solid po-calc po-price text-end" required aria-label="Harga satuan item {{ $i + 1 }}">
                            </td>
                            <td>
                                <input name="items[{{ $i }}][discount_amount]" value="{{ $item['discount_amount'] ?? 0 }}" type="number" step="0.01" min="0" class="form-control form-control-sm form-control-solid po-calc po-discount text-end" aria-label="Diskon item {{ $i + 1 }}">
                            </td>
                            <td>
                                <input name="items[{{ $i }}][tax_amount]" value="{{ $item['tax_amount'] ?? 0 }}" type="number" step="0.01" min="0" class="form-control form-control-sm form-control-solid po-calc po-tax text-end" aria-label="Pajak item {{ $i + 1 }}">
                            </td>
                            <td class="fw-bolder text-end po-line-subtotal text-gray-800 fs-7">Rp 0</td>
                            <td class="text-end pe-4">
                                <button type="button" class="btn btn-icon btn-sm btn-light-danger btn-remove-po-row" title="Hapus Baris">
                                    <i class="ki-outline ki-trash fs-6"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-3 bg-light-subtle border-top text-center">
            <button type="button" class="btn btn-sm btn-link text-primary fw-semibold" id="btn-add-po-row-bottom">
                <i class="ki-outline ki-plus fs-6 me-1"></i> Tambah Baris Barang Baru
            </button>
        </div>
    </div>
</div>

{{-- 3. RINCIAN NILAI & TOTAL PESANAN --}}
<div class="row g-6 mb-6">
    <div class="col-lg-6">
        <div class="card card-bordered h-100 bg-light-subtle">
            <div class="card-header border-bottom border-gray-200 py-3">
                <div class="card-title">
                    <i class="ki-outline ki-bill fs-4 text-primary me-2"></i>
                    <span class="fs-6 fw-bold">3. Penyesuaian Biaya Dokumen</span>
                </div>
            </div>
            <div class="card-body p-5">
                <div class="row g-4">
                    <div class="col-md-12">
                        <x-metronic.form-group name="header_discount" label="Diskon Nota Keseluruhan (Rp)" tooltip="Potongan diskon nominal untuk keseluruhan PO.">
                            <div class="input-group input-group-solid">
                                <span class="input-group-text">Rp</span>
                                <input name="header_discount" value="{{ old('header_discount', $purchaseOrder->header_discount ?? 0) }}" type="number" step="0.01" min="0" class="form-control form-control-solid po-total-input text-end">
                            </div>
                        </x-metronic.form-group>
                    </div>

                    <div class="col-md-6">
                        <x-metronic.form-group name="freight_cost" label="Ongkos Pengiriman (Rp)" tooltip="Biaya pengiriman barang dari lokasi supplier ke gudang/toko.">
                            <div class="input-group input-group-solid">
                                <span class="input-group-text">Rp</span>
                                <input name="freight_cost" value="{{ old('freight_cost', $purchaseOrder->freight_cost ?? 0) }}" type="number" step="0.01" min="0" class="form-control form-control-solid po-total-input text-end">
                            </div>
                        </x-metronic.form-group>
                    </div>

                    <div class="col-md-6">
                        <x-metronic.form-group name="additional_cost" label="Biaya Lainnya (Rp)" tooltip="Biaya bongkar muat, asuransi, atau administrasi dokumen.">
                            <div class="input-group input-group-solid">
                                <span class="input-group-text">Rp</span>
                                <input name="additional_cost" value="{{ old('additional_cost', $purchaseOrder->additional_cost ?? 0) }}" type="number" step="0.01" min="0" class="form-control form-control-solid po-total-input text-end">
                            </div>
                        </x-metronic.form-group>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card card-bordered h-100 bg-light-primary border-primary border-opacity-25 shadow-sm">
            <div class="card-header border-bottom border-primary border-opacity-25 py-3">
                <div class="card-title">
                    <i class="ki-outline ki-calculator fs-4 text-primary me-2"></i>
                    <span class="fs-6 fw-bold text-primary">Ringkasan Finansial Pesanan</span>
                </div>
            </div>
            <div class="card-body p-5 d-flex flex-column justify-content-between">
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-gray-600 fs-7">Subtotal Nilai Barang:</span>
                        <span class="fw-bold text-gray-800" id="po-summary-subtotal">Rp 0</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-gray-600 fs-7">Potongan Diskon Nota:</span>
                        <span class="fw-bold text-danger" id="po-summary-discount">- Rp 0</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-gray-600 fs-7">Ongkos Kirim & Tambahan:</span>
                        <span class="fw-bold text-gray-800" id="po-summary-fees">+ Rp 0</span>
                    </div>
                </div>

                <div class="border-top border-primary border-opacity-25 pt-4">
                    <div class="text-muted fs-8 fw-bold text-uppercase mb-1">TOTAL NILAI AKHIR PESANAN (PO)</div>
                    <div class="fs-2x fw-bolder text-primary" id="po-grand-total">Rp 0</div>
                    <div class="text-muted fs-8 mt-1">Dihitung otomatis termasuk diskon, pajak per baris, dan ongkos pengiriman.</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 4. ACTION BAR --}}
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 p-4 bg-light rounded border">
    <div class="d-flex align-items-center text-muted fs-7">
        <i class="ki-outline ki-shield-tick fs-5 text-success me-2"></i>
        Draft pesanan dapat diubah kembali sewaktu-waktu sebelum dikirim ke supplier rekanan.
    </div>
    <div class="d-flex flex-wrap gap-3">
        <a href="{{ $purchaseOrder->exists ? route('purchasing.purchase-orders.show', $purchaseOrder) : route('purchasing.purchase-orders.index') }}" class="btn btn-light">
            Batal
        </a>
        <button type="submit" name="submit_action" value="draft" class="btn btn-light-primary">
            <i class="ki-outline ki-file fs-5 me-1"></i> Simpan Sebagai Draft
        </button>
        @can('purchase_orders.approve')
            <button type="submit" name="submit_action" value="approve" class="btn btn-success">
                <i class="ki-outline ki-check-circle fs-5 me-1"></i> Simpan & Setujui Langsung (Siap Kirim)
            </button>
        @endcan
    </div>
</div>

{{-- Modal Katalog Visual Produk --}}
<x-metronic.visual-catalog-modal 
    id="visualCatalogModal" 
    sourceSelector="#po-items-body tr:first-child select.po-product-select option"
    on-select="handleCatalogSelect" 
/>

@push('scripts')
<script>
    let activePoRowIndex = 0;

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
            const subtotalEl = row.querySelector('.po-line-subtotal');
            if (subtotalEl) {
                subtotalEl.innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(lineTotal);
            }
            subtotal += lineTotal;
        });

        const documentDiscount = parseFloat(document.querySelector('[name="header_discount"]')?.value || 0);
        const freight = parseFloat(document.querySelector('[name="freight_cost"]')?.value || 0);
        const additional = parseFloat(document.querySelector('[name="additional_cost"]')?.value || 0);
        const total = Math.max(subtotal - documentDiscount + freight + additional, 0);

        // Update Ringkasan Finansial
        const sumSubtotalEl = document.getElementById('po-summary-subtotal');
        if (sumSubtotalEl) sumSubtotalEl.innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(subtotal);

        const sumDiscEl = document.getElementById('po-summary-discount');
        if (sumDiscEl) sumDiscEl.innerText = '- Rp ' + new Intl.NumberFormat('id-ID').format(documentDiscount);

        const sumFeesEl = document.getElementById('po-summary-fees');
        if (sumFeesEl) sumFeesEl.innerText = '+ Rp ' + new Intl.NumberFormat('id-ID').format(freight + additional);

        const grandTotalEl = document.getElementById('po-grand-total');
        if (grandTotalEl) grandTotalEl.innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(total);
    };

    window.calculatePurchaseOrderTotal();

    // Track active row for visual catalog
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-catalog');
        if (btn) {
            activePoRowIndex = btn.dataset.rowIndex;
            const modal = document.getElementById('visualCatalogModal');
            if (modal && modal.__catalogInstance) {
                modal.__catalogInstance.updateSourceSelector(`select[name="items[${activePoRowIndex}][product_id]"] option`);
            }
        }
    });

    window.handleCatalogSelect = function(product) {
        const select = document.querySelector(`select[name="items[${activePoRowIndex}][product_id]"]`);
        if (select) {
            select.value = product.id;
            select.dispatchEvent(new Event('change'));
            toastr && toastr.success(product.name + ' dipilih.');
        }
    };

    // Add PO Row
    function addPoRow() {
        const tbody = document.getElementById('po-items-body');
        const rows = tbody.querySelectorAll('.po-item-row');
        const newIndex = rows.length;

        const firstRow = rows[0];
        if (!firstRow) return;

        const newRow = firstRow.cloneNode(true);
        newRow.setAttribute('data-index', newIndex);

        // Update product select
        const prodSelect = newRow.querySelector('.po-product-select');
        prodSelect.setAttribute('name', `items[${newIndex}][product_id]`);
        prodSelect.setAttribute('aria-label', `Produk item ${newIndex + 1}`);
        prodSelect.value = '';

        // Update catalog button
        const catBtn = newRow.querySelector('.btn-catalog');
        if (catBtn) catBtn.setAttribute('data-row-index', newIndex);

        // Clear last price badge
        const lastPriceInfo = newRow.querySelector('.last-price-info');
        if (lastPriceInfo) lastPriceInfo.innerHTML = '';

        // Update unit select
        const unitSelect = newRow.querySelector('.po-unit-select');
        unitSelect.setAttribute('name', `items[${newIndex}][unit_id]`);
        unitSelect.setAttribute('aria-label', `Satuan pembelian item ${newIndex + 1}`);

        // Update inputs
        const qtyInput = newRow.querySelector('.po-qty');
        qtyInput.setAttribute('name', `items[${newIndex}][quantity_ordered]`);
        qtyInput.value = 1;

        const priceInput = newRow.querySelector('.po-price');
        priceInput.setAttribute('name', `items[${newIndex}][unit_price]`);
        priceInput.value = 0;
        priceInput.dataset.touched = "";

        const discInput = newRow.querySelector('.po-discount');
        discInput.setAttribute('name', `items[${newIndex}][discount_amount]`);
        discInput.value = 0;

        const taxInput = newRow.querySelector('.po-tax');
        taxInput.setAttribute('name', `items[${newIndex}][tax_amount]`);
        taxInput.value = 0;

        newRow.querySelector('.po-line-subtotal').innerText = 'Rp 0';

        tbody.appendChild(newRow);
        reindexPoRows();
        window.calculatePurchaseOrderTotal();
    }

    document.getElementById('btn-add-po-row')?.addEventListener('click', addPoRow);
    document.getElementById('btn-add-po-row-bottom')?.addEventListener('click', addPoRow);

    // Remove PO Row
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-remove-po-row');
        if (btn) {
            const tbody = document.getElementById('po-items-body');
            const rows = tbody.querySelectorAll('.po-item-row');
            if (rows.length <= 1) {
                toastr && toastr.warning('Minimal harus ada 1 barang dalam pesanan pembelian.');
                return;
            }
            btn.closest('.po-item-row').remove();
            reindexPoRows();
            window.calculatePurchaseOrderTotal();
        }
    });

    function reindexPoRows() {
        const rows = document.querySelectorAll('#po-items-body .po-item-row');
        rows.forEach(function(row, idx) {
            row.setAttribute('data-index', idx);
            const numEl = row.querySelector('.po-row-num');
            if (numEl) numEl.innerText = idx + 1;
            const prod = row.querySelector('.po-product-select');
            if (prod) prod.setAttribute('name', `items[${idx}][product_id]`);
            const cat = row.querySelector('.btn-catalog');
            if (cat) cat.setAttribute('data-row-index', idx);
            const unit = row.querySelector('.po-unit-select');
            if (unit) unit.setAttribute('name', `items[${idx}][unit_id]`);
            const qty = row.querySelector('.po-qty');
            if (qty) qty.setAttribute('name', `items[${idx}][quantity_ordered]`);
            const price = row.querySelector('.po-price');
            if (price) price.setAttribute('name', `items[${idx}][unit_price]`);
            const disc = row.querySelector('.po-discount');
            if (disc) disc.setAttribute('name', `items[${idx}][discount_amount]`);
            const tax = row.querySelector('.po-tax');
            if (tax) tax.setAttribute('name', `items[${idx}][tax_amount]`);
        });

        const counter = document.getElementById('po-items-counter');
        if (counter) counter.innerText = `${rows.length} item`;
    }

    // Supplier change auto-fills payment term
    document.getElementById('po-supplier-select')?.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const term = opt?.getAttribute('data-term');
        if (term !== null && term !== undefined) {
            const termInput = document.getElementById('po-term-days');
            if (termInput) termInput.value = term;
        }
        updateAllLastPrices();
    });

    // Product change updates last price & pre-fills price if available
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('po-product-select')) {
            updateRowPrice(e.target.closest('.po-item-row'));
            window.calculatePurchaseOrderTotal();
        }
    });

    function updateRowPrice(row) {
        if (!row) return;
        const select = row.querySelector('.po-product-select');
        const selectedOpt = select?.options[select.selectedIndex];
        if (!selectedOpt || !selectedOpt.value) return;

        const baseUnitId = selectedOpt.getAttribute('data-base-unit');
        const unitSelect = row.querySelector('.po-unit-select');
        if (baseUnitId && unitSelect && !unitSelect.value) {
            unitSelect.value = baseUnitId;
        }

        const supplierId = document.getElementById('po-supplier-select')?.value;
        const costPrice = parseFloat(selectedOpt.getAttribute('data-cost') || 0);
        let supplierPrices = {};
        try {
            supplierPrices = JSON.parse(selectedOpt.getAttribute('data-supplier-prices') || '{}');
        } catch(e) {}

        const lastPrice = supplierId && supplierPrices[supplierId] !== undefined ? supplierPrices[supplierId] : costPrice;
        const priceInput = row.querySelector('.po-price');
        if (priceInput && (parseFloat(priceInput.value || 0) === 0 || !priceInput.dataset.touched)) {
            priceInput.value = lastPrice;
        }

        const infoEl = row.querySelector('.last-price-info');
        if (infoEl) {
            if (lastPrice > 0) {
                infoEl.innerHTML = `<span class="badge badge-light-info fs-9">Harga Beli Terakhir: Rp ${new Intl.NumberFormat('id-ID').format(lastPrice)}</span>`;
            } else {
                infoEl.innerHTML = '';
            }
        }
    }

    function updateAllLastPrices() {
        document.querySelectorAll('#po-items-body .po-item-row').forEach(row => updateRowPrice(row));
    }

    document.querySelectorAll('#po-items-body .po-price').forEach(input => {
        input.addEventListener('input', function() { this.dataset.touched = "true"; });
    });
</script>
@endpush
