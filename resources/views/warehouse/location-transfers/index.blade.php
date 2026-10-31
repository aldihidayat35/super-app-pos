@extends('layouts.metronic.app')

@section('title', 'Pindah Rak / Bin Internal - ' . config('app.name'))
@section('page_title', 'Pindah Rak / Bin Internal')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-location-transfers" title="Panduan Pindah Rak / Bin Internal">
        <x-slot:function>
            <p>Halaman ini digunakan untuk memindahkan stok barang antar rak, etalase, atau bin dalam <strong>satu toko atau gudang yang sama</strong> (misalnya dari Gudang Belakang ke Etalase Depan, atau dari Rak A1 ke Rak B2). Pemindahan ini dieksekusi secara instan tanpa birokrasi persetujuan (approval) dan setiap akun bertanggung jawab penuh atas transaksi yang diproses.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol>
                <li>Pilih lokasi kerja (toko atau gudang yang sedang Anda kelola).</li>
                <li>Pilih rak/bin asal dan rak/bin tujuan.</li>
                <li>Pilih satu atau beberapa produk serta tentukan kuantitas yang dipindahkan.</li>
                <li>Klik tombol <strong>Pindahkan Stok Sekarang</strong> untuk mengeksekusi mutasi seketika.</li>
            </ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul>
                <li><strong>Lokasi Kerja:</strong> Toko atau gudang tempat pemindahan berlangsung.</li>
                <li><strong>Rak/Bin Asal:</strong> Lokasi fisik awal penyimpanan barang.</li>
                <li><strong>Rak/Bin Tujuan:</strong> Lokasi fisik tujuan penempatan barang.</li>
                <li><strong>Tabel Produk Multi-Item:</strong> Daftar barang dan kuantitas yang dipindahkan sekaligus.</li>
                <li><strong>Penanggung Jawab:</strong> Akun pengguna yang mengeksekusi transaksi.</li>
            </ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Stok pada rak asal langsung berkurang dan stok pada rak tujuan langsung bertambah saat itu juga tanpa status in-transit.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol>
                <li>Pastikan rak asal dan rak tujuan berbeda.</li>
                <li>Gunakan tombol <strong>+ Tambah Produk</strong> untuk memindahkan beberapa item sekaligus.</li>
                <li>Periksa indikator stok tersedia agar tidak melebihi stok yang ada.</li>
            </ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0">
                <i class="ki-outline ki-information-5 fs-4 me-2"></i>Transaksi pemindahan ini bersifat final dan tercatat langsung di buku besar mutasi stok dengan nama akun Anda sebagai penanggung jawab.
            </div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Memindahkan 5 pcs Kaos Polos dan 3 pcs Celana Jeans dari Rak Belakang ke Etalase Depan Toko.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <div class="row g-5 mb-8">
        <div class="col-12">
            <x-metronic.card title="Form Pindah Rak / Bin Internal">
                <x-slot:toolbar>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted fs-7">Penanggung Jawab:</span>
                        <span class="badge badge-light-primary fw-bold fs-7">
                            <i class="ki-outline ki-user me-1"></i>{{ auth()->user()->name }}
                        </span>
                    </div>
                </x-slot:toolbar>

                <form method="POST" action="{{ route('warehouse.location-transfers.store') }}" id="location-transfer-form">
                    @csrf

                    @error('transfer')
                        <div class="alert alert-danger mb-5 d-flex align-items-center">
                            <i class="ki-outline ki-cross-circle fs-3 me-2 text-danger"></i>
                            <div>{{ $message }}</div>
                        </div>
                    @enderror

                    <!-- Header Lokasi & Rak -->
                    <div class="row g-4 mb-6">
                        <div class="col-md-4">
                            <label class="form-label required fw-bold">Lokasi Kerja (Toko / Gudang)</label>
                            <select id="source_work_location_id"
                                    name="source_work_location_id"
                                    class="form-select form-select-solid"
                                    data-control="select2"
                                    data-placeholder="Pilih lokasi kerja"
                                    required>
                                <option value="">Pilih lokasi kerja</option>
                                @foreach ($workLocations as $location)
                                    <option value="{{ $location->id }}" @selected(old('source_work_location_id', count($workLocations) === 1 ? $workLocations->first()->id : '') == $location->id)>
                                        {{ $location->code }} — {{ $location->name }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="destination_work_location_id" id="destination_work_location_id" value="{{ old('destination_work_location_id', old('source_work_location_id', count($workLocations) === 1 ? $workLocations->first()->id : '')) }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required fw-bold">Rak / Bin Asal (Sumber)</label>
                            <select id="source_warehouse_location_id"
                                    name="source_warehouse_location_id"
                                    class="form-select form-select-solid"
                                    data-control="select2"
                                    data-placeholder="Pilih lokasi kerja terlebih dahulu"
                                    required
                                    @disabled(! old('source_work_location_id', count($workLocations) === 1 ? $workLocations->first()->id : ''))>
                                <option value="">Pilih rak asal</option>
                                @if ($selectedSourceWarehouseLocation)
                                    <option value="{{ $selectedSourceWarehouseLocation->id }}" selected>{{ $selectedSourceWarehouseLocation->full_code }} — {{ $selectedSourceWarehouseLocation->name }}</option>
                                @endif
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required fw-bold">Rak / Bin Tujuan</label>
                            <select id="destination_warehouse_location_id"
                                    name="destination_warehouse_location_id"
                                    class="form-select form-select-solid"
                                    data-control="select2"
                                    data-placeholder="Pilih lokasi kerja terlebih dahulu"
                                    required
                                    @disabled(! old('source_work_location_id', count($workLocations) === 1 ? $workLocations->first()->id : ''))>
                                <option value="">Pilih rak tujuan</option>
                                @if ($selectedDestinationWarehouseLocation)
                                    <option value="{{ $selectedDestinationWarehouseLocation->id }}" selected>{{ $selectedDestinationWarehouseLocation->full_code }} — {{ $selectedDestinationWarehouseLocation->name }}</option>
                                @endif
                            </select>
                        </div>
                    </div>

                    <!-- Tabel Item Multi-Produk -->
                    <div class="separator separator-dashed my-5"></div>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="fw-bolder m-0 text-gray-800">
                            <i class="ki-outline ki-parcel fs-4 text-primary me-2"></i>Daftar Produk yang Dipindahkan
                        </h6>
                        <button type="button" class="btn btn-sm btn-light-primary fw-bold" id="btn-add-item">
                            <i class="ki-outline ki-plus fs-5 me-1"></i>Tambah Produk
                        </button>
                    </div>

                    <div class="table-responsive mb-6">
                        <table class="table align-middle table-row-dashed table-striped" id="items-table">
                            <thead class="bg-light">
                                <tr class="text-muted fw-bold text-uppercase fs-7">
                                    <th class="min-w-300px ps-4">Produk</th>
                                    <th class="min-w-160px text-center">Stok Tersedia</th>
                                    <th class="min-w-140px">Jumlah Pindah</th>
                                    <th class="text-end min-w-80px pe-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="transfer-items-body">
                                <tr class="transfer-row" data-index="0">
                                    <td class="ps-4">
                                        <select name="items[0][product_id]" class="form-select form-select-sm product-select" data-placeholder="Pilih rak asal terlebih dahulu" required disabled>
                                            <option value="">Pilih produk yang tersedia</option>
                                            @if ($selectedProduct)
                                                <option value="{{ $selectedProduct->id }}" selected>{{ $selectedProduct->sku }} — {{ $selectedProduct->name }}</option>
                                            @endif
                                        </select>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-light fw-bolder stock-available-badge">-</span>
                                    </td>
                                    <td>
                                        <input type="number" step="1" min="1" name="items[0][quantity]" class="form-control form-control-sm quantity-input" placeholder="Qty" value="{{ old('items.0.quantity', old('quantity', 1)) }}" required>
                                        <div class="text-danger fs-9 mt-1 d-none stock-warning">Melebihi stok tersedia</div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button type="button" class="btn btn-sm btn-icon btn-light-danger btn-remove-row" title="Hapus"><i class="ki-outline ki-trash fs-5"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Keterangan & Submit -->
                    <div class="row g-4 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Alasan / Catatan Pemindahan</label>
                            <input type="text" name="reason" class="form-control form-control-solid" placeholder="Contoh: Display etalase depan, perapihan rak, dsb." value="{{ old('reason', 'Pindah rak internal') }}">
                        </div>
                        <div class="col-md-4 text-end">
                            <input type="hidden" name="idempotency_key" value="{{ (string) str()->uuid() }}">
                            <button class="btn btn-primary w-100 fw-bold py-3" type="submit" id="btn-submit-transfer">
                                <i class="ki-outline ki-arrow-right-left fs-4 me-1"></i>Pindahkan Stok Sekarang
                            </button>
                        </div>
                    </div>
                </form>
            </x-metronic.card>
        </div>
    </div>

    <!-- Riwayat Pindah Rak Internal -->
    <div class="row g-5">
        <div class="col-12">
            <x-metronic.card title="Riwayat Pindah Rak / Bin Internal">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead>
                            <tr class="text-muted fw-bold text-uppercase fs-7">
                                <th class="ps-4">Waktu</th>
                                <th>Produk</th>
                                <th>Jenis</th>
                                <th>Lokasi / Rak</th>
                                <th>Perubahan Qty</th>
                                <th>Penanggung Jawab</th>
                                <th class="text-end pe-4">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($transfers as $mutation)
                                <tr>
                                    <td class="ps-4 text-gray-800 fw-bold">{{ $mutation->occurred_at?->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <div class="fw-bolder text-gray-800">{{ $mutation->product?->name }}</div>
                                        <div class="text-muted fs-8">{{ $mutation->product?->sku }}</div>
                                    </td>
                                    <td>
                                        @if($mutation->mutation_type->value === 'transfer_out')
                                            <span class="badge badge-light-danger fw-bold fs-8"><i class="ki-outline ki-arrow-up-right me-1"></i>Keluar Rak</span>
                                        @else
                                            <span class="badge badge-light-success fw-bold fs-8"><i class="ki-outline ki-arrow-down-left me-1"></i>Masuk Rak</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-light-primary fw-bold">{{ $mutation->warehouseLocation?->full_code ?: $mutation->workLocation?->name }}</span>
                                    </td>
                                    <td>
                                        <span class="fw-bolder {{ (float)$mutation->quantity_on_hand_change > 0 ? 'text-success' : 'text-danger' }}">
                                            {{ (float)$mutation->quantity_on_hand_change > 0 ? '+' : '' }}{{ qty($mutation->quantity_on_hand_change) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="ki-outline ki-user text-muted me-1 fs-6"></i>
                                            <span class="text-gray-700 fw-bold">{{ $mutation->actor?->name ?: '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="{{ route('warehouse.stock-mutations.show', $mutation) }}" class="btn btn-sm btn-icon btn-light" title="Lihat Mutasi">
                                            <i class="ki-outline ki-eye fs-5"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <x-metronic.empty-state title="Belum ada riwayat pindah rak" description="Aktivitas pemindahan rak atau bin internal yang Anda lakukan akan otomatis tercatat di sini." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $transfers->links() }}
            </x-metronic.card>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const $ = window.jQuery;
    if (typeof $?.fn?.select2 !== 'function') return;

    const optionsUrl = @json(route('warehouse.location-transfers.options'));
    const sourceWorkLocation = $('#source_work_location_id');
    const destinationWorkLocation = $('#destination_work_location_id');
    const sourceWarehouseLocation = $('#source_warehouse_location_id');
    const destinationWarehouseLocation = $('#destination_warehouse_location_id');
    const itemsBody = $('#transfer-items-body');
    const btnAddItem = $('#btn-add-item');
    const btnSubmit = $('#btn-submit-transfer');

    let rowIndex = {{ max(1, count(old('items', []))) }};
    let availableProductsCache = [];

    const loadBins = (workLocationId) => {
        if (!workLocationId) {
            sourceWarehouseLocation.empty().append(new Option('Pilih lokasi kerja terlebih dahulu', '', true, true)).prop('disabled', true).trigger('change');
            destinationWarehouseLocation.empty().append(new Option('Pilih lokasi kerja terlebih dahulu', '', true, true)).prop('disabled', true).trigger('change');
            return;
        }

        $.ajax({
            url: optionsUrl,
            dataType: 'json',
            data: { type: 'locations', work_location_id: workLocationId, per_page: 100 },
            success: (response) => {
                const results = response.results || [];
                sourceWarehouseLocation.empty().append(new Option('Pilih rak asal', '', true, true)).prop('disabled', false);
                destinationWarehouseLocation.empty().append(new Option('Pilih rak tujuan', '', true, true)).prop('disabled', false);

                results.forEach((bin) => {
                    sourceWarehouseLocation.append(new Option(bin.text, bin.id));
                    destinationWarehouseLocation.append(new Option(bin.text, bin.id));
                });
                sourceWarehouseLocation.trigger('change');
                destinationWarehouseLocation.trigger('change');
            }
        });
    };

    const loadProductsInSourceBin = (workLocationId, binId) => {
        if (!workLocationId || !binId) {
            availableProductsCache = [];
            itemsBody.find('.product-select').empty().append(new Option('Pilih rak asal terlebih dahulu', '', true, true)).prop('disabled', true).trigger('change');
            itemsBody.find('.stock-available-badge').text('-');
            return;
        }

        $.ajax({
            url: optionsUrl,
            dataType: 'json',
            data: { type: 'products', work_location_id: workLocationId, warehouse_location_id: binId, per_page: 100 },
            success: (response) => {
                availableProductsCache = response.results || [];
                itemsBody.find('.product-select').each(function() {
                    const select = $(this);
                    const currentVal = select.val();
                    populateProductSelect(select, currentVal);
                });
            }
        });
    };

    const populateProductSelect = (select, selectedId) => {
        select.empty().append(new Option('Pilih produk yang tersedia', '', true, true)).prop('disabled', false);
        availableProductsCache.forEach((prod) => {
            const isSelected = String(prod.id) === String(selectedId);
            const opt = new Option(prod.text, prod.id, isSelected, isSelected);
            opt.dataset.available = prod.available;
            opt.dataset.unit = prod.unit;
            select.append(opt);
        });
        select.trigger('change');
    };

    const initProductSelect2 = (select) => {
        select.select2({
            theme: 'bootstrap5',
            width: '100%',
            placeholder: 'Pilih produk yang tersedia'
        }).on('select2:select', function(e) {
            const row = $(this).closest('.transfer-row');
            updateRowStockInfo(row);
        });
    };

    const updateRowStockInfo = (row) => {
        const select = row.find('.product-select');
        const badge = row.find('.stock-available-badge');
        const qtyInput = row.find('.quantity-input');
        const warning = row.find('.stock-warning');

        const selectedOption = select.find('option:selected')[0];
        if (!selectedOption || !selectedOption.value) {
            badge.text('-').removeClass('badge-light-success badge-light-danger').addClass('badge-light');
            warning.addClass('d-none');
            qtyInput.removeClass('is-invalid');
            validateForm();
            return;
        }

        const available = parseFloat(selectedOption.dataset.available || 0);
        const unit = selectedOption.dataset.unit || 'Pcs';
        badge.text(`${available} ${unit}`).removeClass('badge-light badge-light-danger').addClass('badge-light-success');

        const qty = parseFloat(qtyInput.val() || 0);
        if (qty > available) {
            warning.removeClass('d-none').text(`Maksimal ${available} ${unit}`);
            qtyInput.addClass('is-invalid border-danger');
            badge.removeClass('badge-light-success').addClass('badge-light-danger');
        } else {
            warning.addClass('d-none');
            qtyInput.removeClass('is-invalid border-danger');
        }
        validateForm();
    };

    const validateForm = () => {
        let isValid = true;
        const sourceBin = sourceWarehouseLocation.val();
        const destBin = destinationWarehouseLocation.val();

        if (!sourceBin || !destBin || String(sourceBin) === String(destBin)) {
            isValid = false;
        }

        itemsBody.find('.transfer-row').each(function() {
            const row = $(this);
            const prod = row.find('.product-select').val();
            const qtyInput = row.find('.quantity-input');
            const qty = parseFloat(qtyInput.val() || 0);
            if (!prod || qty <= 0 || qtyInput.hasClass('is-invalid')) {
                isValid = false;
            }
        });

        btnSubmit.prop('disabled', !isValid);
    };

    sourceWorkLocation.on('change', function() {
        const locId = $(this).val();
        destinationWorkLocation.val(locId);
        loadBins(locId);
    });

    sourceWarehouseLocation.on('change', function() {
        const binId = $(this).val();
        loadProductsInSourceBin(sourceWorkLocation.val(), binId);
        validateForm();
    });

    destinationWarehouseLocation.on('change', function() {
        validateForm();
    });

    btnAddItem.on('click', function() {
        const newRow = $(`
            <tr class="transfer-row" data-index="${rowIndex}">
                <td class="ps-4">
                    <select name="items[${rowIndex}][product_id]" class="form-select form-select-sm product-select" required>
                    </select>
                </td>
                <td class="text-center">
                    <span class="badge badge-light fw-bolder stock-available-badge">-</span>
                </td>
                <td>
                    <input type="number" step="1" min="1" name="items[${rowIndex}][quantity]" class="form-control form-control-sm quantity-input" placeholder="Qty" value="1" required>
                    <div class="text-danger fs-9 mt-1 d-none stock-warning">Melebihi stok tersedia</div>
                </td>
                <td class="text-end pe-4">
                    <button type="button" class="btn btn-sm btn-icon btn-light-danger btn-remove-row" title="Hapus"><i class="ki-outline ki-trash fs-5"></i></button>
                </td>
            </tr>
        `);

        itemsBody.append(newRow);
        const newSelect = newRow.find('.product-select');
        populateProductSelect(newSelect, '');
        initProductSelect2(newSelect);
        rowIndex++;
        validateForm();
    });

    itemsBody.on('click', '.btn-remove-row', function() {
        if (itemsBody.find('.transfer-row').length > 1) {
            $(this).closest('.transfer-row').remove();
            validateForm();
        } else {
            if (typeof toastr !== 'undefined') toastr.warning('Minimal harus ada 1 produk.');
        }
    });

    itemsBody.on('input change', '.quantity-input', function() {
        const row = $(this).closest('.transfer-row');
        updateRowStockInfo(row);
    });

    // Inisialisasi awal
    initProductSelect2(itemsBody.find('.product-select'));
    if (sourceWorkLocation.val()) {
        if (!sourceWarehouseLocation.find('option:not([value=""])').length) {
            loadBins(sourceWorkLocation.val());
        } else if (sourceWarehouseLocation.val()) {
            loadProductsInSourceBin(sourceWorkLocation.val(), sourceWarehouseLocation.val());
        }
    }
})();
</script>
@endpush
