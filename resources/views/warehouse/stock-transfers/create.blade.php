@extends('layouts.metronic.app')

@section('title', 'Kirim Transfer Antar Cabang - ' . config('app.name'))
@section('page_title', 'Form dan Persetujuan Transfer')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-transfer-create" title="Panduan Kirim Transfer Antar Cabang">
        <x-slot:function>
            <p>Form ini digunakan untuk membuat dan mengirim transfer stok antar cabang atau gudang secara langsung. Alur persetujuan telah ditiadakan; pembuat dokumen bertindak sebagai penanggung jawab pengirim dan barang langsung berstatus <em>Dalam Perjalanan (In-Transit)</em> untuk kemudian diverifikasi dan diterima di cabang tujuan.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol>
                <li>Pilih lokasi kerja asal (pengirim) dan cabang tujuan.</li>
                <li>Tambahkan satu atau beberapa produk serta tentukan kuantitas transfer.</li>
                <li>Pilih lokasi rak asal jika diperlukan (atau biarkan otomatis / FIFO).</li>
                <li>Klik tombol <strong>Kirim Sekarang (Dalam Perjalanan)</strong> untuk langsung mengirim barang.</li>
            </ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul>
                <li><strong>Lokasi Asal & Tujuan:</strong> Cabang pengirim dan cabang penerima (harus berbeda).</li>
                <li><strong>Tabel Produk:</strong> Produk, jumlah transfer, dan rak ambil opsional.</li>
                <li><strong>Penanggung Jawab:</strong> Akun Anda yang bertanggung jawab atas pengiriman ini.</li>
            </ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Pengiriman langsung memotong stok on-hand cabang asal dan mencatat stok dalam perjalanan hingga dikonfirmasi terima di cabang tujuan.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol>
                <li>Pastikan stok di cabang asal mencukupi.</li>
                <li>Klik <strong>Kirim Sekarang</strong> untuk langsung menerbitkan status In-Transit.</li>
                <li>Gunakan <strong>Simpan Draft</strong> jika transfer masih ingin disiapkan terlebih dahulu.</li>
            </ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0">Lokasi asal dan tujuan tidak boleh sama. Setiap akun bertanggung jawab penuh atas data yang diinput.</div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Gudang Pusat mengirim 10 Box ke Toko Cabang Kedai Awal.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <a href="{{ route('warehouse.stock-transfers.index') }}" class="btn btn-light">
        <i class="ki-outline ki-arrow-left fs-5 me-1"></i>Kembali ke Daftar
    </a>
@endsection

@section('content')
    @php
        $initialItems = array_values(old('items', [[
            'product_id' => '',
            'quantity_requested' => '1',
            'quantity_approved' => '1',
            'source_warehouse_location_id' => '',
            'destination_warehouse_location_id' => '',
            'notes' => '',
        ]]));
        $sourceId = old('source_work_location_id', $workLocations->first()?->id);
        $destinationId = old('destination_work_location_id');
    @endphp

    <x-metronic.card title="Form Pengiriman Transfer Antar Cabang">
        <x-slot:toolbar>
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted fs-7">Penanggung Jawab Pengirim:</span>
                <span class="badge badge-light-primary fw-bold fs-7">
                    <i class="ki-outline ki-user me-1"></i>{{ auth()->user()->name }}
                </span>
            </div>
        </x-slot:toolbar>
        <form method="POST" action="{{ route('warehouse.stock-transfers.store') }}" id="stock-transfer-form" data-options-url="{{ route('warehouse.stock-transfers.location-options') }}">
            @csrf
            <div class="row g-4">
                <div class="col-md-3">
                    <label class="form-label required">Lokasi Kerja Sumber</label>
                    <select name="source_work_location_id" id="source-work-location" class="form-select form-select-solid" data-control="select2" required>
                        @foreach($workLocations as $location)<option value="{{ $location->id }}" @selected((int) $sourceId === $location->id)>{{ $location->typeLabel() }} — {{ $location->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label required">Lokasi Kerja Tujuan</label>
                    <select name="destination_work_location_id" id="destination-work-location" class="form-select form-select-solid" data-control="select2" required>
                        <option value="">Pilih tujuan</option>
                        @foreach($allWorkLocations as $location)<option value="{{ $location->id }}" @selected((int) $destinationId === $location->id)>{{ $location->typeLabel() }} — {{ $location->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Lokasi Ambil Default</label>
                    <select name="source_warehouse_location_id" class="form-select form-select-solid source-bin-select" data-control="select2" data-selected="{{ old('source_warehouse_location_id') }}">
                        <option value="">Tanpa zona/rak/bin khusus</option>
                        @if($selectedWarehouseLocations->has((int) old('source_warehouse_location_id')))<option value="{{ old('source_warehouse_location_id') }}" selected>{{ $selectedWarehouseLocations->get((int) old('source_warehouse_location_id'))->full_code }}</option>@endif
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Lokasi Tujuan Default</label>
                    <select name="destination_warehouse_location_id" class="form-select form-select-solid destination-bin-select" data-control="select2" data-selected="{{ old('destination_warehouse_location_id') }}">
                        <option value="">Tanpa zona/rak/bin khusus</option>
                        @if($selectedWarehouseLocations->has((int) old('destination_warehouse_location_id')))<option value="{{ old('destination_warehouse_location_id') }}" selected>{{ $selectedWarehouseLocations->get((int) old('destination_warehouse_location_id'))->full_code }}</option>@endif
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label required">Tanggal</label><input type="date" name="transfer_date" value="{{ old('transfer_date', now()->toDateString()) }}" class="form-control form-control-solid" required></div>
                <div class="col-md-9"><label class="form-label">Catatan</label><input name="notes" value="{{ old('notes') }}" class="form-control form-control-solid"></div>
            </div>

            <div id="transfer-location-feedback" class="alert alert-light-danger mt-4 d-none" role="alert"></div>

            <div class="separator my-6"></div>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div><div class="fw-bold fs-5">Daftar Produk</div><div class="text-muted fs-7">Tambahkan seluruh produk yang akan dipindahkan.</div></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-primary" id="btn-catalog-modal" data-bs-toggle="modal" data-bs-target="#visualCatalogModal">
                        <i class="ki-outline ki-element-11 fs-5"></i> Pilih dari Katalog
                    </button>
                    <button type="button" class="btn btn-sm btn-light-primary" id="add-transfer-item">
                        <i class="ki-outline ki-plus fs-5"></i> Tambah Baris
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr class="text-muted fw-bold text-uppercase fs-7">
                            <th class="min-w-250px">Produk & Satuan</th>
                            <th class="min-w-150px">Jumlah Transfer</th>
                            <th class="min-w-200px">Lokasi Ambil (Opsional)</th>
                            <th class="text-end min-w-80px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="transfer-items">
                        @foreach($initialItems as $index => $item)
                            <tr class="transfer-item-row">
                                <td>
                                    <select name="items[{{ $index }}][product_id]" class="form-select form-select-sm product-select" data-searchable="false" required>
                                        <option value="">Pilih produk</option>
                                        @if($selectedProducts->has((int)($item['product_id'] ?? 0)))
                                            <option value="{{ $item['product_id'] }}" selected>{{ $selectedProducts->get((int)$item['product_id'])->sku }} — {{ $selectedProducts->get((int)$item['product_id'])->name }}</option>
                                        @endif
                                    </select>
                                    <div class="product-stock-preview badge badge-light-info mt-1 d-none"></div>
                                    <div class="d-flex align-items-center gap-2 mt-2">
                                        <label class="fs-8 text-muted mb-0">Satuan:</label>
                                        <select name="items[{{ $index }}][unit_id]" class="form-select form-select-sm unit-select w-auto">
                                            <option value="{{ $item['unit_id'] ?? '' }}">Satuan dasar</option>
                                        </select>
                                    </div>
                                    <div class="unit-conversion-preview text-muted fs-9 mt-1"></div>
                                </td>
                                <td>
                                    <input name="items[{{ $index }}][quantity_requested]" type="number" step="any" min="0.0001" value="{{ $item['quantity_requested'] ?? $item['quantity'] ?? 1 }}" class="form-control form-control-sm quantity-input" required>
                                    <input type="hidden" name="items[{{ $index }}][quantity_approved]" class="quantity-approved-input" value="{{ $item['quantity_approved'] ?? $item['quantity_requested'] ?? $item['quantity'] ?? 1 }}">
                                    <div class="stock-warning-feedback text-danger fs-8 mt-1 d-none"></div>
                                </td>
                                <td>
                                    <select name="items[{{ $index }}][source_warehouse_location_id]" class="form-select form-select-sm source-bin-select" data-control="select2" data-selected="{{ $item['source_warehouse_location_id'] ?? '' }}">
                                        <option value="">Otomatis / Default</option>
                                        @if($selectedWarehouseLocations->has((int)($item['source_warehouse_location_id'] ?? 0)))
                                            <option value="{{ $item['source_warehouse_location_id'] }}" selected>{{ $selectedWarehouseLocations->get((int)$item['source_warehouse_location_id'])->full_code }}</option>
                                        @endif
                                    </select>
                                    <div class="text-muted fs-9 mt-1">Kosongkan untuk alokasi otomatis (FIFO)</div>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-icon btn-light-danger remove-transfer-item" title="Hapus produk"><i class="ki-outline ki-trash fs-5"></i></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($errors->any())<div class="alert alert-danger">Periksa kembali form. Minimal satu produk harus diisi dan lokasi harus sesuai dengan sumber atau tujuan.</div>@endif
            <div class="d-flex justify-content-end gap-3">
                <button type="submit" name="action" value="draft" class="btn btn-light fw-bold">
                    <i class="ki-outline ki-file me-1"></i>Simpan Draft
                </button>
                <button type="submit" name="action" value="ship" class="btn btn-primary fw-bold">
                    <i class="ki-outline ki-send fs-4 me-1"></i>Kirim Sekarang (Dalam Perjalanan)
                </button>
            </div>
        </form>
    </x-metronic.card>

    <template id="transfer-item-template">
        <tr class="transfer-item-row">
            <td>
                <select name="items[__INDEX__][product_id]" class="form-select form-select-sm product-select" data-searchable="false" required>
                    <option value="">Pilih produk</option>
                </select>
                <div class="product-stock-preview badge badge-light-info mt-1 d-none"></div>
                <div class="d-flex align-items-center gap-2 mt-2">
                    <label class="fs-8 text-muted mb-0">Satuan:</label>
                    <select name="items[__INDEX__][unit_id]" class="form-select form-select-sm unit-select w-auto">
                        <option value="">Satuan dasar</option>
                    </select>
                </div>
                <div class="unit-conversion-preview text-muted fs-9 mt-1"></div>
            </td>
            <td>
                <input name="items[__INDEX__][quantity_requested]" type="number" step="any" min="0.0001" value="1" class="form-control form-control-sm quantity-input" required>
                <input type="hidden" name="items[__INDEX__][quantity_approved]" class="quantity-approved-input" value="1">
                <div class="stock-warning-feedback text-danger fs-8 mt-1 d-none"></div>
            </td>
            <td>
                <select name="items[__INDEX__][source_warehouse_location_id]" class="form-select form-select-sm source-bin-select" data-control="select2">
                    <option value="">Otomatis / Default</option>
                </select>
                <div class="text-muted fs-9 mt-1">Kosongkan untuk alokasi otomatis (FIFO)</div>
            </td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-icon btn-light-danger remove-transfer-item" title="Hapus produk"><i class="ki-outline ki-trash fs-5"></i></button>
            </td>
        </tr>
    </template>

    <x-metronic.visual-catalog-modal 
        id="visualCatalogModal" 
        data-url="{{ route('warehouse.stock-transfers.location-options') }}" 
        on-select="handleCatalogSelect" 
    />
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnCatalog = document.getElementById('btn-catalog-modal');
    btnCatalog.addEventListener('click', function() {
        // Set dynamic URL params based on current selected locations
        const sourceLoc = document.getElementById('source-work-location').value;
        const sourceBin = document.querySelector('select[name="source_warehouse_location_id"]')?.value || '';
        
        const params = {
            work_location_id: sourceLoc,
            context: 'product',
            per_page: 100 // fetch more for the catalog
        };
        if (sourceBin) {
            params.warehouse_location_id = sourceBin;
        }
        
        btnCatalog.dataset.urlParams = JSON.stringify(params);
    });
});

function handleCatalogSelect(product) {
    const btn = document.getElementById('add-transfer-item');
    btn.click(); // Add a new row
    
    // Find the newly added row
    const items = document.querySelectorAll('.transfer-item-row');
    const newRow = items[items.length - 1];
    
    // Set the product select value
    const select = newRow.querySelector('.product-select');
    if (select) {
        // Since it's select2 AJAX, create option element
        const displayText = product.text || (product.sku + ' — ' + product.name);
        const option = new Option(displayText, product.id, true, true);
        window.$(select).append(option).trigger('change');
        window.$(select).trigger({
            type: 'select2:select',
            params: { data: product }
        });
    }
    
    if (typeof toastr !== 'undefined') {
        toastr.success(product.name + ' ditambahkan ke daftar transfer.');
    }
}
</script>
@endpush
