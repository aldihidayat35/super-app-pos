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
    <div class="alert alert-light-primary d-flex align-items-start mb-5">
        <i class="ki-outline ki-document fs-2 text-primary me-3"></i>
        <div>
            <div class="fw-bold">Referensi Pengajuan Belanja Internal</div>
            <div class="fs-7">PO ini berasal dari pengajuan <a href="{{ route('purchasing.requests.show', $purchaseRequest) }}" class="fw-semibold">{{ $purchaseRequest->number }}</a>.</div>
        </div>
    </div>
    <input type="hidden" name="purchase_request_id" value="{{ $purchaseRequest->id }}">
@endif

<div class="mb-6">
    <h3 class="fs-5 fw-bold mb-1">Informasi Supplier dan Tujuan Pengiriman</h3>
    <div class="text-muted fs-7 mb-4">Tentukan rekanan supplier, gudang/toko penerima, tanggal, serta termin pembayaran.</div>
    <div class="row g-5">
        <div class="col-md-6">
            <x-metronic.form-group name="supplier_id" label="Supplier Rekanan" required help="Pemasok aktif yang akan menerima pesanan pembelian ini.">
                <select name="supplier_id" id="po-supplier-select" class="form-select form-select-solid" required>
                    <option value="">Pilih supplier</option>
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
            <x-metronic.form-group name="destination_work_location_id" label="Lokasi Tujuan Pengiriman" required help="Pilih gudang utama atau cabang toko yang akan menerima fisik barang langsung dari supplier.">
                <select name="destination_work_location_id" class="form-select form-select-solid" required>
                    <option value="">Pilih lokasi penerima</option>
                    @foreach ($destinations as $destination)
                        <option value="{{ $destination->id }}" @selected(old('destination_work_location_id', $defaultDestinationId) == $destination->id)>
                            {{ $destination->typeLabel() }} - {{ $destination->code }} - {{ $destination->name }}
                        </option>
                    @endforeach
                </select>
            </x-metronic.form-group>
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-metronic.form-group name="order_date" label="Tanggal Pesanan" required help="Tanggal resmi surat pesanan diterbitkan.">
                <input type="date" name="order_date" value="{{ old('order_date', optional($purchaseOrder->order_date)->format('Y-m-d') ?: now()->toDateString()) }}" class="form-control form-control-solid" required>
            </x-metronic.form-group>
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-metronic.form-group name="expected_at" label="Estimasi Tanggal Tiba (ETA)" help="Perkiraan tanggal barang sampai di lokasi penerima.">
                <input type="date" name="expected_at" value="{{ old('expected_at', optional($purchaseOrder->expected_at)->format('Y-m-d')) }}" class="form-control form-control-solid">
            </x-metronic.form-group>
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-metronic.form-group name="payment_term_days" label="Termin Pembayaran (Hari)" help="Jangka waktu pembayaran jatuh tempo ke supplier (0 untuk tunai).">
                <input type="number" id="po-term-days" name="payment_term_days" value="{{ old('payment_term_days', $purchaseOrder->payment_term_days ?? 0) }}" min="0" max="365" class="form-control form-control-solid">
            </x-metronic.form-group>
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-metronic.form-group name="notes" label="Catatan Tambahan" help="Instruksi khusus kepada supplier atau catatan dokumen intern.">
                <input name="notes" value="{{ old('notes', $purchaseOrder->notes) }}" class="form-control form-control-solid" placeholder="Contoh: Kirim via ekspedisi langganan">
            </x-metronic.form-group>
        </div>
    </div>
</div>

<div class="separator mb-6"></div>

<div class="mb-6">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fs-5 fw-bold mb-1">Daftar Barang yang Dipesan</h3>
            <div class="text-muted fs-7">Tentukan produk, satuan beli, kuantitas, harga per satuan, serta diskon/pajak item.</div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-light-primary" id="btn-add-po-row">
                <i class="ki-outline ki-plus fs-5 me-1"></i> Tambah Baris
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-row-dashed align-middle mb-0" id="po-items-table">
            <thead>
                <tr class="text-muted fw-bold text-uppercase fs-8">
                    <th class="min-w-220px">Produk</th>
                    <th class="min-w-130px">Satuan Beli</th>
                    <th class="min-w-100px">Qty Order</th>
                    <th class="min-w-130px">Harga Satuan</th>
                    <th class="min-w-100px">Diskon Item</th>
                    <th class="min-w-100px">Pajak Item</th>
                    <th class="min-w-130px text-end">Subtotal</th>
                    <th class="w-40px text-end">Aksi</th>
                </tr>
            </thead>
            <tbody id="po-items-body">
                @foreach ($existingItems as $i => $item)
                    <tr class="po-item-row" data-index="{{ $i }}">
                        <td>
                            <div class="d-flex gap-2">
                                <select name="items[{{ $i }}][product_id]" class="form-select form-select-sm form-select-solid po-product-select" required aria-label="Produk item {{ $i + 1 }}">
                                    <option value="">Pilih produk</option>
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
                                <button type="button" class="btn btn-icon btn-sm btn-light-primary btn-catalog" data-bs-toggle="modal" data-bs-target="#visualCatalogModal" data-row-index="{{ $i }}" title="Pilih dari Katalog Visual">
                                    <i class="ki-outline ki-element-11 fs-6"></i>
                                </button>
                            </div>
                            <div class="last-price-info text-muted fs-8 mt-1"></div>
                        </td>
                        <td>
                            <select name="items[{{ $i }}][unit_id]" class="form-select form-select-sm form-select-solid po-unit-select" required aria-label="Satuan pembelian item {{ $i + 1 }}">
                                <option value="">Pilih satuan</option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->id }}" @selected(($item['unit_id'] ?? '') == $unit->id)>{{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input name="items[{{ $i }}][quantity_ordered]" value="{{ qty_input($item['quantity_ordered'] ?? 1) }}" type="number" step="any" min="0.0001" class="form-control form-control-sm form-control-solid po-calc po-qty" required aria-label="Jumlah dipesan item {{ $i + 1 }}">
                        </td>
                        <td>
                            <input name="items[{{ $i }}][unit_price]" value="{{ $item['unit_price'] ?? 0 }}" type="number" step="0.01" min="0" class="form-control form-control-sm form-control-solid po-calc po-price" required aria-label="Harga satuan item {{ $i + 1 }}">
                        </td>
                        <td>
                            <input name="items[{{ $i }}][discount_amount]" value="{{ $item['discount_amount'] ?? 0 }}" type="number" step="0.01" min="0" class="form-control form-control-sm form-control-solid po-calc po-discount" aria-label="Diskon item {{ $i + 1 }}">
                        </td>
                        <td>
                            <input name="items[{{ $i }}][tax_amount]" value="{{ $item['tax_amount'] ?? 0 }}" type="number" step="0.01" min="0" class="form-control form-control-sm form-control-solid po-calc po-tax" aria-label="Pajak item {{ $i + 1 }}">
                        </td>
                        <td class="fw-bold text-end po-line-subtotal">Rp 0</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-icon btn-sm btn-light-danger btn-remove-po-row" title="Hapus Baris">
                                <i class="ki-outline ki-trash fs-6"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="separator mb-6"></div>

<div>
    <h3 class="fs-5 fw-bold mb-1">Rincian Nilai dan Total Pesanan</h3>
    <div class="text-muted fs-7 mb-4">Biaya berikut berlaku untuk keseluruhan dokumen pesanan.</div>
    <div class="row g-5 align-items-end">
        <div class="col-sm-6 col-lg-3">
            <x-metronic.form-group name="header_discount" label="Diskon Nota Keseluruhan (Rp)" help="Potongan diskon nominal untuk keseluruhan PO.">
                <input name="header_discount" value="{{ old('header_discount', $purchaseOrder->header_discount ?? 0) }}" type="number" step="0.01" min="0" class="form-control form-control-solid po-total-input">
            </x-metronic.form-group>
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-metronic.form-group name="freight_cost" label="Ongkos Pengiriman (Rp)" help="Biaya pengiriman barang dari lokasi supplier ke gudang/toko.">
                <input name="freight_cost" value="{{ old('freight_cost', $purchaseOrder->freight_cost ?? 0) }}" type="number" step="0.01" min="0" class="form-control form-control-solid po-total-input">
            </x-metronic.form-group>
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-metronic.form-group name="additional_cost" label="Biaya Tambahan Lainnya (Rp)" help="Biaya bongkar muat, asuransi, atau administrasi dokumen.">
                <input name="additional_cost" value="{{ old('additional_cost', $purchaseOrder->additional_cost ?? 0) }}" type="number" step="0.01" min="0" class="form-control form-control-solid po-total-input">
            </x-metronic.form-group>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="border rounded p-4 mb-5 bg-light-primary">
                <div class="text-muted fs-8 fw-bold text-uppercase">TOTAL NILAI PESANAN</div>
                <div class="fs-2 fw-bolder text-primary" id="po-grand-total">Rp 0</div>
            </div>
        </div>
    </div>
</div>

<div class="mt-6 d-flex flex-wrap gap-3">
    <button type="submit" name="submit_action" value="draft" class="btn btn-light-primary">
        <i class="ki-outline ki-file fs-5 me-2"></i>Simpan Sebagai Draft
    </button>
    @can('purchase_orders.approve')
        <button type="submit" name="submit_action" value="approve" class="btn btn-success">
            <i class="ki-outline ki-check-circle fs-5 me-2"></i>Simpan & Setujui Langsung (Siap Kirim)
        </button>
    @endcan
    <a href="{{ $purchaseOrder->exists ? route('purchasing.purchase-orders.show', $purchaseOrder) : route('purchasing.purchase-orders.index') }}" class="btn btn-light">Batal</a>
</div>

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
        document.getElementById('btn-add-po-row')?.addEventListener('click', function() {
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

            const discInput = newRow.querySelector('.po-discount');
            discInput.setAttribute('name', `items[${newIndex}][discount_amount]`);
            discInput.value = 0;

            const taxInput = newRow.querySelector('.po-tax');
            taxInput.setAttribute('name', `items[${newIndex}][tax_amount]`);
            taxInput.value = 0;

            newRow.querySelector('.po-line-subtotal').innerText = 'Rp 0';

            tbody.appendChild(newRow);
            window.calculatePurchaseOrderTotal();
        });

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
            document.querySelectorAll('#po-items-body .po-item-row').forEach(function(row, idx) {
                row.setAttribute('data-index', idx);
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
