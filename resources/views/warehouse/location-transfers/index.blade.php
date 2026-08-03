@extends('layouts.metronic.app')

@section('title', 'Transfer Lokasi - ' . config('app.name'))
@section('page_title', 'Transfer Antar Lokasi Internal')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-location-transfers" title="Panduan Halaman Transfer Lokasi">
        <x-slot:function>
            <p>Halaman ini melakukan transfer stok internal antar lokasi atau gudang. Staff dan Kepala Gudang menggunakannya untuk memindahkan produk secara cepat dengan pencatatan mutasi otomatis. Di sisi kanan terdapat histori transfer yang mencatat semua perpindahan sebelumnya.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Pilih lokasi kerja sumber.</li><li>Pilih zona/rak/bin yang ditampilkan sesuai lokasi sumber.</li><li>Pilih produk yang memiliki stok tersedia pada bin tersebut.</li><li>Pilih lokasi tujuan dan qty yang akan dipindahkan.</li><li>Sistem mencatat mutasi keluar dan masuk secara atomik.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Lokasi Kerja Sumber:</strong> gudang asal pemindahan.</li><li><strong>Zona/Rak/Bin Sumber:</strong> lokasi fisik asal yang disaring berdasarkan lokasi kerja.</li><li><strong>Produk:</strong> hanya produk dengan stok tersedia pada lokasi sumber.</li><li><strong>Lokasi Kerja Tujuan:</strong> gudang tujuan pemindahan.</li><li><strong>Zona/Rak/Bin Tujuan:</strong> lokasi fisik tujuan.</li><li><strong>Qty:</strong> jumlah yang dipindahkan.</li><li><strong>Alasan:</strong> keterangan atau alasan transfer.</li><li><strong>Histori Transfer:</strong> daftar riwayat perpindahan.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Transfer lokasi mengurangi on hand sumber dan menambah on hand tujuan. Kedua perubahan dicatat sebagai mutasi terpisah. Histori transfer tampil pada tabel di sebelah kanan.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Pilih lokasi kerja sumber.</li><li>Pilih zona/rak/bin sumber.</li><li>Cari dan pilih produk yang tersedia.</li><li>Pilih lokasi kerja dan bin tujuan.</li><li>Masukkan qty dan alasan transfer.</li><li>Klik <strong>Proses Transfer</strong>.</li><li>Verifikasi mutasi tampil pada histori transfer.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Pastikan lokasi tujuan sesuai scope akun Anda.</li><li>Jangan memasukkan qty melebihi stok tersedia di sumber.</li><li>Alasan wajib diisi untuk keperluan audit.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Pindahkan 10 unit Kopi Robusta dari Gudang Pusat Bin A-01 ke Cabang Bogor Bin B-02. Alasan: "Restock cabang". Sistem mencatat mutasi keluar -10 pada sumber dan +10 pada tujuan.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <div class="row g-5">
        <div class="col-lg-4">
            <x-metronic.card title="Form Transfer">
                <form method="POST" action="{{ route('warehouse.location-transfers.store') }}">
                    @csrf

                    @error('transfer')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror

                    <x-metronic.form-group name="source_work_location_id" label="Lokasi Kerja Sumber" required>
                        <select id="source_work_location_id"
                                name="source_work_location_id"
                                class="form-select form-select-solid"
                                data-control="select2"
                                data-placeholder="Cari dan pilih lokasi kerja sumber"
                                required>
                            <option value="">Pilih lokasi kerja</option>
                            @foreach ($workLocations as $location)
                                <option value="{{ $location->id }}" @selected(old('source_work_location_id') == $location->id)>{{ $location->code }} — {{ $location->name }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>

                    <x-metronic.form-group name="source_warehouse_location_id" label="Zona/Rak/Bin Sumber" required>
                        <select id="source_warehouse_location_id"
                                name="source_warehouse_location_id"
                                class="form-select form-select-solid"
                                data-control="select2"
                                data-placeholder="Pilih lokasi kerja sumber terlebih dahulu"
                                required
                                @disabled(! old('source_work_location_id'))>
                            <option value="">Pilih zona/rak/bin sumber</option>
                            @if ($selectedSourceWarehouseLocation)
                                <option value="{{ $selectedSourceWarehouseLocation->id }}" selected>{{ $selectedSourceWarehouseLocation->full_code }} — {{ $selectedSourceWarehouseLocation->name }}</option>
                            @endif
                        </select>
                    </x-metronic.form-group>

                    <x-metronic.form-group name="product_id" label="Produk" required>
                        <select id="product_id"
                                name="product_id"
                                class="form-select form-select-solid"
                                data-control="select2"
                                data-placeholder="Pilih zona/rak/bin sumber terlebih dahulu"
                                required
                                @disabled(! old('source_warehouse_location_id'))>
                            <option value="">Pilih produk yang tersedia</option>
                            @if ($selectedProduct)
                                <option value="{{ $selectedProduct->id }}" selected>{{ $selectedProduct->sku }} — {{ $selectedProduct->name }}</option>
                            @endif
                        </select>
                        <div class="form-text">Hanya produk dengan stok tersedia pada lokasi sumber yang dipilih yang akan ditampilkan.</div>
                    </x-metronic.form-group>

                    <x-metronic.form-group name="destination_work_location_id" label="Lokasi Kerja Tujuan" required>
                        <select id="destination_work_location_id"
                                name="destination_work_location_id"
                                class="form-select form-select-solid"
                                data-control="select2"
                                data-placeholder="Cari dan pilih lokasi kerja tujuan"
                                required>
                            <option value="">Pilih lokasi kerja</option>
                            @foreach ($workLocations as $location)
                                <option value="{{ $location->id }}" @selected(old('destination_work_location_id') == $location->id)>{{ $location->code }} — {{ $location->name }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>

                    <x-metronic.form-group name="destination_warehouse_location_id" label="Zona/Rak/Bin Tujuan">
                        <select id="destination_warehouse_location_id"
                                name="destination_warehouse_location_id"
                                class="form-select form-select-solid"
                                data-control="select2"
                                data-placeholder="Pilih lokasi kerja tujuan terlebih dahulu"
                                data-allow-clear="true"
                                @disabled(! old('destination_work_location_id'))>
                            <option value="">Tanpa zona/rak/bin khusus</option>
                            @if ($selectedDestinationWarehouseLocation)
                                <option value="{{ $selectedDestinationWarehouseLocation->id }}" selected>{{ $selectedDestinationWarehouseLocation->full_code }} — {{ $selectedDestinationWarehouseLocation->name }}</option>
                            @endif
                        </select>
                    </x-metronic.form-group>

                    <x-metronic.form-group name="quantity" label="Qty" required>
                        <input name="quantity" type="number" step="1" min="1" value="{{ old('quantity') }}" class="form-control form-control-solid" required>
                    </x-metronic.form-group>

                    <x-metronic.form-group name="reason" label="Alasan" required>
                        <textarea name="reason" class="form-control form-control-solid" rows="3" required>{{ old('reason') }}</textarea>
                    </x-metronic.form-group>

                    <input type="hidden" name="idempotency_key" value="{{ (string) str()->uuid() }}">
                    <button class="btn btn-primary w-100" type="submit">Proses Transfer</button>
                </form>
            </x-metronic.card>
        </div>
        <div class="col-lg-8">
            <x-metronic.card title="Histori Transfer">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Waktu</th><th>Produk</th><th>Jenis</th><th>Lokasi</th><th>Qty</th><th>User</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($transfers as $mutation)
                            <tr>
                                <td>{{ $mutation->occurred_at?->format('d/m/Y H:i') }}</td>
                                <td>{{ $mutation->product?->sku }}<div class="text-muted">{{ $mutation->product?->name }}</div></td>
                                <td>{{ $mutation->mutation_type->label() }}</td>
                                <td>{{ $mutation->warehouseLocation?->full_code ?: $mutation->workLocation?->name }}</td>
                                <td>{{ qty($mutation->quantity_on_hand_change) }}</td>
                                <td>{{ $mutation->actor?->name ?: '-' }}</td>
                                <td class="text-end"><a href="{{ route('warehouse.stock-mutations.show', $mutation) }}" class="btn btn-sm btn-light">Detail</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-metronic.empty-state title="Belum ada transfer lokasi" description="Transfer internal akan menghasilkan mutasi keluar dan masuk." /></td></tr>
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
            const initializeLocationTransferSelects = () => {
                const $ = window.jQuery;

                if (typeof $?.fn?.select2 !== 'function') {
                    return;
                }

                const optionsUrl = @json(route('warehouse.location-transfers.options'));
                const sourceWorkLocation = $('#source_work_location_id');
                const sourceWarehouseLocation = $('#source_warehouse_location_id');
                const product = $('#product_id');
                const destinationWorkLocation = $('#destination_work_location_id');
                const destinationWarehouseLocation = $('#destination_warehouse_location_id');
                const locationCache = new Map();
                const pendingLocationRequests = new Map();
                const productCache = new Map();
                const pendingProductRequests = new Map();

                const resetSelect = (select, placeholder, disabled = true) => {
                    select.empty()
                        .append(new Option(placeholder, '', true, true))
                        .prop('disabled', disabled)
                        .trigger('change');
                };

                const initializeLocalSelect = (select, placeholder) => {
                    if (select.hasClass('select2-hidden-accessible')) {
                        select.select2('destroy');
                    }

                    select.select2({
                        theme: 'bootstrap5',
                        selectionCssClass: ':all:',
                        width: '100%',
                        placeholder,
                        allowClear: select.data('allow-clear') === true,
                        minimumResultsForSearch: 0,
                    });

                    select.attr('data-kt-initialized', '1');
                };

                const fetchAllLocations = (workLocationId) => {
                    const key = String(workLocationId);

                    if (locationCache.has(key)) {
                        return Promise.resolve(locationCache.get(key));
                    }

                    if (pendingLocationRequests.has(key)) {
                        return pendingLocationRequests.get(key);
                    }

                    const request = (async () => {
                        const results = [];
                        let page = 1;
                        let hasMore = false;

                        do {
                            const response = await $.ajax({
                                url: optionsUrl,
                                dataType: 'json',
                                data: {
                                    type: 'locations',
                                    q: '',
                                    page,
                                    per_page: 100,
                                    work_location_id: workLocationId,
                                },
                            });

                            results.push(...response.results);
                            hasMore = response.pagination?.more === true;
                            page += 1;
                        } while (hasMore);

                        locationCache.set(key, results);

                        return results;
                    })();

                    pendingLocationRequests.set(key, request);
                    request.then(
                        () => pendingLocationRequests.delete(key),
                        () => pendingLocationRequests.delete(key),
                    );

                    return request;
                };

                const fetchAllProducts = (workLocationId, warehouseLocationId) => {
                    const key = `${workLocationId}:${warehouseLocationId}`;

                    if (productCache.has(key)) {
                        return Promise.resolve(productCache.get(key));
                    }

                    if (pendingProductRequests.has(key)) {
                        return pendingProductRequests.get(key);
                    }

                    const request = (async () => {
                        const results = [];
                        let page = 1;
                        let hasMore = false;

                        do {
                            const response = await $.ajax({
                                url: optionsUrl,
                                dataType: 'json',
                                data: {
                                    type: 'products',
                                    q: '',
                                    page,
                                    per_page: 100,
                                    work_location_id: workLocationId,
                                    warehouse_location_id: warehouseLocationId,
                                },
                            });

                            results.push(...response.results);
                            hasMore = response.pagination?.more === true;
                            page += 1;
                        } while (hasMore);

                        productCache.set(key, results);

                        return results;
                    })();

                    pendingProductRequests.set(key, request);
                    request.then(
                        () => pendingProductRequests.delete(key),
                        () => pendingProductRequests.delete(key),
                    );

                    return request;
                };

                const loadWarehouseLocations = async (select, workLocationId, options = {}) => {
                    const {
                        emptyLabel = 'Pilih zona/rak/bin',
                        emptyAllowed = false,
                        selectedId = null,
                    } = options;

                    const requestedWorkLocationId = workLocationId ? String(workLocationId) : '';
                    select.data('requested-work-location-id', requestedWorkLocationId);

                    select.empty()
                        .append(new Option('Memuat data lokasi...', '', true, true))
                        .prop('disabled', true)
                        .trigger('change.select2');

                    if (!workLocationId) {
                        resetSelect(select, emptyLabel, true);
                        return;
                    }

                    try {
                        const locations = await fetchAllLocations(requestedWorkLocationId);

                        if (select.data('requested-work-location-id') !== requestedWorkLocationId) {
                            return;
                        }

                        select.empty().append(new Option(emptyLabel, '', !selectedId, !selectedId));

                        locations.forEach((location) => {
                            const selected = String(location.id) === String(selectedId || '');
                            select.append(new Option(location.text, location.id, selected, selected));
                        });

                        select.prop('disabled', !emptyAllowed && locations.length === 0).trigger('change.select2');
                    } catch (error) {
                        if (select.data('requested-work-location-id') !== requestedWorkLocationId) {
                            return;
                        }

                        resetSelect(select, 'Gagal memuat data lokasi', true);
                        console.error('Data zona/rak/bin gagal dimuat.', error);
                    }
                };

                const loadProducts = async (workLocationId, warehouseLocationId, selectedId = null) => {
                    const requestKey = workLocationId && warehouseLocationId
                        ? `${workLocationId}:${warehouseLocationId}`
                        : '';
                    product.data('requested-stock-scope', requestKey);
                    product.empty()
                        .append(new Option('Memuat data produk...', '', true, true))
                        .prop('disabled', true)
                        .trigger('change.select2');

                    if (!requestKey) {
                        resetSelect(product, 'Pilih zona/rak/bin sumber terlebih dahulu', true);
                        return;
                    }

                    try {
                        const products = await fetchAllProducts(workLocationId, warehouseLocationId);

                        if (product.data('requested-stock-scope') !== requestKey) {
                            return;
                        }

                        product.empty().append(new Option('Pilih produk yang tersedia', '', !selectedId, !selectedId));

                        products.forEach((item) => {
                            const selected = String(item.id) === String(selectedId || '');
                            product.append(new Option(item.text, item.id, selected, selected));
                        });

                        product.prop('disabled', products.length === 0).trigger('change.select2');

                        if (products.length === 0) {
                            product.empty()
                                .append(new Option('Tidak ada produk dengan stok tersedia', '', true, true))
                                .prop('disabled', true)
                                .trigger('change.select2');
                        }
                    } catch (error) {
                        if (product.data('requested-stock-scope') !== requestKey) {
                            return;
                        }

                        resetSelect(product, 'Gagal memuat data produk', true);
                        console.error('Data produk sumber gagal dimuat.', error);
                    }
                };

                const initialSourceWarehouseLocationId = sourceWarehouseLocation.val();
                const initialDestinationWarehouseLocationId = destinationWarehouseLocation.val();
                const initialProductId = product.val();

                initializeLocalSelect(sourceWarehouseLocation, 'Cari zona/rak/bin sumber');
                initializeLocalSelect(destinationWarehouseLocation, 'Cari zona/rak/bin tujuan');
                initializeLocalSelect(product, 'Cari produk yang tersedia');

                loadWarehouseLocations(sourceWarehouseLocation, sourceWorkLocation.val(), {
                    emptyLabel: 'Pilih zona/rak/bin sumber',
                    selectedId: initialSourceWarehouseLocationId,
                });
                loadWarehouseLocations(destinationWarehouseLocation, destinationWorkLocation.val(), {
                    emptyLabel: 'Tanpa zona/rak/bin khusus',
                    emptyAllowed: true,
                    selectedId: initialDestinationWarehouseLocationId,
                });
                loadProducts(sourceWorkLocation.val(), initialSourceWarehouseLocationId, initialProductId);

                sourceWorkLocation.on('change.location-transfer', () => {
                    resetSelect(product, 'Pilih produk yang tersedia', true);
                    loadWarehouseLocations(sourceWarehouseLocation, sourceWorkLocation.val(), {
                        emptyLabel: 'Pilih zona/rak/bin sumber',
                    });
                });

                sourceWarehouseLocation.on('change.location-transfer', () => {
                    loadProducts(sourceWorkLocation.val(), sourceWarehouseLocation.val());
                });

                destinationWorkLocation.on('change.location-transfer', () => {
                    loadWarehouseLocations(destinationWarehouseLocation, destinationWorkLocation.val(), {
                        emptyLabel: 'Tanpa zona/rak/bin khusus',
                        emptyAllowed: true,
                    });
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeLocationTransferSelects, { once: true });
            } else {
                initializeLocationTransferSelects();
            }
        })();
    </script>
@endpush
