@extends('layouts.metronic.app')

@section('title', 'Buat Transfer Stok - ' . config('app.name'))
@section('page_title', 'Form dan Persetujuan Transfer')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-transfer-create" title="Panduan Form Transfer Stok">
        <x-slot:function>
            <p>Form ini digunakan untuk transfer formal ke gudang atau cabang lain melalui persetujuan, reservasi stok, pengambilan, pengemasan, pengiriman, dan penerimaan.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Pilih lokasi kerja sumber dan tujuan.</li><li>Sistem hanya menampilkan zona/rak/bin yang sesuai.</li><li>Tambahkan produk sebanyak yang diperlukan.</li><li>Simpan sebagai rancangan atau ajukan untuk persetujuan.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Jumlah Diminta:</strong> jumlah yang diajukan.</li><li><strong>Jumlah Disetujui:</strong> usulan jumlah yang akan diperiksa kembali oleh pemberi persetujuan.</li><li><strong>Lokasi Ambil:</strong> zona/rak/bin sumber.</li><li><strong>Lokasi Tujuan:</strong> zona/rak/bin tujuan.</li><li><strong>Tambah Produk:</strong> menambahkan baris tanpa batas tetap lima produk.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Rancangan dan pengajuan belum mengurangi stok. Setelah disetujui, stok sumber dialokasikan. Stok fisik sumber baru keluar saat transfer dikirim.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Pilih sumber dan tujuan.</li><li>Pilih lokasi default bila diperlukan.</li><li>Isi minimal satu produk dan jumlahnya.</li><li>Klik <strong>Simpan Rancangan</strong> atau <strong>Ajukan Persetujuan</strong>.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0">Sumber dan tujuan tidak boleh sama. Zona/rak/bin sumber dan tujuan harus sesuai dengan lokasi kerja yang dipilih.</div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Untuk perpindahan langsung antarbin tanpa persetujuan dan pengiriman, gunakan menu <strong>Transfer Antar Lokasi Internal</strong>.</p>
        </x-slot:example>
    </x-metronic.page-guide>
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

    <div class="alert alert-light-primary border border-primary border-dashed d-flex align-items-start gap-3">
        <i class="ki-outline ki-information-5 fs-2 text-primary"></i>
        <div>
            <div class="fw-bold text-gray-900 mb-1">Pilih jenis transfer yang tepat</div>
            <div class="text-gray-700"><strong>Transfer Antar Lokasi Internal</strong> memindahkan stok langsung dan membuat perubahan keluar/masuk tanpa persetujuan, pengemasan, pengiriman, atau penerimaan. <strong>Transfer Stok</strong> adalah proses formal antar gudang/cabang dengan seluruh tahapan tersebut.</div>
        </div>
    </div>

    <x-metronic.card title="Transfer Baru">
        <form method="POST" action="{{ route('warehouse.stock-transfers.store') }}" id="stock-transfer-form">
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

            <div class="separator my-6"></div>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div><div class="fw-bold fs-5">Daftar Produk</div><div class="text-muted fs-7">Tambahkan seluruh produk yang akan dipindahkan.</div></div>
                <button type="button" class="btn btn-sm btn-light-primary" id="add-transfer-item"><i class="ki-outline ki-plus fs-5"></i> Tambah Produk</button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th class="min-w-200px">Produk</th><th>Jumlah Diminta</th><th>Jumlah Disetujui</th><th class="min-w-180px">Lokasi Ambil</th><th class="min-w-180px">Lokasi Tujuan</th><th>Catatan</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody id="transfer-items">
                        @foreach($initialItems as $index => $item)
                            <tr class="transfer-item-row">
                                <td><select name="items[{{ $index }}][product_id]" class="form-select form-select-sm product-select" data-control="select2" required><option value="">Pilih produk</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((int)($item['product_id'] ?? 0) === $product->id)>{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></td>
                                <td><input name="items[{{ $index }}][quantity_requested]" type="number" step="1" min="1" value="{{ $item['quantity_requested'] ?? 1 }}" class="form-control form-control-sm" required></td>
                                <td><input name="items[{{ $index }}][quantity_approved]" type="number" step="1" min="0" value="{{ $item['quantity_approved'] ?? 1 }}" class="form-control form-control-sm"></td>
                                <td><select name="items[{{ $index }}][source_warehouse_location_id]" class="form-select form-select-sm source-bin-select" data-control="select2" data-selected="{{ $item['source_warehouse_location_id'] ?? '' }}"><option value="">Gunakan default</option>@if($selectedWarehouseLocations->has((int)($item['source_warehouse_location_id'] ?? 0)))<option value="{{ $item['source_warehouse_location_id'] }}" selected>{{ $selectedWarehouseLocations->get((int)$item['source_warehouse_location_id'])->full_code }}</option>@endif</select></td>
                                <td><select name="items[{{ $index }}][destination_warehouse_location_id]" class="form-select form-select-sm destination-bin-select" data-control="select2" data-selected="{{ $item['destination_warehouse_location_id'] ?? '' }}"><option value="">Gunakan default</option>@if($selectedWarehouseLocations->has((int)($item['destination_warehouse_location_id'] ?? 0)))<option value="{{ $item['destination_warehouse_location_id'] }}" selected>{{ $selectedWarehouseLocations->get((int)$item['destination_warehouse_location_id'])->full_code }}</option>@endif</select></td>
                                <td><input name="items[{{ $index }}][notes]" value="{{ $item['notes'] ?? '' }}" class="form-control form-control-sm"></td>
                                <td class="text-end"><button type="button" class="btn btn-sm btn-icon btn-light-danger remove-transfer-item" title="Hapus produk"><i class="ki-outline ki-trash fs-5"></i></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($errors->any())<div class="alert alert-danger">Periksa kembali form. Minimal satu produk harus diisi dan lokasi harus sesuai dengan sumber atau tujuan.</div>@endif
            <div class="d-flex justify-content-end gap-3"><button name="action" value="draft" class="btn btn-light">Simpan Rancangan</button><button name="action" value="submit" class="btn btn-primary">Ajukan Persetujuan</button></div>
        </form>
    </x-metronic.card>

    <template id="transfer-item-template">
        <tr class="transfer-item-row">
            <td><select name="items[__INDEX__][product_id]" class="form-select form-select-sm product-select" data-control="select2" required><option value="">Pilih produk</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></td>
            <td><input name="items[__INDEX__][quantity_requested]" type="number" step="1" min="1" value="1" class="form-control form-control-sm" required></td>
            <td><input name="items[__INDEX__][quantity_approved]" type="number" step="1" min="0" value="1" class="form-control form-control-sm"></td>
            <td><select name="items[__INDEX__][source_warehouse_location_id]" class="form-select form-select-sm source-bin-select" data-control="select2"><option value="">Gunakan default</option></select></td>
            <td><select name="items[__INDEX__][destination_warehouse_location_id]" class="form-select form-select-sm destination-bin-select" data-control="select2"><option value="">Gunakan default</option></select></td>
            <td><input name="items[__INDEX__][notes]" class="form-control form-control-sm"></td>
            <td class="text-end"><button type="button" class="btn btn-sm btn-icon btn-light-danger remove-transfer-item" title="Hapus produk"><i class="ki-outline ki-trash fs-5"></i></button></td>
        </tr>
    </template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const optionsUrl = @json(route('warehouse.stock-transfers.location-options'));
    const body = document.getElementById('transfer-items');
    const source = document.getElementById('source-work-location');
    const destination = document.getElementById('destination-work-location');
    let nextIndex = body.querySelectorAll('.transfer-item-row').length;

    const resetSelect2 = (select) => {
        if (window.$?.fn?.select2 && window.$(select).hasClass('select2-hidden-accessible')) window.$(select).select2('destroy');
    };
    const initialize = (root) => window.GudangTokoSelect2?.initialize(root);
    const loadBins = async (select, workLocationId, context, selected = '') => {
        resetSelect2(select);
        select.innerHTML = `<option value="">${select.closest('td') ? 'Gunakan default' : 'Tanpa zona/rak/bin khusus'}</option>`;
        select.disabled = !workLocationId;
        if (workLocationId) {
            try {
                const response = await window.appFetch(`${optionsUrl}?work_location_id=${encodeURIComponent(workLocationId)}&context=${context}`);
                if (!response.ok) throw new Error('Gagal memuat lokasi');
                const payload = await response.json();
                payload.results.forEach((item) => select.add(new Option(item.text, item.id, false, String(item.id) === String(selected))));
            } catch (error) {
                select.innerHTML = '<option value="">Lokasi tidak dapat dimuat</option>';
            }
        }
        initialize(select);
    };
    const refreshGroup = (context) => {
        const workLocationId = context === 'source' ? source.value : destination.value;
        document.querySelectorAll(`.${context}-bin-select`).forEach((select) => loadBins(select, workLocationId, context, select.dataset.selected || ''));
    };
    const bindRow = (row) => {
        row.querySelector('.remove-transfer-item').addEventListener('click', () => {
            if (body.querySelectorAll('.transfer-item-row').length === 1) return;
            row.remove();
        });
        initialize(row);
        loadBins(row.querySelector('.source-bin-select'), source.value, 'source');
        loadBins(row.querySelector('.destination-bin-select'), destination.value, 'destination');
    };

    source.addEventListener('change', () => {
        document.querySelectorAll('.source-bin-select').forEach((select) => { select.dataset.selected = ''; });
        refreshGroup('source');
    });
    destination.addEventListener('change', () => {
        document.querySelectorAll('.destination-bin-select').forEach((select) => { select.dataset.selected = ''; });
        refreshGroup('destination');
    });
    document.getElementById('add-transfer-item').addEventListener('click', () => {
        const template = document.getElementById('transfer-item-template').innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        body.insertAdjacentHTML('beforeend', template);
        bindRow(body.lastElementChild);
    });
    body.querySelectorAll('.transfer-item-row').forEach((row) => {
        row.querySelector('.remove-transfer-item').addEventListener('click', () => {
            if (body.querySelectorAll('.transfer-item-row').length > 1) row.remove();
        });
    });
    refreshGroup('source');
    refreshGroup('destination');
});
</script>
@endpush
