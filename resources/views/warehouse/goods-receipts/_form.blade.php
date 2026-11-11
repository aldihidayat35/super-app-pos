@php
    $receiptItems = $receipt->exists ? $receipt->items : collect();
    $isEdit = $receipt->exists;

    $productsJson = ($products ?? collect())->map(fn($p) => [
        'id' => $p->id,
        'name' => $p->name,
        'sku' => $p->sku,
        'barcode' => $p->barcodes->first()?->code ?? '',
        'base_unit_id' => $p->base_unit_id,
        'base_unit_name' => strtolower($p->baseUnit?->code ?? 'pcs'),
        'cost_price' => (float) ($p->cost_price ?? 0),
        'minimum_price' => (float) ($p->minimum_price ?? 0),
        'category_name' => $p->category?->name ?? 'Umum',
        'units' => $p->units->map(fn($u) => [
            'unit_id' => $u->unit_id,
            'unit_name' => strtolower($u->unit?->code ?? $u->unit?->name ?? 'pcs'),
            'conversion_factor' => (float) $u->conversion_factor,
        ])->values()->all(),
    ])->values()->all();

    $unitsJson = ($units ?? collect())->map(fn($u) => [
        'id' => $u->id,
        'name' => $u->name,
        'code' => strtolower($u->code),
    ])->values()->all();

    $receiptItemsJson = $receiptItems->map(fn($item) => [
        'id' => $item->id,
        'product_id' => $item->product_id,
        'purchase_order_item_id' => $item->purchase_order_item_id,
        'unit_id' => $item->unit_id,
        'warehouse_location_id' => $item->warehouse_location_id,
        'quantity_received' => (float) $item->quantity_received,
        'quantity_accepted' => (float) $item->quantity_accepted,
        'quantity_rejected' => (float) $item->quantity_rejected,
        'quantity_damaged' => (float) $item->quantity_damaged,
        'quantity_returned_to_supplier' => (float) $item->quantity_returned_to_supplier,
        'unit_price' => (float) $item->unit_price,
        'actual_unit_cost' => (float) $item->actual_unit_cost,
        'sale_price' => (float) $item->sale_price,
        'batch_no' => $item->batch_no ?? '',
        'qc_notes' => $item->qc_notes ?? '',
    ])->values()->all();
@endphp

<form id="goods-receipt-form" method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @if(($method ?? 'POST') !== 'POST') @method($method) @endif

    @if($selectedPo)
        <input type="hidden" name="purchase_order_id" value="{{ $selectedPo->id }}">
    @endif

    {{-- 1. HEADER INFORMASI PENERIMAAN --}}
    <x-metronic.card title="Informasi Penerimaan Supplier" class="mb-5 shadow-xs border border-gray-200">
        <div class="row g-4">
            {{-- Lokasi Penerimaan --}}
            <div class="col-md-3">
                <label class="form-label required fw-bold fs-7">Lokasi Penerima</label>
                @if($isEdit && $receipt->status->isPosted())
                    <input class="form-control form-control-solid" value="{{ $receipt->destinationName() }}" readonly>
                @else
                    <select name="destination_work_location_id" id="select-destination-location" class="form-select form-select-solid fw-semibold fs-7" required>
                        @foreach($permittedLocations as $loc)
                            <option value="{{ $loc->id }}" 
                                    data-type="{{ $loc->type }}"
                                    @selected((int) old('destination_work_location_id', $receipt->destination_work_location_id ?? $currentLocation?->id) === $loc->id)>
                                {{ $loc->name }} ({{ ucfirst($loc->type) }})
                            </option>
                        @endforeach
                    </select>
                @endif
                <div class="text-muted fs-8 mt-1">Stok akan masuk ke lokasi ini.</div>
            </div>

            {{-- Supplier --}}
            <div class="col-md-3">
                <label class="form-label required fw-bold fs-7">Supplier</label>
                @if($isEdit && $receipt->status->isPosted())
                    <input class="form-control form-control-solid" value="{{ $receipt->supplier?->name }}" readonly>
                @else
                    <select name="supplier_id" id="select-supplier" class="form-select form-select-solid fw-semibold fs-7 select2-enable" required>
                        <option value="">-- Pilih Supplier --</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" 
                                    @selected((int) old('supplier_id', $receipt->supplier_id ?? $selectedPo?->supplier_id) === $supplier->id)>
                                {{ $supplier->name }}
                            </option>
                        @endforeach
                    </select>
                @endif
                <div class="text-muted fs-8 mt-1">Pemasok barang datang.</div>
            </div>

            {{-- Tanggal Penerimaan --}}
            <div class="col-md-3">
                <label class="form-label required fw-bold fs-7">Tanggal Penerimaan</label>
                <input type="date" name="received_at" 
                       value="{{ old('received_at', optional($receipt->received_at)->format('Y-m-d') ?: now()->toDateString()) }}" 
                       class="form-control form-control-solid fw-semibold fs-7 @error('received_at') is-invalid @enderror" 
                       required>
                @error('received_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Nomor Surat Jalan / Faktur Supplier --}}
            <div class="col-md-3">
                <label class="form-label fw-bold fs-7">No. Surat Jalan / Faktur</label>
                <input type="text" name="delivery_note_number" 
                       value="{{ old('delivery_note_number', $receipt->delivery_note_number) }}" 
                       class="form-control form-control-solid fw-semibold fs-7" 
                       placeholder="Contoh: SJ-2026/11/001">
            </div>

            {{-- Total Ongkir Aktual --}}
            <div class="col-md-3">
                <label class="form-label fw-bold fs-7">Total Biaya Ongkir (Rp)</label>
                <div class="input-group input-group-solid">
                    <span class="input-group-text fs-8 text-gray-500">Rp</span>
                    <input type="text" name="actual_freight_cost" id="input-freight-cost" 
                           value="{{ number_format((float) old('actual_freight_cost', $receipt->actual_freight_cost ?? 0), 0, ',', '.') }}" 
                           class="form-control form-control-solid fw-bold fs-7 format-rupiah" 
                           placeholder="0">
                </div>
                <div class="text-muted fs-8 mt-1">Dibagi proporsional ke barang lolos QC.</div>
            </div>

            {{-- Biaya Tambahan Aktual --}}
            <div class="col-md-3">
                <label class="form-label fw-bold fs-7">Biaya Tambahan Lainnya (Rp)</label>
                <div class="input-group input-group-solid">
                    <span class="input-group-text fs-8 text-gray-500">Rp</span>
                    <input type="text" name="actual_additional_cost" id="input-additional-cost" 
                           value="{{ number_format((float) old('actual_additional_cost', $receipt->actual_additional_cost ?? 0), 0, ',', '.') }}" 
                           class="form-control form-control-solid fw-bold fs-7 format-rupiah" 
                           placeholder="0">
                </div>
                <div class="text-muted fs-8 mt-1">Bongkar muat, asuransi, dll (opsional).</div>
            </div>

            {{-- Bukti Foto / Surat Jalan --}}
            <div class="col-md-3">
                <label class="form-label fw-bold fs-7">Foto / Bukti Pengiriman</label>
                <input type="file" name="proof" class="form-control form-control-solid fs-8" accept=".jpg,.jpeg,.png,.pdf">
                @if($receipt->proof_path)
                    <div class="mt-1"><a href="{{ asset('storage/' . $receipt->proof_path) }}" target="_blank" class="text-primary fs-8"><i class="ki-outline ki-file me-1"></i> Lihat Bukti Terunggah</a></div>
                @endif
            </div>

            {{-- Catatan Penerimaan --}}
            <div class="col-md-3">
                <label class="form-label fw-bold fs-7">Catatan Penerimaan</label>
                <input type="text" name="notes" value="{{ old('notes', $receipt->notes) }}" class="form-control form-control-solid fs-7" placeholder="Catatan internal / sopir">
            </div>
        </div>
    </x-metronic.card>

    {{-- 2. TABEL BARANG & QUALITY CONTROL (QC) --}}
    <x-metronic.card class="mb-5 shadow-xs border border-gray-200">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h4 class="fw-boldest text-gray-900 mb-1">Daftar Barang &amp; Quality Control (QC)</h4>
                <div class="text-muted fs-8">Periksa kondisi fisik barang, tentukan HPP beli, dan tetapkan harga minimum jual di atas modal.</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-light-success fw-bold" data-bs-toggle="modal" data-bs-target="#modal-add-product">
                    <i class="ki-outline ki-plus-circle fs-6 me-1 text-success"></i> Tambah Produk Baru
                </button>
                <button type="button" class="btn btn-sm btn-light-primary fw-bold" id="btn-add-row" onclick="addNewProductRow()">
                    <i class="ki-outline ki-plus fs-6 me-1"></i> Tambah Baris
                </button>
            </div>
        </div>

        <div class="table-responsive border border-gray-200 rounded">
            <table class="table table-row-bordered table-row-gray-200 align-middle gs-4 gy-3 mb-0" id="table-receipt-items">
                <thead class="bg-light">
                    <tr class="fw-bolder text-gray-800 fs-8 text-uppercase">
                        <th style="width: 24%; min-width: 220px;" class="ps-3">Produk &amp; SKU</th>
                        <th style="width: 13%; min-width: 130px;">Satuan Beli &amp; Konversi</th>
                        <th style="width: 18%; min-width: 170px;">Kuantitas &amp; QC Fisik</th>
                        <th style="width: 14%; min-width: 130px;">HPP Beli &amp; Ongkir</th>
                        <th style="width: 13%; min-width: 120px; background-color: rgba(var(--bs-primary-rgb), 0.03);">HPP Final (Modal/Pcs)</th>
                        <th style="width: 18%; min-width: 170px; background-color: rgba(var(--bs-success-rgb), 0.03);">Harga Jual Min (Anti-Rugi)</th>
                        <th style="width: 4%; min-width: 40px;" class="text-center pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody id="receipt-items-tbody" class="fs-7">
                    {{-- Baris akan dirender di sini via Blade atau JS --}}
                </tbody>
            </table>
        </div>

        {{-- Pesan Validasi Error Global Form --}}
        @if($errors->any())
            <div class="alert alert-danger mt-4 d-flex align-items-center p-4">
                <i class="ki-outline ki-cross-circle fs-2x text-danger me-3"></i>
                <div class="d-flex flex-column">
                    <span class="fw-bold">Terdapat kesalahan pada input penerimaan barang:</span>
                    <ul class="mb-0 fs-8 mt-1">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- 3. RINGKASAN FINANSIAL DI BAGIAN BAWAH --}}
        <div class="row g-4 mt-2">
            <div class="col-12 col-lg-8">
                <div class="p-4 rounded-3 bg-light border border-gray-200">
                    <div class="row g-3 text-center text-md-start">
                        <div class="col-6 col-md-3">
                            <span class="text-gray-500 fs-8 text-uppercase fw-bold">Total Datang</span>
                            <div class="fs-4 fw-bolder text-gray-900 mt-1" id="summary-total-received">0 pcs</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-gray-500 fs-8 text-uppercase fw-bold">Lolos QC (Accepted)</span>
                            <div class="fs-4 fw-bolder text-success mt-1" id="summary-total-accepted">0 pcs</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-gray-500 fs-8 text-uppercase fw-bold">Rusak &amp; Retur</span>
                            <div class="fs-4 fw-bolder text-warning mt-1" id="summary-total-nonaccepted">0 pcs</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-gray-500 fs-8 text-uppercase fw-bold">Subtotal Pembelian</span>
                            <div class="fs-4 fw-bolder text-gray-900 mt-1" id="summary-subtotal-cost">Rp 0</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="p-4 rounded-3 bg-light-primary border border-primary border-opacity-25 d-flex flex-column justify-content-between h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-gray-700 fs-8 fw-bold">Total Nilai Faktur (Barang + Ongkir):</span>
                        <span class="fs-3 fw-boldest text-primary" id="summary-total-invoice">Rp 0</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between text-muted fs-8">
                        <span>Total Ongkir &amp; Biaya:</span>
                        <span class="fw-bold text-gray-800" id="summary-total-landed">Rp 0</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- 4. TOMBOL AKSI SUBMIT --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-5 pt-4 border-top border-gray-200">
            <a href="{{ route('warehouse.goods-receipts.index') }}" class="btn btn-sm btn-light fw-bold">
                <i class="ki-outline ki-arrow-left fs-6 me-1"></i> Batal &amp; Kembali
            </a>
            <div class="d-flex align-items-center gap-3">
                <button type="submit" name="action" value="draft" class="btn btn-sm btn-light-primary fw-bold px-4 py-2.5">
                    <i class="ki-outline ki-save-2 fs-6 me-1"></i> Simpan Sebagai Draft
                </button>
                <button type="button" class="btn btn-sm btn-success fw-bolder px-5 py-2.5 shadow-sm" id="btn-submit-post" onclick="submitReceiptPost()">
                    <i class="ki-outline ki-check-circle fs-5 me-1 text-white"></i> Simpan &amp; Posting Stok
                </button>
            </div>
        </div>
    </x-metronic.card>
</form>

{{-- MODAL POPUP: TAMBAH PRODUK BARU INSTAN --}}
<div class="modal fade" id="modal-add-product" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <form id="form-quick-add-product" onsubmit="handleQuickProductSubmit(event)">
                @csrf
                <div class="modal-header pb-0 border-0 justify-content-between">
                    <h3 class="fw-boldest text-gray-900 mb-0">Tambah Produk Baru</h3>
                    <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                        <i class="ki-outline ki-cross fs-2"></i>
                    </div>
                </div>
                <div class="modal-body py-4">
                    <div class="d-flex flex-column gap-3">
                        <div>
                            <label class="form-label required fs-8 fw-bold">Nama Produk</label>
                            <input type="text" name="quick_name" id="quick-prod-name" class="form-control form-control-sm form-control-solid" placeholder="Contoh: Kopi Bubuk 250gr" required>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label required fs-8 fw-bold">Kategori</label>
                                <select name="quick_category_id" id="quick-prod-category" class="form-select form-select-sm form-select-solid" required>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label required fs-8 fw-bold">Satuan Dasar</label>
                                <select name="quick_base_unit_id" id="quick-prod-unit" class="form-select form-select-sm form-select-solid" required>
                                    @foreach($units as $u)
                                        <option value="{{ $u->id }}" @selected(strtoupper((string) $u->code) === 'PCS')>
                                            {{ $u->name }} ({{ strtolower($u->code) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label fs-8 fw-bold">SKU Produk (Opsional)</label>
                                <input type="text" name="quick_sku" id="quick-prod-sku" class="form-control form-control-sm form-control-solid font-monospace" placeholder="Otomatis jika kosong">
                            </div>
                            <div class="col-6">
                                <label class="form-label fs-8 fw-bold">Barcode (Opsional)</label>
                                <input type="text" name="quick_barcode" id="quick-prod-barcode" class="form-control form-control-sm form-control-solid font-monospace" placeholder="Scan barcode">
                            </div>
                        </div>
                        <div class="alert alert-light-info p-3 mb-0 fs-9">
                            <i class="ki-outline ki-information-2 text-info fs-7 me-1"></i> Setelah disimpan, produk akan langsung otomatis terpilih di baris penerimaan barang.
                        </div>
                    </div>
                </div>
                <div class="modal-footer pt-0 border-0">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold" id="btn-save-quick-product">
                        <i class="ki-outline ki-check fs-6 me-1"></i> Simpan Produk
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    'use strict';

    // Master Data Cache
    const masterProducts = @json($productsJson);
    const masterUnits = @json($unitsJson);
    let initialReceiptItems = @json($receiptItemsJson);

    let rowIndexCounter = 0;

    // Helper: Parse Angka
    function parseNumber(val) {
        if (!val) return 0;
        if (typeof val === 'number') return val;
        val = val.toString().replace(/\./g, '').replace(/,/g, '.').replace(/[^\d.-]/g, '');
        const num = parseFloat(val);
        return isNaN(num) ? 0 : num;
    }

    // Helper: Format Ribuan
    function formatRupiah(num) {
        num = Math.round(num);
        return new Intl.NumberFormat('id-ID').format(num);
    }

    // Render Row Template
    window.addNewProductRow = function(presetData = null) {
        const tbody = document.getElementById('receipt-items-tbody');
        if (!tbody) return;

        const idx = rowIndexCounter++;
        const row = document.createElement('tr');
        row.className = 'receipt-item-row';
        row.id = `row-item-${idx}`;
        row.setAttribute('data-row-id', idx);

        let selectedProductId = presetData?.product_id || (presetData?.id || '');
        let selectedProduct = masterProducts.find(p => p.id == selectedProductId) || masterProducts[0];

        let selectedUnitId = presetData?.unit_id || selectedProduct?.base_unit_id || (masterUnits[0]?.id || 1);
        let qtyReceived = presetData?.quantity_received !== undefined ? presetData.quantity_received : 1;
        let qtyAccepted = presetData?.quantity_accepted !== undefined ? presetData.quantity_accepted : qtyReceived;
        let qtyDamaged = presetData?.quantity_damaged !== undefined ? presetData.quantity_damaged : 0;
        let qtyRejected = presetData?.quantity_rejected !== undefined ? presetData.quantity_rejected : 0;
        let qtyReturned = presetData?.quantity_returned_to_supplier !== undefined ? presetData.quantity_returned_to_supplier : 0;
        let unitPrice = presetData?.unit_price !== undefined ? presetData.unit_price : (selectedProduct?.cost_price || 0);
        let salePrice = presetData?.sale_price !== undefined ? presetData.sale_price : (selectedProduct?.minimum_price || 0);
        let batchNo = presetData?.batch_no || '';
        let qcNotes = presetData?.qc_notes || '';

        // Generate Options Produk
        let productOptions = masterProducts.map(p => `
            <option value="${p.id}" ${p.id == selectedProductId ? 'selected' : ''} data-sku="${p.sku}" data-base-unit="${p.base_unit_id}" data-min-price="${p.minimum_price}">
                ${p.name} (${p.sku})
            </option>
        `).join('');

        // Generate Options Satuan
        let unitOptions = masterUnits.map(u => `
            <option value="${u.id}" ${u.id == selectedUnitId ? 'selected' : ''} data-code="${u.code}">
                ${u.name} (${u.code})
            </option>
        `).join('');

        row.innerHTML = `
            {{-- 1. Produk & SKU --}}
            <td class="ps-3 align-top">
                <div class="d-flex flex-column gap-1">
                    <select name="items[${idx}][product_id]" class="form-select form-select-sm form-select-solid select-product fw-semibold fs-8" onchange="onProductChange(${idx})">
                        ${productOptions}
                    </select>
                    <div class="d-flex align-items-center gap-1.5 mt-1">
                        <span class="badge badge-light-dark font-monospace fs-9 py-0.5 px-1.5 label-sku">${selectedProduct?.sku || '-'}</span>
                        <span class="text-muted fs-9 label-category">${selectedProduct?.category_name || 'Umum'}</span>
                    </div>
                    <div class="mt-1">
                        <input type="text" name="items[${idx}][batch_no]" class="form-control form-control-sm form-control-solid fs-9 py-1" value="${batchNo}" placeholder="No. Batch (opsional)">
                    </div>
                </div>
            </td>

            {{-- 2. Satuan Beli & Faktor Konversi --}}
            <td class="align-top">
                <div class="d-flex flex-column gap-1">
                    <select name="items[${idx}][unit_id]" class="form-select form-select-sm form-select-solid select-unit fw-semibold fs-8" onchange="calculateRow(${idx})">
                        ${unitOptions}
                    </select>
                    <div class="badge badge-light-secondary text-gray-700 fs-9 py-0.5 px-1.5 mt-1 label-conversion">
                        1 = <span class="conversion-factor-text">1</span> <span class="base-unit-text">${selectedProduct?.base_unit_name || 'pcs'}</span>
                    </div>
                </div>
            </td>

            {{-- 3. Kuantitas & QC Fisik --}}
            <td class="align-top">
                <div class="d-flex flex-column gap-1.5">
                    <div class="d-flex align-items-center gap-1">
                        <span class="text-gray-500 fs-9 fw-bold w-45px">Datang:</span>
                        <input type="number" step="1" min="0" name="items[${idx}][quantity_received]" class="form-control form-control-sm form-control-solid input-received fs-8 py-1 fw-bold" value="${qtyReceived}" oninput="onReceivedChange(${idx})">
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <span class="text-success fs-9 fw-bold w-45px">Lolos:</span>
                        <input type="number" step="1" min="0" name="items[${idx}][quantity_accepted]" class="form-control form-control-sm form-control-solid input-accepted fs-8 py-1 fw-bold text-success bg-light-success" value="${qtyAccepted}" oninput="calculateRow(${idx})">
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <span class="text-warning fs-9 fw-bold w-45px">Rusak:</span>
                        <input type="number" step="1" min="0" name="items[${idx}][quantity_damaged]" class="form-control form-control-sm form-control-solid input-damaged fs-8 py-1" value="${qtyDamaged}" oninput="onQcBreakdownChange(${idx})">
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <span class="text-danger fs-9 fw-bold w-45px">Tolak/Ret:</span>
                        <input type="number" step="1" min="0" name="items[${idx}][quantity_rejected]" class="form-control form-control-sm form-control-solid input-rejected fs-8 py-1" value="${qtyRejected}" oninput="onQcBreakdownChange(${idx})" title="Ditolak">
                        <input type="number" step="1" min="0" name="items[${idx}][quantity_returned_to_supplier]" class="form-control form-control-sm form-control-solid input-returned fs-8 py-1" value="${qtyReturned}" oninput="onQcBreakdownChange(${idx})" title="Retur Langsung">
                    </div>
                    <div class="qc-balance-badge badge badge-light-success fs-9 py-0.5">Seimbang</div>
                </div>
            </td>

            {{-- 4. HPP Beli & Alokasi Ongkir --}}
            <td class="align-top">
                <div class="d-flex flex-column gap-1">
                    <label class="text-gray-500 fs-9 fw-bold text-uppercase mb-0">HPP Beli:</label>
                    <div class="input-group input-group-sm input-group-solid">
                        <span class="input-group-text fs-9 py-0 px-1.5 text-gray-400">Rp</span>
                        <input type="text" name="items[${idx}][unit_price]" class="form-control form-control-sm form-control-solid input-unit-price fs-8 py-1 fw-bold format-rupiah" value="${formatRupiah(unitPrice)}" oninput="calculateRow(${idx})">
                    </div>
                    <div class="text-muted fs-9 mt-1">
                        Ongkir/pcs: <span class="fw-bold text-gray-800 label-ongkir-per-pcs">Rp 0</span>
                    </div>
                </div>
            </td>

            {{-- 5. HPP Final Modal per Pcs --}}
            <td class="align-top" style="background-color: rgba(var(--bs-primary-rgb), 0.03);">
                <div class="d-flex flex-column gap-1">
                    <span class="fs-6 fw-boldest text-primary label-hpp-final">Rp 0</span>
                    <input type="hidden" name="items[${idx}][actual_unit_cost]" class="input-actual-unit-cost" value="0">
                    <div class="text-muted fs-9">Modal Bersih / <span class="base-unit-text">${selectedProduct?.base_unit_name || 'pcs'}</span></div>
                </div>
            </td>

            {{-- 6. Harga Jual Minimum & Margin --}}
            <td class="align-top" style="background-color: rgba(var(--bs-success-rgb), 0.03);">
                <div class="d-flex flex-column gap-1.5">
                    <div class="input-group input-group-sm input-group-solid">
                        <span class="input-group-text fs-9 py-0 px-1.5 text-gray-400">Rp</span>
                        <input type="text" name="items[${idx}][sale_price]" class="form-control form-control-sm form-control-solid input-sale-price fs-8 py-1 fw-boldest format-rupiah" value="${formatRupiah(salePrice)}" oninput="calculateRow(${idx})">
                    </div>
                    
                    {{-- Shortcut Margin (+10%, +20%, +30%, +50%) --}}
                    <div class="d-flex align-items-center gap-1">
                        <button type="button" class="btn btn-xs btn-light-primary px-1.5 py-0.5 fs-9 fw-bold" onclick="applyMarginShortcut(${idx}, 10)">+10%</button>
                        <button type="button" class="btn btn-xs btn-light-primary px-1.5 py-0.5 fs-9 fw-bold" onclick="applyMarginShortcut(${idx}, 20)">+20%</button>
                        <button type="button" class="btn btn-xs btn-light-primary px-1.5 py-0.5 fs-9 fw-bold" onclick="applyMarginShortcut(${idx}, 30)">+30%</button>
                        <button type="button" class="btn btn-xs btn-light-primary px-1.5 py-0.5 fs-9 fw-bold" onclick="applyMarginShortcut(${idx}, 50)">+50%</button>
                    </div>

                    {{-- Margin Status Indicator --}}
                    <div class="margin-status-badge badge badge-light-success fs-9 py-0.5">+Rp 0 (+0%)</div>
                </div>
            </td>

            {{-- 7. Tombol Hapus Baris --}}
            <td class="text-center align-top pe-3">
                <button type="button" class="btn btn-icon btn-sm btn-light-danger h-28px w-28px" onclick="removeRow(${idx})" title="Hapus Baris">
                    <i class="ki-outline ki-trash fs-6"></i>
                </button>
            </td>
        `;

        tbody.appendChild(row);
        calculateRow(idx);
    };

    // Hapus Baris
    window.removeRow = function(idx) {
        const row = document.getElementById(`row-item-${idx}`);
        if (!row) return;
        const tbody = document.getElementById('receipt-items-tbody');
        if (tbody.children.length <= 1) {
            Swal.fire({
                title: 'Minimal 1 Barang',
                text: 'Penerimaan barang harus memiliki minimal satu baris produk.',
                icon: 'warning',
                confirmButtonText: 'Mengerti'
            });
            return;
        }
        row.remove();
        recalculateAll();
    };

    // Tangani Perubahan Produk
    window.onProductChange = function(idx) {
        const row = document.getElementById(`row-item-${idx}`);
        if (!row) return;

        const selectProd = row.querySelector('.select-product');
        const prodId = selectProd.value;
        const prod = masterProducts.find(p => p.id == prodId);
        if (!prod) return;

        row.querySelector('.label-sku').textContent = prod.sku;
        row.querySelector('.label-category').textContent = prod.category_name;
        row.querySelectorAll('.base-unit-text').forEach(el => el.textContent = prod.base_unit_name);

        const selectUnit = row.querySelector('.select-unit');
        selectUnit.value = prod.base_unit_id;

        const inputUnitPrice = row.querySelector('.input-unit-price');
        inputUnitPrice.value = formatRupiah(prod.cost_price || 0);

        const inputSalePrice = row.querySelector('.input-sale-price');
        inputSalePrice.value = formatRupiah(prod.minimum_price || 0);

        calculateRow(idx);
    };

    // Tangani Input Qty Datang -> Otomatis set Accepted = Datang jika rusak/tolak = 0
    window.onReceivedChange = function(idx) {
        const row = document.getElementById(`row-item-${idx}`);
        if (!row) return;

        const received = parseNumber(row.querySelector('.input-received').value);
        const damaged = parseNumber(row.querySelector('.input-damaged').value);
        const rejected = parseNumber(row.querySelector('.input-rejected').value);
        const returned = parseNumber(row.querySelector('.input-returned').value);

        const nonAccepted = damaged + rejected + returned;
        const acceptedInput = row.querySelector('.input-accepted');
        acceptedInput.value = Math.max(0, received - nonAccepted);

        calculateRow(idx);
    };

    // Tangani Perubahan QC Breakdown (Rusak / Tolak / Retur)
    window.onQcBreakdownChange = function(idx) {
        const row = document.getElementById(`row-item-${idx}`);
        if (!row) return;

        const received = parseNumber(row.querySelector('.input-received').value);
        const damaged = parseNumber(row.querySelector('.input-damaged').value);
        const rejected = parseNumber(row.querySelector('.input-rejected').value);
        const returned = parseNumber(row.querySelector('.input-returned').value);

        const nonAccepted = damaged + rejected + returned;
        const acceptedInput = row.querySelector('.input-accepted');
        acceptedInput.value = Math.max(0, received - nonAccepted);

        calculateRow(idx);
    };

    // Shortcut Margin
    window.applyMarginShortcut = function(idx, percent) {
        const row = document.getElementById(`row-item-${idx}`);
        if (!row) return;

        const hppFinal = parseNumber(row.querySelector('.input-actual-unit-cost').value);
        if (hppFinal <= 0) {
            Swal.fire({
                title: 'HPP Belum Tersedia',
                text: 'Harap isi kuantitas dan HPP beli terlebih dahulu sebelum menerapkan shortcut margin.',
                icon: 'info',
                confirmButtonText: 'Mengerti'
            });
            return;
        }

        const targetPrice = Math.ceil(hppFinal * (1 + (percent / 100)));
        row.querySelector('.input-sale-price').value = formatRupiah(targetPrice);
        calculateRow(idx);
    };

    // Hitung Faktor Konversi untuk baris
    function getConversionFactor(prod, unitId) {
        if (!prod || !unitId) return 1;
        if (prod.base_unit_id == unitId) return 1;
        const custom = prod.units ? prod.units.find(u => u.unit_id == unitId) : null;
        if (custom && custom.conversion_factor > 0) return custom.conversion_factor;

        const uObj = masterUnits.find(u => u.id == unitId);
        if (uObj) {
            const code = (uObj.code || '').toUpperCase();
            if (code === 'PCS' || code === 'PC' || code === 'BH') return 1;
            if (code === 'LSN') return 12;
            if (code === 'GROS') return 144;
        }
        return 1;
    }

    // Kalkulasi Baris Individual & Trigger Global
    window.calculateRow = function(idx) {
        recalculateAll();
    };

    // Kalkulasi Menyeluruh & Alokasi Ongkir Proporsional
    window.recalculateAll = function() {
        const rows = document.querySelectorAll('tr.receipt-item-row');
        if (rows.length === 0) return;

        const totalFreight = parseNumber(document.getElementById('input-freight-cost')?.value || 0);
        const totalAdditional = parseNumber(document.getElementById('input-additional-cost')?.value || 0);
        const totalLandedCost = totalFreight + totalAdditional;

        let grandTotalReceived = 0;
        let grandTotalAccepted = 0;
        let grandTotalDamaged = 0;
        let grandTotalRejected = 0;
        let grandTotalReturned = 0;
        let grandPurchaseValueAccepted = 0; // Basis alokasi ongkir

        // Step 1: Kumpulkan subtotal nilai pembelian yang lolos QC
        const rowDataList = [];
        rows.forEach(row => {
            const idx = row.getAttribute('data-row-id');
            const selectProd = row.querySelector('.select-product');
            const prod = masterProducts.find(p => p.id == selectProd.value);

            const selectUnit = row.querySelector('.select-unit');
            const unitId = selectUnit.value;
            const factor = getConversionFactor(prod, unitId);

            row.querySelector('.conversion-factor-text').textContent = factor;

            const received = parseNumber(row.querySelector('.input-received').value);
            const accepted = parseNumber(row.querySelector('.input-accepted').value);
            const damaged = parseNumber(row.querySelector('.input-damaged').value);
            const rejected = parseNumber(row.querySelector('.input-rejected').value);
            const returned = parseNumber(row.querySelector('.input-returned').value);

            const unitPrice = parseNumber(row.querySelector('.input-unit-price').value);
            const linePurchaseValue = accepted * unitPrice; // Nilai beli barang diterima

            grandTotalReceived += received * factor;
            grandTotalAccepted += accepted * factor;
            grandTotalDamaged += damaged * factor;
            grandTotalRejected += rejected * factor;
            grandTotalReturned += returned * factor;
            grandPurchaseValueAccepted += linePurchaseValue;

            rowDataList.push({
                row,
                idx,
                prod,
                factor,
                received,
                accepted,
                damaged,
                rejected,
                returned,
                unitPrice,
                linePurchaseValue
            });
        });

        // Step 2: Alokasikan Ongkir secara Proporsional & Hitung HPP Final
        let hasAntiLossViolation = false;

        rowDataList.forEach(item => {
            const row = item.row;
            const acceptedBase = item.accepted * item.factor;

            // Alokasi Ongkir
            let allocatedLandedLine = 0;
            if (grandPurchaseValueAccepted > 0 && totalLandedCost > 0 && item.linePurchaseValue > 0) {
                allocatedLandedLine = (item.linePurchaseValue / grandPurchaseValueAccepted) * totalLandedCost;
            }

            let landedPerBase = 0;
            if (acceptedBase > 0 && allocatedLandedLine > 0) {
                landedPerBase = allocatedLandedLine / acceptedBase;
            }

            // HPP Beli Satuan Dasar = unitPrice / factor
            const baseCostBeli = item.factor > 0 ? (item.unitPrice / item.factor) : item.unitPrice;
            const hppFinalPerBase = Math.round(baseCostBeli + landedPerBase);

            row.querySelector('.label-ongkir-per-pcs').textContent = `Rp ${formatRupiah(landedPerBase)}`;
            row.querySelector('.label-hpp-final').textContent = `Rp ${formatRupiah(hppFinalPerBase)}`;
            row.querySelector('.input-actual-unit-cost').value = hppFinalPerBase;

            // Validasi QC Balance
            const balanceBadge = row.querySelector('.qc-balance-badge');
            const totalQc = item.accepted + item.damaged + item.rejected + item.returned;
            if (totalQc === item.received) {
                balanceBadge.className = 'qc-balance-badge badge badge-light-success fs-9 py-0.5';
                balanceBadge.textContent = 'Seimbang';
            } else {
                const diff = item.received - totalQc;
                balanceBadge.className = 'qc-balance-badge badge badge-light-danger fs-9 py-0.5';
                balanceBadge.textContent = diff > 0 ? `Kurang ${diff}` : `Lebih ${Math.abs(diff)}`;
            }

            // Validasi Anti-Rugi & Margin
            const salePrice = parseNumber(row.querySelector('.input-sale-price').value);
            const marginBadge = row.querySelector('.margin-status-badge');
            const profit = salePrice - hppFinalPerBase;

            if (salePrice <= hppFinalPerBase || hppFinalPerBase <= 0) {
                hasAntiLossViolation = true;
                marginBadge.className = 'margin-status-badge badge badge-danger text-white fs-9 py-0.5 fw-bold';
                marginBadge.textContent = 'Dilarang di bawah HPP!';
            } else {
                const marginPercent = ((profit / salePrice) * 100).toFixed(1);
                marginBadge.className = 'margin-status-badge badge badge-light-success text-success fs-9 py-0.5 fw-bold';
                marginBadge.textContent = `+Rp ${formatRupiah(profit)} (+${marginPercent}%)`;
            }
        });

        // Step 3: Update Ringkasan Finansial Bawah
        const totalInvoice = grandPurchaseValueAccepted + totalLandedCost;
        document.getElementById('summary-total-received').textContent = `${formatRupiah(grandTotalReceived)} pcs`;
        document.getElementById('summary-total-accepted').textContent = `${formatRupiah(grandTotalAccepted)} pcs`;
        document.getElementById('summary-total-nonaccepted').textContent = `${formatRupiah(grandTotalDamaged + grandTotalRejected + grandTotalReturned)} pcs`;
        document.getElementById('summary-subtotal-cost').textContent = `Rp ${formatRupiah(grandPurchaseValueAccepted)}`;
        document.getElementById('summary-total-landed').textContent = `Rp ${formatRupiah(totalLandedCost)}`;
        document.getElementById('summary-total-invoice').textContent = `Rp ${formatRupiah(totalInvoice)}`;

        // Button Posting State
        const btnPost = document.getElementById('btn-submit-post');
        if (btnPost) {
            btnPost.disabled = hasAntiLossViolation;
            if (hasAntiLossViolation) {
                btnPost.classList.add('opacity-50');
                btnPost.title = 'Terdapat harga jual minimum yang lebih rendah atau sama dengan HPP!';
            } else {
                btnPost.classList.remove('opacity-50');
                btnPost.title = '';
            }
        }
    };

    // Submit Posting dengan Konfirmasi
    window.submitReceiptPost = function() {
        const form = document.getElementById('goods-receipt-form');
        if (!form) return;

        // Cek validasi anti-rugi
        recalculateAll();
        const btnPost = document.getElementById('btn-submit-post');
        if (btnPost && btnPost.disabled) {
            Swal.fire({
                title: 'Harga Jual di Bawah Modal!',
                text: 'Terdapat produk dengan harga jual minimum yang lebih rendah atau sama dengan HPP Final. Harap sesuaikan harga sebelum posting.',
                icon: 'error',
                confirmButtonText: 'Periksa Kembali'
            });
            return;
        }

        Swal.fire({
            title: 'Konfirmasi Posting Stok',
            text: 'Posting penerimaan akan langsung memperbarui stok fisik di lokasi, membuat batch FIFO baru, dan mengaktifkan harga jual minimum untuk kasir POS. Lanjutkan?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Posting Stok',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'btn btn-success fw-bold',
                cancelButton: 'btn btn-light'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const hiddenAction = document.createElement('input');
                hiddenAction.type = 'hidden';
                hiddenAction.name = 'action';
                hiddenAction.value = 'post';
                form.appendChild(hiddenAction);
                form.submit();
            }
        });
    };

    // Submit Tambah Produk Cepat via AJAX
    window.handleQuickProductSubmit = function(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-save-quick-product');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';

        const payload = {
            name: document.getElementById('quick-prod-name').value.trim(),
            category_id: document.getElementById('quick-prod-category').value,
            base_unit_id: document.getElementById('quick-prod-unit').value,
            sku: document.getElementById('quick-prod-sku').value.trim(),
            barcode: document.getElementById('quick-prod-barcode').value.trim(),
            _token: '{{ csrf_token() }}'
        };

        fetch('{{ route("warehouse.goods-receipts.store-product-ajax") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        })
        .then(async res => {
            const data = await res.json();
            if (!res.ok) {
                const errMsg = data.errors ? Object.values(data.errors).flat().join('<br>') : (data.message || 'Gagal menyimpan produk.');
                throw new Error(errMsg);
            }
            return data;
        })
        .then(result => {
            btn.disabled = false;
            btn.innerHTML = '<i class="ki-outline ki-check fs-6 me-1"></i> Simpan Produk';

            if (result.success && result.product) {
                const p = result.product;
                masterProducts.push({
                    id: p.id,
                    name: p.name,
                    sku: p.sku,
                    barcode: p.barcode || '',
                    base_unit_id: p.base_unit_id,
                    base_unit_name: p.base_unit_name,
                    cost_price: 0,
                    minimum_price: 0,
                    category_name: p.category_name,
                    units: [{
                        unit_id: p.base_unit_id,
                        unit_name: p.base_unit_name,
                        conversion_factor: 1
                    }]
                });

                // Tutup Modal
                const modalEl = document.getElementById('modal-add-product');
                const modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) modalInstance.hide();

                // Reset Form Modal
                document.getElementById('form-quick-add-product').reset();

                // Tambahkan Baris Baru dengan Produk Tersebut
                addNewProductRow(p);

                Swal.fire({
                    title: 'Produk Berhasil Ditambahkan!',
                    text: `Produk ${p.name} telah masuk ke baris penerimaan barang.`,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="ki-outline ki-check fs-6 me-1"></i> Simpan Produk';
            Swal.fire({
                title: 'Gagal Menambah Produk',
                html: err.message,
                icon: 'error',
                confirmButtonText: 'Tutup'
            });
        });
    };

    // Format Input Rupiah saat Mengetik
    document.addEventListener('input', function(e) {
        if (e.target.matches('.format-rupiah')) {
            const raw = parseNumber(e.target.value);
            e.target.value = formatRupiah(raw);
            recalculateAll();
        }
    });

    // Inisialisasi Halaman
    document.addEventListener('DOMContentLoaded', function() {
        if (initialReceiptItems.length > 0) {
            initialReceiptItems.forEach(item => addNewProductRow(item));
        } else {
            addNewProductRow(); // Tambah 1 baris default
        }

        const freightInput = document.getElementById('input-freight-cost');
        if (freightInput) freightInput.addEventListener('input', recalculateAll);

        const addCostInput = document.getElementById('input-additional-cost');
        if (addCostInput) addCostInput.addEventListener('input', recalculateAll);
    });

})();
</script>
@endpush
