@extends('layouts.metronic.app')

@section('title', 'Stok Opname - ' . config('app.name'))
@section('page_title', 'Stok Opname')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-opnames" title="Panduan Halaman Stok Opname">
        <x-slot:function>
            <p>Halaman ini digunakan untuk menjadwalkan, memulai, dan memantau penghitungan fisik persediaan. Kepala Gudang menetapkan penanggung jawab, metode, cakupan, dan batas toleransi.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Buat jadwal opname dan pilih penanggung jawab.</li><li>Klik Simpan Acuan Stok untuk menyimpan kondisi sistem saat ini.</li><li>Petugas melakukan penghitungan fisik.</li><li>Hasil fisik dibandingkan dengan acuan stok.</li><li>Hasil diajukan untuk persetujuan.</li><li>Selisih dan laporan tersedia sebelum penyesuaian saldo diselesaikan.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
              <ul><li><strong>Gudang/Cabang:</strong> lokasi penghitungan.</li><li><strong>Zona/Rak/Bin:</strong> cakupan lokasi fisik.</li><li><strong>Metode Manual:</strong> petugas mengetik hasil hitung.</li><li><strong>Metode Scan:</strong> pilihan alur; pemindaian barcode khusus belum terintegrasi penuh dan input masih dilakukan pada halaman penghitungan.</li><li><strong>Metode Import:</strong> hasil dimasukkan melalui CSV.</li><li><strong>PIC:</strong> penanggung jawab opname.</li><li><strong>Batas Toleransi:</strong> batas yang memicu pemeriksaan/persetujuan lebih tinggi.</li><li><strong>Simpan Acuan Stok:</strong> menyimpan kondisi sistem sebagai pembanding.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Acuan stok menyimpan jumlah sistem sebagai pembanding. Persetujuan belum mengubah stok; mutasi penyesuaian baru dibuat setelah proses penyelesaian.</p>
           </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Pilih gudang/cabang, lokasi, dan kategori.</li><li>Pilih metode yang sesuai.</li><li>Tentukan penanggung jawab dan batas toleransi.</li><li>Atur apakah stok sistem disembunyikan dan transaksi dibekukan.</li><li>Klik <strong>Simpan Acuan Stok</strong>.</li><li>Pantau kemajuan dari daftar opname.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Mode objektif menyembunyikan stok sistem dari petugas penghitung.</li><li>Pembekuan transaksi menghentikan perubahan stok pada cakupan aktif.</li><li>Batas nilai dapat memicu persetujuan Owner.</li><li>Cakupan tidak dapat diubah setelah acuan stok disimpan.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Opname Manual di Gudang Pusat pada 18/07/2025. PIC: Budi. Threshold qty: 10, threshold nilai: Rp 1.000.000. Blind count aktif. Snapshot diambil dan counter mulai menghitung fisik.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const $ = window.jQuery;
            const workLocation = document.getElementById('work_location_id');
            const warehouseLocation = document.getElementById('warehouse_location_id');

            if (!$ || typeof $.fn.select2 !== 'function' || !workLocation || !warehouseLocation) {
                return;
            }

            const $warehouseLocation = $(warehouseLocation);
            const warehouseLocationOptions = Array.from(warehouseLocation.options)
                .filter((option) => option.value !== '')
                .map((option) => option.cloneNode(true));

            const initializeWarehouseLocation = (preserveSelection = false) => {
                const workLocationId = workLocation.value;
                const selectedValue = preserveSelection ? warehouseLocation.value : '';

                if ($warehouseLocation.hasClass('select2-hidden-accessible')) {
                    $warehouseLocation.select2('destroy');
                }

                warehouseLocation.replaceChildren();
                warehouseLocation.add(new Option(
                    workLocationId ? 'Semua zona/rak/bin' : 'Pilih gudang/cabang terlebih dahulu',
                    '',
                    selectedValue === '',
                    selectedValue === '',
                ));

                warehouseLocationOptions
                    .filter((option) => option.dataset.workLocationId === workLocationId)
                    .forEach((option) => warehouseLocation.add(option.cloneNode(true)));

                warehouseLocation.value = Array.from(warehouseLocation.options)
                    .some((option) => option.value === selectedValue)
                    ? selectedValue
                    : '';

                warehouseLocation.disabled = !workLocationId;

                $warehouseLocation.select2({
                    theme: 'bootstrap5',
                    width: '100%',
                    selectionCssClass: ':all:',
                    placeholder: workLocationId ? 'Semua zona/rak/bin' : 'Pilih gudang/cabang terlebih dahulu',
                    allowClear: true,
                    minimumInputLength: 0,
                    language: {
                        errorLoading: () => 'Data tidak dapat dimuat.',
                        loadingMore: () => 'Memuat data berikutnya...',
                        noResults: () => 'Zona/Rak/Bin tidak ditemukan.',
                        searching: () => 'Mencari data...',
                    },
                });
            };

            initializeWarehouseLocation(true);
            $(workLocation).on('change.stock-opname-location', () => initializeWarehouseLocation(false));
        });
    </script>
@endpush

@section('content')
    <x-metronic.page-title title="Stok Opname" description="Jadwalkan, simpan acuan stok, hitung fisik, periksa selisih, dan lakukan penyesuaian melalui persetujuan." />

    <div class="row g-6">
        <div class="col-lg-4">
            <x-metronic.card title="Jadwalkan Opname">
                <form method="POST" action="{{ route('warehouse.stock-opnames.store') }}">
                    @csrf
                    <x-metronic.form-group name="work_location_id" label="Gudang/Cabang" required>
                        <select id="work_location_id"
                                name="work_location_id"
                                class="form-select form-select-solid"
                                data-control="select2"
                                data-searchable-fallback="true"
                                data-placeholder="Cari dan pilih gudang/cabang"
                                required>
                            <option value="">Pilih lokasi kerja</option>
                            @foreach($workLocations as $location)
                                <option value="{{ $location->id }}" @selected(old('work_location_id') == $location->id)>{{ $location->code }} — {{ $location->name }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>
                    <x-metronic.form-group name="warehouse_location_id" label="Zona/Rak/Bin">
                        <select id="warehouse_location_id"
                                name="warehouse_location_id"
                                class="form-select form-select-solid"
                                data-control="select2"
                                data-searchable-fallback="true"
                                data-placeholder="{{ old('work_location_id') ? 'Semua zona/rak/bin' : 'Pilih gudang/cabang terlebih dahulu' }}"
                                data-allow-clear="true"
                                @disabled(! old('work_location_id'))>
                            <option value="">{{ old('work_location_id') ? 'Semua zona/rak/bin' : 'Pilih gudang/cabang terlebih dahulu' }}</option>
                            @foreach($warehouseLocations as $location)
                                <option value="{{ $location->id }}"
                                        data-work-location-id="{{ $location->warehouse?->work_location_id }}"
                                        @selected(old('warehouse_location_id') == $location->id)>{{ $location->full_code }} — {{ $location->name }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>
                    <x-metronic.form-group name="category_id" label="Kategori Produk">
                        <select id="category_id"
                                name="category_id"
                                class="form-select form-select-solid"
                                data-control="select2"
                                data-searchable-fallback="true"
                                data-placeholder="Cari kategori produk"
                                data-allow-clear="true">
                            <option value="">Semua kategori</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>
                    <div class="row">
                        <div class="col-md-6">
                            <x-metronic.form-group name="method" label="Metode" required>
                                <select name="method" class="form-select form-select-solid" required>
                                    <option value="manual" @selected(old('method') === 'manual')>Manual</option>
                                    <option value="scan" @selected(old('method') === 'scan')>Scan (pemindaian khusus belum terintegrasi)</option>
                                    <option value="import" @selected(old('method') === 'import')>Import CSV</option>
                                </select>
                                <div class="form-text">Manual: ketik hasil. Scan: saat ini tetap memakai form penghitungan. Import: unggah file CSV.</div>
                            </x-metronic.form-group>
                        </div>
                        <div class="col-md-6">
                            <x-metronic.form-group name="scheduled_at" label="Tanggal">
                                <input type="date" name="scheduled_at" value="{{ old('scheduled_at', now()->toDateString()) }}" class="form-control form-control-solid">
                            </x-metronic.form-group>
                        </div>
                    </div>
                    <x-metronic.form-group name="pic_user_id" label="PIC (Penanggung Jawab Opname)" help="Pengguna yang bertanggung jawab mengawasi pelaksanaan stok opname.">
                        <select id="pic_user_id"
                                name="pic_user_id"
                                class="form-select form-select-solid"
                                data-control="select2"
                                data-searchable-fallback="true"
                                data-placeholder="Cari dan pilih PIC"
                                data-allow-clear="true">
                            <option value="">Gunakan pembuat</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected(old('pic_user_id') == $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>
                    <div class="row">
                        <div class="col-md-6"><x-metronic.form-group name="threshold_qty" label="Batas Toleransi Jumlah" help="Batas selisih jumlah barang yang memerlukan perhatian atau persetujuan lebih tinggi."><input type="number" step="1" min="0" name="threshold_qty" value="{{ old('threshold_qty', '10') }}" class="form-control form-control-solid"></x-metronic.form-group></div>
                        <div class="col-md-6"><x-metronic.form-group name="threshold_value_display" label="Batas Toleransi Nilai Kerugian" help="Batas nilai rupiah dari selisih stok yang memerlukan persetujuan lebih tinggi."><div class="input-group input-group-solid"><span class="input-group-text">Rp</span><input type="text" value="{{ old('threshold_value', '1000000') }}" class="form-control" data-currency-input data-currency-target="#threshold_value"></div><input type="hidden" id="threshold_value" name="threshold_value" value="{{ old('threshold_value', '1000000') }}"></x-metronic.form-group></div>
                    </div>
                    <label class="form-check form-switch form-check-custom form-check-solid mb-3">
                        <input type="hidden" name="blind_count" value="0">
                        <input class="form-check-input" type="checkbox" name="blind_count" value="1" @checked(old('blind_count'))>
                        <span class="form-check-label"><strong>Sembunyikan Stok Sistem dari Petugas Penghitung</strong><span class="d-block text-muted fs-7">Petugas menghitung barang berdasarkan kondisi fisik tanpa melihat angka pada sistem.</span></span>
                    </label>
                    <label class="form-check form-switch form-check-custom form-check-solid mb-5">
                        <input type="hidden" name="freeze_stock" value="0">
                        <input class="form-check-input" type="checkbox" name="freeze_stock" value="1" @checked(old('freeze_stock'))>
                        <span class="form-check-label"><strong>Bekukan Transaksi Stok Selama Penghitungan</strong><span class="d-block text-muted fs-7">Transaksi stok pada cakupan opname akan diblokir sementara agar jumlah tidak berubah selama penghitungan.</span></span>
                    </label>
                    <x-metronic.form-group name="notes" label="Catatan">
                        <textarea name="notes" rows="3" class="form-control form-control-solid">{{ old('notes') }}</textarea>
                    </x-metronic.form-group>
                    <div class="d-flex gap-2">
                        <button name="action" value="draft" class="btn btn-light-primary">Simpan Rancangan</button>
                        <button name="action" value="start" class="btn btn-primary" data-bs-toggle="tooltip" title="Menyimpan kondisi stok sistem saat ini sebagai angka pembanding awal opname.">Simpan Acuan Stok</button>
                    </div>
                </form>
            </x-metronic.card>
        </div>

        <div class="col-lg-8">
            <x-metronic.card title="Daftar Opname">
                <form class="row g-3 mb-5">
                    <div class="col-md-5">
                        <select id="filter_work_location_id"
                                name="work_location_id"
                                class="form-select form-select-solid"
                                data-control="select2"
                                data-searchable-fallback="true"
                                data-placeholder="Cari gudang/cabang"
                                data-allow-clear="true">
                            <option value="">Semua gudang/cabang</option>
                            @foreach($workLocations as $location)
                                <option value="{{ $location->id }}" @selected(($filters['work_location_id'] ?? '') == $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select name="status" class="form-select form-select-solid">
                            <option value="">Semua status</option>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3"><button class="btn btn-light w-100">Filter</button></div>
                </form>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>No</th><th>Scope</th><th>Jadwal/PIC</th><th>Progress</th><th>Selisih</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                        <tbody>
                        @forelse($opnames as $opname)
                            <tr>
                                <td class="fw-bold">{{ $opname->number }}<div class="text-muted fs-8">{{ ucfirst($opname->method) }}</div></td>
                                <td>{{ $opname->workLocation?->name }}<div class="text-muted fs-8">{{ $opname->warehouseLocation?->full_code ?: 'Semua bin' }}</div></td>
                                <td>{{ $opname->scheduled_at?->format('d/m/Y') ?: '-' }}<div class="text-muted fs-8">{{ $opname->pic?->name ?: '-' }}</div></td>
                                <td>{{ $opname->countedProgress() }}<div class="text-muted fs-8">{{ $opname->items->whereNotNull('counted_qty')->count() }}/{{ $opname->items->count() }} item</div></td>
                                <td>{{ qty($opname->total_difference_qty) }}<div class="text-muted fs-8">{{ \App\Support\CurrencyFormatter::rupiah($opname->total_difference_value) }}</div></td>
                                <td><x-metronic.status-badge :status="$opname->status" /></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-light" href="{{ route('warehouse.stock-opnames.show', $opname) }}">Detail</a>
                                    @can('count', $opname)<a class="btn btn-sm btn-light-primary" href="{{ route('warehouse.stock-opnames.count', $opname) }}">Counting</a>@endcan
                                    <a class="btn btn-sm btn-light-info" href="{{ route('warehouse.stock-opnames.variance', $opname) }}">Variance</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-metronic.empty-state title="Belum ada stok opname" description="Buat jadwal opname pertama untuk mulai snapshot saldo." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $opnames->links() }}
            </x-metronic.card>
        </div>
    </div>
@endsection
