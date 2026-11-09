@extends('layouts.metronic.app')

@section('title', 'Import Stok Produk - ' . config('app.name'))
@section('page_title', 'Import Stok Produk')

@section('toolbar_actions')
    <a href="{{ route('warehouse.stocks.index') }}" class="btn btn-light me-2">
        <i class="ki-outline ki-cube-2 fs-2 me-1"></i> Lihat Saldo Stok
    </a>
    <a href="{{ route('warehouse.opening-stocks.index') }}" class="btn btn-light">
        <i class="ki-outline ki-folder-added fs-2 me-1"></i> Pendataan Stok Lokasi
    </a>
@endsection

@section('page_guide')
    <x-metronic.page-guide id="warehouse-opening-stock-import" title="Panduan Import Stok Produk Excel">
        <x-slot:function>
            <p>Halaman ini digunakan untuk mengunggah saldo stok fisik produk dan nilai HPP secara massal menggunakan file Excel (.xlsx) ke lokasi induk (Gudang atau Cabang/Toko) yang ditentukan.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol>
                <li><strong>Langkah 1:</strong> Pilih lokasi induk tujuan (Gudang atau Cabang/Toko) terlebih dahulu.</li>
                <li>Periksa daftar kode rak/bin aktif yang tersedia di lokasi induk tersebut sebagai referensi pengisian.</li>
                <li><strong>Langkah 2:</strong> Download template Excel resmi dengan format 5 kolom: <code>SKU Produk</code>, <code>Kode Rak/Bin (Opsional)</code>, <code>Jumlah Stok Awal</code>, <code>HPP</code>, dan <code>Alasan</code>.</li>
                <li>Isi berkas Excel lalu unggah dan klik tombol <strong>Preview Validasi</strong>.</li>
                <li>Periksa tabel hasil verifikasi. Jika semua baris valid, klik <strong>Commit ke Database</strong> untuk menyimpan saldo stok secara permanen.</li>
            </ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul>
                <li><strong>Pilihan Tipe Lokasi:</strong> filter cepat untuk memilih antara entitas Gudang atau Toko/Cabang.</li>
                <li><strong>Lokasi Induk Tujuan:</strong> gudang atau toko tempat saldo stok fisik dicatatkan.</li>
                <li><strong>Referensi Kode Rak/Bin:</strong> daftar rak fisik aktif yang dapat diisi pada kolom opsional Excel.</li>
                <li><strong>Tabel Mockup Template:</strong> pratinjau struktur kolom file Excel sebelum pengisian.</li>
                <li><strong>Tabel Preview Verifikasi:</strong> menampilkan hasil validasi sebelum data benar-benar disimpan ke database.</li>
            </ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Setelah commit, saldo fisik stok produk langsung diperbarui (<em>on hand</em>), transaksi mutasi penyesuaian (<em>adjust</em>) dicatat pada <em>InventoryService</em>, dan histori HPP modal persediaan diperbarui.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol>
                <li>Tentukan lokasi induk tujuan pada Langkah 1.</li>
                <li>Unduh template Excel jika belum memiliki formatnya.</li>
                <li>Pilih file .xlsx yang sudah diisi lalu klik tombol Preview Validasi.</li>
                <li>Bila validasi berhasil, klik tombol Commit ke Database.</li>
            </ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0">
                <ul class="mb-0">
                    <li>SKU produk harus sudah terdaftar aktif di master produk.</li>
                    <li>Jika kolom <em>Kode Rak/Bin (Opsional)</em> diisi, kodenya harus sesuai dengan rak yang terdaftar pada lokasi induk yang dipilih. Kosongkan jika stok dicatat sebagai stok umum tanpa rak khusus.</li>
                    <li>Nilai kuantitas stok dan HPP wajib bernilai 0 atau positif (tidak boleh minus).</li>
                </ul>
            </div>
        </x-slot:warnings>
        <x-slot:example>
            <p>SKU: <code>BRG-001</code>, Rak: <code>BIN-KASIR</code> (atau kosong), Qty: <code>50</code>, HPP: <code>15000</code>, Alasan: <code>Saldo Awal Toko</code>.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    @if (session('import_success_work_location_id'))
        <div class="alert alert-dismissible bg-light-success d-flex flex-column flex-sm-row p-5 mb-6 border border-success">
            <i class="ki-outline ki-check-circle fs-2hx text-success me-4 mb-5 mb-sm-0"></i>
            <div class="d-flex flex-column pe-0 pe-sm-10 justify-content-center">
                <h4 class="fw-semibold text-success mb-1">Import Stok Berhasil Disimpan</h4>
                <span>Data saldo fisik produk telah tersimpan dan siap digunakan untuk transaksi opname, transfer, maupun penjualan.</span>
            </div>
            <div class="ms-auto d-flex align-items-center mt-3 mt-sm-0">
                <a href="{{ route('warehouse.stocks.index', ['work_location_id' => session('import_success_work_location_id')]) }}" class="btn btn-sm btn-success">
                    <i class="ki-outline ki-eye fs-3 me-1"></i> Cek Saldo Stok Lokasi
                </a>
            </div>
        </div>
    @endif

    <div class="row g-6 mb-6">
        {{-- LANGKAH 1: PILIH LOKASI INDUK --}}
        <div class="col-xl-6">
            <x-metronic.card title="Langkah 1: Pilih Lokasi Induk (Gudang / Cabang)" class="h-100">
                <div class="mb-5">
                    <label class="form-label fw-bold required">1. Pilih Tipe Lokasi</label>
                    <div class="d-flex gap-3">
                        <label class="btn btn-outline btn-outline-dashed btn-active-light-primary d-flex align-items-center flex-grow-1 p-3 cursor-pointer @if(old('location_type', 'warehouse') === 'warehouse') active @endif" id="label-type-warehouse">
                            <input type="radio" name="location_type_toggle" value="warehouse" class="btn-check" @checked(old('location_type', 'warehouse') === 'warehouse')>
                            <i class="ki-outline ki-bank fs-2x me-3 text-primary"></i>
                            <div>
                                <div class="fw-bold text-gray-800">Gudang</div>
                                <div class="text-muted fs-8">Gudang logistik / pusat</div>
                            </div>
                        </label>
                        <label class="btn btn-outline btn-outline-dashed btn-active-light-primary d-flex align-items-center flex-grow-1 p-3 cursor-pointer @if(old('location_type') === 'branch') active @endif" id="label-type-branch">
                            <input type="radio" name="location_type_toggle" value="branch" class="btn-check" @checked(old('location_type') === 'branch')>
                            <i class="ki-outline ki-shop fs-2x me-3 text-primary"></i>
                            <div>
                                <div class="fw-bold text-gray-800">Cabang / Toko</div>
                                <div class="text-muted fs-8">Toko fisik & POS kasir</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="mb-5">
                    <label class="form-label fw-bold required">2. Pilih Gudang atau Cabang Tujuan</label>
                    <select id="select-work-location" class="form-select @error('work_location_id') is-invalid @enderror" data-control="select2" data-placeholder="Pilih lokasi induk...">
                        <option value=""></option>
                        @foreach ($workLocations as $loc)
                            <option value="{{ $loc->id }}"
                                data-type="{{ $loc->type }}"
                                data-name="{{ $loc->name }}"
                                data-code="{{ $loc->code }}"
                                data-type-label="{{ $loc->typeLabel() }}"
                                @selected(old('work_location_id', $preview['extra_data'] ?? null) == $loc->id)>
                                [{{ $loc->typeLabel() }}] {{ $loc->code }} — {{ $loc->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('work_location_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Detail Box Lokasi Terpilih --}}
                <div id="location-info-box" class="border rounded p-4 bg-light-primary d-none">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center">
                            <span class="badge badge-primary me-2" id="info-location-badge">Gudang</span>
                            <span class="fw-bold fs-6 text-gray-800" id="info-location-name">-</span>
                        </div>
                        <span class="badge badge-light-secondary fs-7" id="info-location-code">-</span>
                    </div>

                    <div class="separator my-3"></div>

                    <div class="mb-2">
                        <span class="text-muted fs-7 fw-semibold">Kode Rak / Bin Aktif di Lokasi Ini:</span>
                    </div>
                    <div id="info-location-bins" class="d-flex flex-wrap gap-1 mb-2">
                        {{-- Injected via JS --}}
                    </div>
                    <div class="text-muted fs-8">
                        <i class="ki-outline ki-information-2 text-primary fs-7 me-1"></i>
                        <span>Jika produk disimpan pada rak tertentu, salin salah satu kode rak di atas ke kolom <strong>Kode Rak/Bin (Opsional)</strong>. Jika dikosongkan, stok akan disimpan sebagai saldo umum lokasi.</span>
                    </div>
                </div>

                <div id="location-empty-box" class="alert alert-secondary d-flex align-items-center p-4 mb-0">
                    <i class="ki-outline ki-information-5 fs-2x text-muted me-3"></i>
                    <div class="text-muted fs-7">
                        Silakan pilih lokasi induk di atas terlebih dahulu. Berkas Excel yang Anda unggah akan diterapkan ke lokasi tersebut.
                    </div>
                </div>
            </x-metronic.card>
        </div>

        {{-- LANGKAH 2: FORMAT & UPLOAD EXCEL --}}
        <div class="col-xl-6">
            <x-metronic.card title="Langkah 2: Format & Unggah File Excel" class="h-100">
                <div class="mb-5">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-bold mb-0">Format Kolom Tabel Excel</label>
                        <a href="{{ route('warehouse.opening-stocks.import.template') }}" class="btn btn-sm btn-light-primary">
                            <i class="ki-outline ki-file-down fs-4 me-1"></i> Unduh Template (.xlsx)
                        </a>
                    </div>
                    <div class="text-muted fs-7 mb-3">
                        File Excel (.xlsx) wajib memiliki 5 header kolom persis seperti berikut:
                    </div>

                    {{-- Visual Template Header Mockup --}}
                    <div class="table-responsive border rounded mb-3">
                        <table class="table table-bordered align-middle text-center mb-0" style="background-color: #1a4473; color: white;">
                            <thead>
                                <tr class="fw-bold fs-7">
                                    <th class="py-2 px-3 border-light border-opacity-25">SKU Produk</th>
                                    <th class="py-2 px-3 border-light border-opacity-25">Kode Rak/Bin (Opsional)</th>
                                    <th class="py-2 px-3 border-light border-opacity-25">Jumlah Stok Awal</th>
                                    <th class="py-2 px-3 border-light border-opacity-25">HPP</th>
                                    <th class="py-2 px-3 border-light border-opacity-25">Alasan</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white text-gray-700 fs-8">
                                <tr>
                                    <td class="py-2">PRD-001</td>
                                    <td class="py-2 text-muted">BIN-A1 (atau kosong)</td>
                                    <td class="py-2 fw-semibold">100</td>
                                    <td class="py-2">15000</td>
                                    <td class="py-2">Saldo Awal</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Form Upload & Preview --}}
                <form id="form-import-preview" method="POST" action="{{ route('warehouse.opening-stocks.import.preview') }}" enctype="multipart/form-data" data-no-loading="true">
                    @csrf
                    <input type="hidden" name="work_location_id" id="hidden-work-location-id" value="{{ old('work_location_id', $preview['extra_data'] ?? '') }}">

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Unggah File XLSX</label>
                        <input type="file" name="file" id="input-file-xlsx"
                            class="form-control @error('file') is-invalid @enderror"
                            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            required>
                        @error('file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="form-text">Berkas Excel berformat .xlsx (maksimal 5MB).</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2">
                        <span class="text-muted fs-8" id="upload-status-text">
                            <i class="ki-outline ki-lock fs-6 text-muted me-1"></i> Pilih lokasi induk di Langkah 1 untuk membuka unggahan.
                        </span>
                        <button type="submit" id="btn-submit-preview" class="btn btn-primary" disabled>
                            <i class="ki-outline ki-magnifier fs-2 me-1"></i> Preview Validasi
                        </button>
                    </div>
                </form>
            </x-metronic.card>
        </div>
    </div>

    {{-- HASIL VERIFIKASI PREVIEW --}}
    @if ($preview)
        @php
            $targetLocation = \App\Models\WorkLocation::query()->find($preview['extra_data'] ?? null);
            $previewRows = $preview['rows'] ?? [];
            $displayRows = array_slice($previewRows, 0, 50);
            $totalRows = count($previewRows);
        @endphp

        <x-metronic.card title="Hasil Verifikasi Preview: {{ $preview['label'] ?? 'Stok Produk' }}" class="mb-6">
            {{-- Statistik Banner --}}
            <div class="row g-4 mb-5">
                <div class="col-md-3 col-6">
                    <div class="border rounded p-3 text-center bg-light">
                        <div class="text-muted fs-7">Total Baris</div>
                        <div class="fs-2 fw-bold text-gray-800">{{ $preview['totals']['rows'] ?? 0 }}</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="border rounded p-3 text-center bg-light-success border-success border-opacity-25">
                        <div class="text-muted fs-7">Baris Valid</div>
                        <div class="fs-2 fw-bold text-success">{{ $preview['totals']['valid_rows'] ?? 0 }}</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="border rounded p-3 text-center @if(($preview['totals']['invalid_rows'] ?? 0) > 0) bg-light-danger border-danger border-opacity-25 @else bg-light @endif">
                        <div class="text-muted fs-7">Baris Error</div>
                        <div class="fs-2 fw-bold @if(($preview['totals']['invalid_rows'] ?? 0) > 0) text-danger @else text-gray-400 @endif">
                            {{ $preview['totals']['invalid_rows'] ?? 0 }}
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="border rounded p-3 text-center bg-light-primary border-primary border-opacity-25">
                        <div class="text-muted fs-7">Lokasi Induk Tujuan</div>
                        <div class="fs-5 fw-bold text-primary text-truncate px-2" title="{{ $targetLocation?->name }}">
                            [{{ $targetLocation?->typeLabel() ?? '-' }}] {{ $targetLocation?->name ?? '-' }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Error Alert --}}
            @if ($preview['errors'])
                <div class="alert alert-danger mb-5">
                    <div class="fw-bold mb-2 fs-6">
                        <i class="ki-outline ki-cross-circle fs-4 me-1 text-danger"></i> Ditemukan {{ count($preview['errors']) }} Error Validasi:
                    </div>
                    <ul class="mb-0 ps-4">
                        @foreach ($preview['errors'] as $error)
                            <li class="fs-7 mb-1">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="alert alert-success d-flex align-items-center mb-5">
                    <i class="ki-outline ki-check-circle fs-2x text-success me-3"></i>
                    <div>
                        <strong>Seluruh data baris valid!</strong> Silakan periksa pratinjau data di bawah. Setelah yakin, klik tombol <strong>Commit ke Database</strong> untuk menyimpan saldo stok secara permanen.
                    </div>
                </div>
            @endif

            {{-- Tabel Pratinjau Data --}}
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted fs-7">
                    Menampilkan <strong>{{ count($displayRows) }}</strong> dari <strong>{{ $totalRows }}</strong> baris data.
                </span>
                @if ($totalRows > 50)
                    <span class="badge badge-light-warning fs-8">50 baris pertama ditampilkan di tabel preview; semua baris valid akan tersimpan saat commit.</span>
                @endif
            </div>

            <div class="table-responsive border rounded mb-5">
                <table class="table table-row-dashed table-row-gray-300 align-middle gs-4 gy-3 mb-0">
                    <thead class="bg-light">
                        <tr class="fw-bold text-muted text-uppercase fs-8">
                            <th class="w-50px">No</th>
                            @foreach ($preview['headers'] as $header)
                                <th>{{ $preview['header_labels'][$header] ?? $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="fs-7">
                        @forelse ($displayRows as $idx => $row)
                            <tr>
                                <td class="text-muted">{{ $idx + 1 }}</td>
                                @foreach ($preview['headers'] as $header)
                                    <td>
                                        @if ($header === 'hpp')
                                            Rp {{ number_format((float) ($row[$header] ?? 0), 0, ',', '.') }}
                                        @elseif ($header === 'base_quantity')
                                            <span class="badge badge-light-primary fw-bold">{{ $row[$header] ?? '0' }}</span>
                                        @elseif ($header === 'warehouse_location_code')
                                            @if (filled($row[$header] ?? null))
                                                <span class="badge badge-light-info">{{ $row[$header] }}</span>
                                            @else
                                                <span class="text-muted italic">(Stok Umum)</span>
                                            @endif
                                        @else
                                            {{ $row[$header] ?? '-' }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($preview['headers']) + 1 }}" class="text-center text-muted py-4">
                                    Tidak ada baris data yang terbaca.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Tombol Tindakan Commit / Batal --}}
            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                <form method="POST" action="{{ route('warehouse.opening-stocks.import.cancel') }}">
                    @csrf
                    <button type="submit" class="btn btn-light">
                        <i class="ki-outline ki-cross fs-2 me-1"></i> Batal / Upload Ulang
                    </button>
                </form>

                @if ($preview['errors'] === [])
                    <form method="POST" action="{{ route('warehouse.opening-stocks.import.commit') }}" id="form-commit-stock" data-no-loading="true"
                        onsubmit="return confirm('Apakah Anda yakin ingin menyimpan seluruh saldo stok ini ke database? Mutasi penyesuaian stok akan dicatat secara otomatis.');">
                        @csrf
                        <button type="submit" class="btn btn-success" id="btn-commit-stock">
                            <i class="ki-outline ki-check-circle fs-2 me-1"></i> Commit ke Database Sekarang
                        </button>
                    </form>
                @else
                    <button type="button" class="btn btn-secondary" disabled>
                        Perbaiki Error Terlebih Dahulu Sebelum Commit
                    </button>
                @endif
            </div>
        </x-metronic.card>
    @endif

    {{-- Modal Progress Bar Import Saldo Stok --}}
    <x-metronic.import-progress-modal 
        id="stock-import-progress-modal" 
        title="Sedang Memproses Saldo Stok..." 
        subtitle="Mohon menunggu, sistem sedang memverifikasi dan mencatat saldo stok persediaan." 
        icon="ki-cube-2" />
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const binsByLocation = @json($binsByLocation);
    const radioWarehouse = document.querySelector('input[name="location_type_toggle"][value="warehouse"]');
    const radioBranch = document.querySelector('input[name="location_type_toggle"][value="branch"]');
    const labelWarehouse = document.getElementById('label-type-warehouse');
    const labelBranch = document.getElementById('label-type-branch');
    const selectLocation = document.getElementById('select-work-location');
    const hiddenLocationInput = document.getElementById('hidden-work-location-id');
    const locationInfoBox = document.getElementById('location-info-box');
    const locationEmptyBox = document.getElementById('location-empty-box');
    const infoBadge = document.getElementById('info-location-badge');
    const infoName = document.getElementById('info-location-name');
    const infoCode = document.getElementById('info-location-code');
    const infoBins = document.getElementById('info-location-bins');
    const inputFile = document.getElementById('input-file-xlsx');
    const btnSubmit = document.getElementById('btn-submit-preview');
    const statusText = document.getElementById('upload-status-text');

    function getSelectedType() {
        return radioBranch && radioBranch.checked ? 'branch' : 'warehouse';
    }

    function filterOptionsByType() {
        const type = getSelectedType();
        const currentVal = selectLocation.value;
        let hasCurrent = false;

        // Toggle label styling
        if (type === 'branch') {
            labelBranch?.classList.add('active');
            labelWarehouse?.classList.remove('active');
        } else {
            labelWarehouse?.classList.add('active');
            labelBranch?.classList.remove('active');
        }

        // Show/hide options
        Array.from(selectLocation.options).forEach(opt => {
            if (!opt.value) return;
            const optType = opt.getAttribute('data-type');
            if (optType === type) {
                opt.hidden = false;
                opt.disabled = false;
                if (opt.value === currentVal) hasCurrent = true;
            } else {
                opt.hidden = true;
                opt.disabled = true;
            }
        });

        // Re-init select2 if available
        if ($(selectLocation).data('select2')) {
            $(selectLocation).select2('destroy');
            $(selectLocation).select2({ placeholder: 'Pilih ' + (type === 'branch' ? 'cabang / toko...' : 'gudang...') });
        }

        if (!hasCurrent) {
            selectLocation.value = '';
            $(selectLocation).val('').trigger('change.select2');
            updateLocationDetails();
        }
    }

    function updateLocationDetails() {
        const selectedId = selectLocation.value;
        hiddenLocationInput.value = selectedId;

        if (!selectedId) {
            locationInfoBox.classList.add('d-none');
            locationEmptyBox.classList.remove('d-none');
            btnSubmit.disabled = true;
            statusText.innerHTML = '<i class="ki-outline ki-lock fs-6 text-muted me-1"></i> Pilih lokasi induk di Langkah 1 untuk membuka unggahan.';
            return;
        }

        const selectedOption = selectLocation.options[selectLocation.selectedIndex];
        const name = selectedOption?.getAttribute('data-name') || '-';
        const code = selectedOption?.getAttribute('data-code') || '-';
        const typeLabel = selectedOption?.getAttribute('data-type-label') || 'Lokasi';

        infoBadge.textContent = typeLabel;
        infoBadge.className = 'badge ' + (selectedOption?.getAttribute('data-type') === 'branch' ? 'badge-success' : 'badge-primary') + ' me-2';
        infoName.textContent = name;
        infoCode.textContent = code;

        // Populate bins
        const bins = binsByLocation[selectedId] || [];
        infoBins.innerHTML = '';
        if (bins.length > 0) {
            bins.forEach(b => {
                const chip = document.createElement('span');
                chip.className = 'badge badge-light-primary border border-primary border-opacity-25 fs-8 py-1 px-2';
                chip.textContent = b.full_code || b.code;
                chip.title = b.name;
                infoBins.appendChild(chip);
            });
        } else {
            const noBin = document.createElement('span');
            noBin.className = 'text-muted fs-8 italic';
            noBin.textContent = 'Belum ada rak/bin khusus terdaftar pada lokasi ini (stok akan dicatat sebagai stok umum).';
            infoBins.appendChild(noBin);
        }

        locationEmptyBox.classList.add('d-none');
        locationInfoBox.classList.remove('d-none');

        // Enable preview button if file is chosen
        checkUploadReadiness();
    }

    function checkUploadReadiness() {
        const hasLocation = !!selectLocation.value;
        const hasFile = inputFile.files && inputFile.files.length > 0;

        btnSubmit.disabled = !hasLocation || !hasFile;

        if (!hasLocation) {
            statusText.innerHTML = '<i class="ki-outline ki-lock fs-6 text-warning me-1"></i> Pilih lokasi induk di Langkah 1 terlebih dahulu.';
        } else if (!hasFile) {
            statusText.innerHTML = '<i class="ki-outline ki-file fs-6 text-primary me-1"></i> Lokasi dipilih: <strong>' + (selectLocation.options[selectLocation.selectedIndex]?.getAttribute('data-name') || '') + '</strong>. Pilih berkas Excel (.xlsx).';
        } else {
            statusText.innerHTML = '<i class="ki-outline ki-check-circle fs-6 text-success me-1"></i> Siap untuk preview validasi.';
        }
    }

    // Event listeners
    radioWarehouse?.addEventListener('change', filterOptionsByType);
    radioBranch?.addEventListener('change', filterOptionsByType);

    $(selectLocation).on('change', function () {
        updateLocationDetails();
        checkUploadReadiness();
    });

    inputFile?.addEventListener('change', checkUploadReadiness);

    // Initial run
    // If a location is preselected, match the toggle radio
    if (selectLocation.value) {
        const selectedOpt = selectLocation.options[selectLocation.selectedIndex];
        const initialType = selectedOpt?.getAttribute('data-type');
        if (initialType === 'branch' && radioBranch) {
            radioBranch.checked = true;
        } else if (radioWarehouse) {
            radioWarehouse.checked = true;
        }
    }
    filterOptionsByType();
    updateLocationDetails();
    checkUploadReadiness();

    // Progress Bar Handling saat Validasi Preview
    const formImport = document.getElementById('form-import-preview');
    if (formImport) {
        formImport.addEventListener('submit', function () {
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Memvalidasi Berkas...';
            }
            if (typeof window.startImportProgress === 'function') {
                window.startImportProgress({
                    modalId: 'stock-import-progress-modal',
                    title: 'Memvalidasi Saldo Stok...',
                    subtitle: 'Sistem sedang membaca berkas Excel dan memverifikasi data stok.',
                    icon: 'ki-magnifier',
                    stages: [
                        { percent: 18, text: 'Mengunggah berkas Excel saldo stok...' },
                        { percent: 45, text: 'Mencocokkan SKU dengan master produk aktif...' },
                        { percent: 68, text: 'Memverifikasi kode rak/bin dan lokasi tujuan...' },
                        { percent: 88, text: 'Menghitung total kuantitas dan HPP persediaan...' },
                        { percent: 96, text: 'Menyiapkan tabel pratinjau verifikasi...' }
                    ]
                });
            }
        });
    }

    // Progress Bar Handling saat Commit ke Database
    const formCommitStock = document.getElementById('form-commit-stock');
    if (formCommitStock) {
        formCommitStock.addEventListener('submit', function () {
            const btnCommit = document.getElementById('btn-commit-stock');
            if (btnCommit) {
                btnCommit.disabled = true;
                btnCommit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan saldo stok...';
            }
            if (typeof window.startImportProgress === 'function') {
                window.startImportProgress({
                    modalId: 'stock-import-progress-modal',
                    title: 'Menyimpan Saldo Stok ke Database...',
                    subtitle: 'Sistem sedang memperbarui saldo stok fisik dan mencatat mutasi penyesuaian persediaan.',
                    icon: 'ki-cube-2',
                    stages: [
                        { percent: 18, text: 'Memulai transaksi mutasi persediaan...' },
                        { percent: 42, text: 'Mencatat saldo fisik (on-hand) per produk & rak...' },
                        { percent: 68, text: 'Membentuk lapisan FIFO (Stock Batch) & riwayat HPP...' },
                        { percent: 88, text: 'Menyinkronkan total nilai persediaan lokasi...' },
                        { percent: 97, text: 'Finalisasi penyimpanan saldo stok...' }
                    ]
                });
            }
        });
    }
});
</script>
@endpush
