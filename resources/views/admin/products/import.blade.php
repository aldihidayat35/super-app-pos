@extends('layouts.metronic.app')
@section('title', 'Import Produk')
@section('page_title', 'Import Katalog Produk')

@section('page_guide')
    <x-metronic.page-guide id="admin-products-import" title="Panduan Halaman Import Produk">
        <x-slot:function>
            <p class="mb-2"><strong>Fungsi utama halaman ini:</strong> Halaman untuk mengimpor (menambahkan/memperbarui) data produk secara massal dari file CSV atau Excel (.xlsx). Admin cukup mengunggah file berisi daftar produk, lalu sistem melakukan validasi dan menampilkan preview interaktif sebelum data disimpan permanen ke database.</p>
            <p class="mb-0"><strong>Catatan Penting:</strong> Hanya kolom <strong>Nama Produk</strong> yang wajib diisi. Kolom SKU, Barcode, Kategori, Merek, dan Satuan bersifat opsional (sistem otomatis mengisi nilai default yang aman jika dikosongkan).</p>
        </x-slot:function>
        <x-slot:workflow>
            <p class="mb-2"><strong>Alur integrasi setelah import:</strong></p>
            <ul class="mb-0">
                <li><strong>Katalog Produk:</strong> Produk yang berhasil diimpor langsung muncul di daftar master produk.</li>
                <li><strong>Quick Setup:</strong> Setelah import selesai, Anda dapat langsung menuju ke halaman Quick Setup untuk melengkapi HPP, stok awal toko/gudang, dan ring harga jual.</li>
                <li><strong>Dukungan Barcode & SKU:</strong> SKU dibuat otomatis berformat unik. Jika menyertakan barcode, barcode akan didaftarkan sebagai identitas pemindaian kasir/POS.</li>
            </ul>
        </x-slot:workflow>
        <x-slot:parts>
            <ol class="mb-0">
                <li><strong>Langkah 1:</strong> Download template spreadsheet (format .xlsx 2-sheet) dan pelajari panduan kolom.</li>
                <li><strong>Langkah 2:</strong> Tarik & lepaskan (drag and drop) file Anda lalu klik "Validasi & Preview Data".</li>
                <li><strong>Langkah 3:</strong> Tinjau hasil validasi. Gunakan tab filter (Semua, Valid, Error) untuk memastikan tidak ada kesalahan data, lalu klik "Konfirmasi & Simpan Import".</li>
            </ol>
        </x-slot:parts>
        <x-slot:impacts>
            <ul class="mb-0">
                <li><strong>Produk Baru:</strong> Dibuat otomatis dengan status aktif.</li>
                <li><strong>Produk Lama (SKU Cocok):</strong> Diperbarui (UPSERT) tanpa menghapus histori transaksi sebelumnya.</li>
                <li><strong>Atomic Transaction:</strong> Proses penyimpanan berjalan dalam 1 transaksi database yang aman.</li>
            </ul>
        </x-slot:impacts>
        <x-slot:operation>
            <ol class="mb-0">
                <li>Download template Excel melalui tombol di Langkah 1.</li>
                <li>Buka template dan isi baris data produk Anda (cukup isi nama produk jika hanya itu yang Anda miliki).</li>
                <li>Upload file pada area Langkah 2 dan tinjau tabel preview.</li>
                <li>Jika terdapat error, tabel akan menandai baris merah beserta detail kesalahannya. Perbaiki file dan upload ulang.</li>
                <li>Jika semua valid, klik "Konfirmasi & Simpan Import".</li>
            </ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0">
                <ul class="mb-0">
                    <li>Gunakan format <code>.xlsx</code> atau <code>.csv</code> UTF-8.</li>
                    <li>Pastikan tidak ada barcode duplikat pada file Anda atau bertabrakan dengan produk lain yang sudah ada.</li>
                    <li>Jika kode kategori/satuan diisi manual, pastikan kode tersebut terdaftar di sistem (lihat Sheet 2 pada template Excel).</li>
                </ul>
            </div>
        </x-slot:warnings>
        <x-slot:example>
            <p class="mb-0"><strong>Contoh Skenario:</strong> Anda memiliki 100 nama barang dari supplier tanpa SKU dan kategori. Anda cukup memasukkan nama barang ke kolom <code>name</code> dan mengosongkan kolom lainnya. Sistem akan otomatis membuatkan SKU unik, menetapkan kategori 'Umum', dan satuan 'PCS' untuk seluruh 100 produk tersebut dalam hitungan detik.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <a href="{{ route('admin.products.import.template') }}" class="btn btn-sm btn-primary">
        <i class="ki-outline ki-file-down fs-4 me-1"></i> Download Template Excel
    </a>
    <a href="{{ route('admin.products.export') }}" class="btn btn-sm btn-light-primary">
        <i class="ki-outline ki-exit-up fs-4 me-1"></i> Export Data Produk
    </a>
    <a href="{{ route('admin.products.index') }}" class="btn btn-sm btn-light">
        <i class="ki-outline ki-arrow-left fs-4 me-1"></i> Kembali ke Daftar Produk
    </a>
@endsection

@section('content')

{{-- ======================================================== --}}
{{-- KARTU HASIL SUKSES POST-COMMIT (JIKA SELESAI IMPORT)      --}}
{{-- ======================================================== --}}
@if(is_array($result))
    <div class="card border border-success border-dashed bg-light-success mb-8">
        <div class="card-body p-6 p-lg-10">
            <div class="d-flex flex-column flex-md-row align-items-center gap-6">
                <div class="d-flex align-items-center justify-content-center bg-success text-white rounded-circle shadow-sm flex-shrink-0" style="width: 72px; height: 72px;">
                    <i class="ki-outline ki-check fs-2hx text-white"></i>
                </div>
                <div class="flex-grow-1 text-center text-md-start">
                    <h2 class="text-gray-900 fw-bolder mb-2">Import Katalog Produk Berhasil Disimpan!</h2>
                    <p class="text-gray-600 fs-6 mb-4">
                        Data produk dari file Anda telah sukses diproses dan disimpan ke dalam database. Berikut adalah ringkasan hasilnya:
                    </p>
                    <div class="d-flex flex-wrap gap-4 justify-content-center justify-content-md-start mb-6">
                        <div class="bg-white rounded-3 px-5 py-3 border border-success border-opacity-25 shadow-xs">
                            <span class="fs-8 text-muted text-uppercase fw-bold d-block">Produk Baru Ditambahkan</span>
                            <span class="fs-2hx fw-bolder text-success">{{ number_format($result['created'] ?? 0) }}</span>
                        </div>
                        <div class="bg-white rounded-3 px-5 py-3 border border-primary border-opacity-25 shadow-xs">
                            <span class="fs-8 text-muted text-uppercase fw-bold d-block">Produk Diperbarui</span>
                            <span class="fs-2hx fw-bolder text-primary">{{ number_format($result['updated'] ?? 0) }}</span>
                        </div>
                        <div class="bg-white rounded-3 px-5 py-3 border border-gray-300 shadow-xs">
                            <span class="fs-8 text-muted text-uppercase fw-bold d-block">Total Baris Diproses</span>
                            <span class="fs-2hx fw-bolder text-gray-800">{{ number_format(($result['created'] ?? 0) + ($result['updated'] ?? 0)) }}</span>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-md-start">
                        <a href="{{ route('admin.products.quick-setup.index', ['completeness' => 'all']) }}" class="btn btn-success btn-lg">
                            <i class="ki-outline ki-setting-4 fs-3 me-2"></i> Lanjut Setup HPP, Stok & Harga (Quick Setup)
                        </a>
                        <a href="{{ route('admin.products.index') }}" class="btn btn-light-primary btn-lg">
                            <i class="ki-outline ki-element-plus fs-4 me-1"></i> Lihat di Katalog Produk
                        </a>
                        <a href="{{ route('admin.products.import.index') }}" class="btn btn-light btn-lg">
                            <i class="ki-outline ki-arrows-circle fs-4 me-1"></i> Import File Lainnya
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="d-flex flex-column gap-8">

    {{-- ======================================================== --}}
    {{-- LANGKAH 1: PERSIAPAN FILE & PANDUAN KOLOM                --}}
    {{-- ======================================================== --}}
    <div class="card card-flush shadow-sm">
        <div class="card-header border-0 pt-6 pb-2">
            <div class="card-title d-flex align-items-center gap-3">
                <span class="badge badge-circle badge-primary fs-5 fw-bolder" style="width: 32px; height: 32px;">1</span>
                <div>
                    <h3 class="fw-bolder text-gray-900 m-0 fs-4">Langkah 1: Persiapan File & Panduan Kolom</h3>
                    <span class="text-muted fs-7">Unduh template standar dan pahami format kolom sebelum mengunggah.</span>
                </div>
            </div>
        </div>
        <div class="card-body pt-2">
            
            {{-- Banner Aksi Download Template --}}
            <div class="bg-light-primary rounded-3 p-5 mb-6 border border-primary border-opacity-25 d-flex flex-column flex-md-row align-items-center justify-content-between gap-4">
                <div class="d-flex align-items-center gap-4">
                    <div class="bg-primary text-white rounded-3 p-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 52px; height: 52px;">
                        <i class="ki-outline ki-file-sheet fs-2 text-white"></i>
                    </div>
                    <div>
                        <h4 class="text-gray-900 fw-bold mb-1 fs-5">Template Excel Resmi GudangToko (.xlsx)</h4>
                        <p class="text-gray-600 fs-7 mb-0">
                            Template dilengkapi <strong>2 Sheet</strong>: Sheet 1 untuk input data produk, dan Sheet 2 berisi referensi Kategori & Satuan aktif sistem.
                        </p>
                    </div>
                </div>
                <div class="flex-shrink-0">
                    <a href="{{ route('admin.products.import.template') }}" class="btn btn-primary btn-lg shadow-sm">
                        <i class="ki-outline ki-file-down fs-3 me-2"></i> Unduh Template Excel (.xlsx)
                    </a>
                </div>
            </div>

            {{-- Tabel Panduan Kolom --}}
            <h5 class="fw-bold text-gray-800 mb-3 fs-6 d-flex align-items-center gap-2">
                <i class="ki-outline ki-information-4 fs-5 text-primary"></i> Aturan Pengisian Setiap Kolom Spreadsheet
            </h5>
            <div class="table-responsive mb-4">
                <table class="table table-row-bordered table-row-gray-200 align-middle gs-4 gy-3 fs-7">
                    <thead class="bg-light fw-bolder text-gray-700 text-uppercase fs-8">
                        <tr>
                            <th style="min-width: 140px;">Kolom di Excel</th>
                            <th style="width: 110px;">Status</th>
                            <th>Keterangan & Petunjuk Pengisian</th>
                            <th style="min-width: 180px;">Nilai Otomatis (Jika Kosong)</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600">
                        <tr>
                            <td><span class="font-monospace fw-bold text-gray-900 fs-7">name</span></td>
                            <td><span class="badge badge-light-danger fw-bolder px-2 py-1">Wajib Diisi</span></td>
                            <td>Nama lengkap produk yang akan ditampilkan di katalog dan kasir. Contoh: <code>Piring Keramik Putih 8 Inch</code></td>
                            <td><span class="text-muted fst-italic">- (Harus terisi)</span></td>
                        </tr>
                        <tr>
                            <td><span class="font-monospace fw-bold text-gray-900 fs-7">sku</span></td>
                            <td><span class="badge badge-light-secondary fw-semibold px-2 py-1">Opsional</span></td>
                            <td>Kode unik produk internal. Kosongkan jika ingin dibuat otomatis secara unik oleh sistem.</td>
                            <td><span class="badge badge-light-primary font-monospace">Auto PRD{mY}{0001}</span></td>
                        </tr>
                        <tr>
                            <td><span class="font-monospace fw-bold text-gray-900 fs-7">barcode</span></td>
                            <td><span class="badge badge-light-secondary fw-semibold px-2 py-1">Opsional</span></td>
                            <td>Kode barcode unik (EAN-13, UPC) untuk pemindaian scanner kasir / gudang.</td>
                            <td><span class="text-muted fst-italic">Boleh dikosongkan</span></td>
                        </tr>
                        <tr>
                            <td><span class="font-monospace fw-bold text-gray-900 fs-7">category_code</span></td>
                            <td><span class="badge badge-light-secondary fw-semibold px-2 py-1">Opsional</span></td>
                            <td>Kode atau nama kategori produk. Jika dikosongkan, otomatis dimasukkan ke kategori Umum.</td>
                            <td><span class="badge badge-light-info">UMUM (Kategori Umum)</span></td>
                        </tr>
                        <tr>
                            <td><span class="font-monospace fw-bold text-gray-900 fs-7">brand_code</span></td>
                            <td><span class="badge badge-light-secondary fw-semibold px-2 py-1">Opsional</span></td>
                            <td>Kode atau nama merek barang. Boleh dikosongkan jika produk belum bermerek.</td>
                            <td><span class="text-muted fst-italic">Tanpa Merek (null)</span></td>
                        </tr>
                        <tr>
                            <td><span class="font-monospace fw-bold text-gray-900 fs-7">base_unit_code</span></td>
                            <td><span class="badge badge-light-secondary fw-semibold px-2 py-1">Opsional</span></td>
                            <td>Kode satuan dasar terkecil produk (mis. PCS, PACK). Default sistem adalah PCS.</td>
                            <td><span class="badge badge-light-info">PCS (Pieces)</span></td>
                        </tr>
                        <tr>
                            <td><span class="font-monospace fw-bold text-gray-900 fs-7">minimum_order</span></td>
                            <td><span class="badge badge-light-secondary fw-semibold px-2 py-1">Opsional</span></td>
                            <td>Jumlah minimum pemesanan pembelian produk.</td>
                            <td><span class="text-muted font-monospace">0</span></td>
                        </tr>
                        <tr>
                            <td><span class="font-monospace fw-bold text-gray-900 fs-7">minimum_stock</span></td>
                            <td><span class="badge badge-light-secondary fw-semibold px-2 py-1">Opsional</span></td>
                            <td>Batas minimum persediaan sebelum peringatan restock.</td>
                            <td><span class="text-muted font-monospace">0</span></td>
                        </tr>
                        <tr>
                            <td><span class="font-monospace fw-bold text-gray-900 fs-7">safety_stock</span></td>
                            <td><span class="badge badge-light-secondary fw-semibold px-2 py-1">Opsional</span></td>
                            <td>Stok pengaman cadangan untuk mencegah kehabisan barang.</td>
                            <td><span class="text-muted font-monospace">0</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Tombol Collapse Referensi Kategori & Satuan Cepat --}}
            <div class="mt-3">
                <button type="button" class="btn btn-sm btn-light-info" data-bs-toggle="collapse" data-bs-target="#quick-reference-collapse" aria-expanded="false">
                    <i class="ki-outline ki-eye fs-5 me-1"></i> Lihat Daftar Referensi Kategori & Satuan Sistem Saat Ini
                </button>
                <div class="collapse mt-4" id="quick-reference-collapse">
                    <div class="card card-bordered bg-light-subtle p-5">
                        <div class="row g-5">
                            <div class="col-md-6">
                                <h6 class="fw-bold text-gray-800 mb-2 fs-7 d-flex align-items-center gap-2">
                                    <i class="ki-outline ki-category fs-6 text-primary"></i> Kategori Aktif Terdaftar:
                                </h6>
                                <div class="d-flex flex-wrap gap-2">
                                    @forelse(($categories ?? []) as $cat)
                                        <span class="badge badge-light-dark fs-8 py-2 px-3" title="Nama: {{ $cat->name }}">
                                            <strong>{{ $cat->code }}</strong> &bull; {{ $cat->name }}
                                        </span>
                                    @empty
                                        <span class="text-muted fs-8">Belum ada kategori kustom (default: UMUM).</span>
                                    @endforelse
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold text-gray-800 mb-2 fs-7 d-flex align-items-center gap-2">
                                    <i class="ki-outline ki-cube-2 fs-6 text-success"></i> Satuan Aktif Terdaftar:
                                </h6>
                                <div class="d-flex flex-wrap gap-2">
                                    @forelse(($units ?? []) as $u)
                                        <span class="badge badge-light-success fs-8 py-2 px-3" title="Nama: {{ $u->name }}">
                                            <strong>{{ $u->code }}</strong> ({{ $u->symbol ?: $u->name }})
                                        </span>
                                    @empty
                                        <span class="text-muted fs-8">Belum ada satuan kustom (default: PCS).</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- LANGKAH 2: UNGGAH FILE SPREADSHEET (DROPZONE AREA)        --}}
    {{-- ======================================================== --}}
    <div class="card card-flush shadow-sm" id="step-2-upload">
        <div class="card-header border-0 pt-6 pb-2">
            <div class="card-title d-flex align-items-center gap-3">
                <span class="badge badge-circle badge-primary fs-5 fw-bolder" style="width: 32px; height: 32px;">2</span>
                <div>
                    <h3 class="fw-bolder text-gray-900 m-0 fs-4">Langkah 2: Unggah File Spreadsheet</h3>
                    <span class="text-muted fs-7">Pilih atau letakkan file spreadsheet yang sudah diisi untuk divalidasi.</span>
                </div>
            </div>
        </div>
        <div class="card-body pt-2">
            
            <form method="POST" enctype="multipart/form-data" action="{{ route('admin.products.import.preview') }}" id="form-import-preview" data-no-loading="true">
                @csrf
                
                {{-- Dropzone Container --}}
                <div class="upload-dropzone border border-2 border-dashed border-primary bg-light-primary rounded-4 p-8 text-center cursor-pointer transition-all position-relative" id="dropzone-box">
                    <input type="file" 
                           name="file" 
                           id="file-input" 
                           class="position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer" 
                           accept=".xlsx,.xls,.csv,.txt" 
                           required>

                    <div id="dropzone-prompt">
                        <div class="d-flex align-items-center justify-content-center bg-white text-primary rounded-circle shadow-xs mx-auto mb-4" style="width: 68px; height: 68px;">
                            <i class="ki-outline ki-cloud-upload fs-2tx text-primary"></i>
                        </div>
                        <h4 class="text-gray-900 fw-bolder mb-1 fs-5">Tarik & Lepaskan File Spreadsheet Anda ke Sini</h4>
                        <p class="text-gray-600 fs-7 mb-3">atau klik area ini untuk memilih file dari komputer</p>
                        <div class="d-flex align-items-center justify-content-center gap-2">
                            <span class="badge badge-light-secondary text-gray-700 fs-8 px-3 py-1">Format: .XLSX</span>
                            <span class="badge badge-light-secondary text-gray-700 fs-8 px-3 py-1">Format: .CSV</span>
                            <span class="badge badge-light-secondary text-gray-700 fs-8 px-3 py-1">Maks: 10 MB</span>
                        </div>
                    </div>

                    {{-- Chip Pratinjau File yang Dipilih (Awalnya Tersembunyi) --}}
                    <div id="file-selected-chip" class="d-none">
                        <div class="d-inline-flex align-items-center gap-3 bg-white border border-primary border-opacity-50 rounded-3 px-5 py-3 shadow-sm">
                            <i class="ki-outline ki-file-sheet fs-2tx text-success"></i>
                            <div class="text-start">
                                <span class="fw-bolder text-gray-900 fs-6 d-block" id="selected-filename">nama_file.xlsx</span>
                                <span class="text-muted fs-8 d-block" id="selected-filesize">0 KB</span>
                            </div>
                            <button type="button" class="btn btn-icon btn-sm btn-light-danger ms-3" id="btn-clear-file" title="Ganti File">
                                <i class="ki-outline ki-cross fs-5"></i>
                            </button>
                        </div>
                    </div>
                </div>

                @error('file')
                    <div class="text-danger fs-7 mt-2">{{ $message }}</div>
                @enderror

                {{-- Tombol Aksi Submit Preview --}}
                <div class="d-flex align-items-center justify-content-between mt-6">
                    <span class="text-muted fs-8">
                        <i class="ki-outline ki-shield-check text-success fs-6 me-1"></i> Sistem akan memeriksa kelengkapan nama dan keunikan kode secara otomatis.
                    </span>
                    <button type="submit" class="btn btn-primary btn-lg shadow-sm" id="btn-submit-preview" disabled>
                        <i class="ki-outline ki-magnifier fs-4 me-1"></i> Validasi & Preview Data
                    </button>
                </div>
            </form>

        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- LANGKAH 3: PREVIEW VALIDASI & KONFIRMASI COMMIT          --}}
    {{-- ======================================================== --}}
    @if(is_array($preview))
        @php
            $rows = $preview['rows'] ?? [];
            $previewErrors = $preview['errors'] ?? [];
            $hasErrors = filled($previewErrors);
            $totalCount = $preview['total_count'] ?? count($rows);
            $errorCount = $preview['error_count'] ?? count($previewErrors);
            $validCount = $preview['valid_count'] ?? max(0, $totalCount - $errorCount);
        @endphp

        <div class="card card-flush shadow-sm border border-2 {{ $hasErrors ? 'border-danger border-opacity-50' : 'border-success border-opacity-50' }}" id="step-3-preview">
            <div class="card-header border-0 pt-6 pb-2">
                <div class="card-title d-flex align-items-center gap-3">
                    <span class="badge badge-circle {{ $hasErrors ? 'badge-danger' : 'badge-success' }} fs-5 fw-bolder" style="width: 32px; height: 32px;">3</span>
                    <div>
                        <h3 class="fw-bolder text-gray-900 m-0 fs-4">Langkah 3: Preview Validasi Data ({{ number_format($totalCount) }} Baris)</h3>
                        <span class="text-muted fs-7">
                            {{ $hasErrors ? 'Ditemukan beberapa baris yang memerlukan perbaikan sebelum dapat diimpor.' : 'Semua baris data valid dan siap disimpan ke sistem.' }}
                        </span>
                    </div>
                </div>
                <div class="card-toolbar">
                    <a href="{{ route('admin.products.import.index') }}" class="btn btn-sm btn-light">
                        <i class="ki-outline ki-trash fs-5 me-1"></i> Batalkan / Reset Preview
                    </a>
                </div>
            </div>

            <div class="card-body pt-2">
                
                {{-- KPI Cards Ringkasan --}}
                <div class="row g-4 mb-6">
                    <div class="col-sm-4">
                        <div class="card card-bordered bg-light-primary p-4 rounded-3 text-center">
                            <span class="fs-8 fw-bold text-gray-600 text-uppercase d-block mb-1">Total Baris Terbaca</span>
                            <span class="fs-2hx fw-bolder text-primary">{{ number_format($totalCount) }}</span>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="card card-bordered bg-light-success p-4 rounded-3 text-center">
                            <span class="fs-8 fw-bold text-success text-uppercase d-block mb-1">Siap Diimpor (Valid)</span>
                            <span class="fs-2hx fw-bolder text-success">{{ number_format($validCount) }}</span>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="card card-bordered {{ $errorCount > 0 ? 'bg-light-danger border-danger' : 'bg-light' }} p-4 rounded-3 text-center">
                            <span class="fs-8 fw-bold {{ $errorCount > 0 ? 'text-danger' : 'text-muted' }} text-uppercase d-block mb-1">Butuh Perbaikan (Error)</span>
                            <span class="fs-2hx fw-bolder {{ $errorCount > 0 ? 'text-danger' : 'text-gray-500' }}">{{ number_format($errorCount) }}</span>
                        </div>
                    </div>
                </div>

                {{-- Alert Pesan Status Validasi --}}
                @if($hasErrors)
                    <div class="alert alert-danger d-flex align-items-center p-5 mb-6 rounded-3">
                        <i class="ki-outline ki-cross-circle fs-2hx text-danger me-4"></i>
                        <div class="d-flex flex-column">
                            <h5 class="mb-1 text-danger fw-bolder">Masih ada error validasi!</h5>
                            <span class="fs-7 text-gray-700">
                                Ditemukan {{ $errorCount }} baris data yang bermasalah. Perbaiki file spreadsheet Anda lalu upload ulang.
                            </span>
                        </div>
                    </div>
                @else
                    <div class="alert alert-success d-flex align-items-center p-5 mb-6 rounded-3">
                        <i class="ki-outline ki-check-circle fs-2hx text-success me-4"></i>
                        <div class="d-flex flex-column">
                            <h5 class="mb-1 text-success fw-bolder">Semua baris valid!</h5>
                            <span class="fs-7 text-gray-700">
                                Seluruh {{ $totalCount }} data produk telah diperiksa dan siap diimport. Klik tombol <strong>Commit Import</strong> untuk menyimpan dalam transaksi database.
                            </span>
                        </div>
                    </div>
                @endif

                {{-- Filter Tabs Baris --}}
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                    <ul class="nav nav-pills nav-pills-sm" id="preview-filter-tabs">
                        <li class="nav-item">
                            <a class="nav-link active fw-bold px-4 py-2" href="javascript:void(0)" data-filter="all">
                                Semua Baris <span class="badge badge-light-primary ms-2">{{ $totalCount }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-bold px-4 py-2" href="javascript:void(0)" data-filter="valid">
                                Siap Diimpor <span class="badge badge-light-success ms-2">{{ $validCount }}</span>
                            </a>
                        </li>
                        @if($errorCount > 0)
                            <li class="nav-item">
                                <a class="nav-link fw-bold px-4 py-2 text-danger" href="javascript:void(0)" data-filter="error">
                                    Butuh Perbaikan <span class="badge badge-danger ms-2">{{ $errorCount }}</span>
                                </a>
                            </li>
                        @endif
                    </ul>
                    <span class="text-muted fs-8" id="preview-filter-indicator">Menampilkan seluruh baris</span>
                </div>

                {{-- Tabel Interaktif Preview --}}
                <div class="table-responsive border rounded-3 mb-6" style="max-height: 480px; overflow-y: auto;">
                    <table class="table table-hover table-row-bordered align-middle gs-4 gy-3 fs-7 mb-0" id="preview-table">
                        <thead class="bg-light text-gray-700 text-uppercase fs-8 sticky-top shadow-xs">
                            <tr>
                                <th style="width: 70px;">Baris</th>
                                <th style="width: 140px;">Status</th>
                                <th style="min-width: 220px;">Nama Produk</th>
                                <th style="min-width: 130px;">SKU</th>
                                <th style="min-width: 120px;">Barcode</th>
                                <th style="min-width: 120px;">Kategori</th>
                                <th style="min-width: 100px;">Merek</th>
                                <th style="min-width: 100px;">Satuan</th>
                                <th style="min-width: 200px;">Keterangan / Error</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            @foreach($rows as $idx => $row)
                                @php
                                    $rowNum = $row['__row_num'] ?? ($idx + 2);
                                    $rowErrors = $previewErrors[$rowNum] ?? ($row['__errors'] ?? []);
                                    $isValid = empty($rowErrors);
                                    $skuVal = trim((string) ($row['sku'] ?? ''));
                                    $barcodeVal = trim((string) ($row['barcode'] ?? ''));
                                    $catVal = trim((string) ($row['category_code'] ?? ''));
                                    $brandVal = trim((string) ($row['brand_code'] ?? ''));
                                    $unitVal = trim((string) ($row['base_unit_code'] ?? ''));
                                @endphp
                                <tr class="preview-row {{ $isValid ? 'bg-light-success-subtle' : 'bg-light-danger border-start border-3 border-danger' }}"
                                    data-row-status="{{ $isValid ? 'valid' : 'error' }}">
                                    
                                    {{-- Nomor Baris --}}
                                    <td class="text-center font-monospace fw-bold text-gray-700">
                                        Baris {{ $rowNum }}
                                    </td>

                                    {{-- Status Baris --}}
                                    <td>
                                        @if($isValid)
                                            <span class="badge badge-light-success fw-bold px-2 py-1">
                                                <i class="ki-outline ki-check-circle fs-8 text-success me-1"></i> Valid
                                            </span>
                                        @else
                                            <span class="badge badge-light-danger fw-bold px-2 py-1">
                                                <i class="ki-outline ki-cross-circle fs-8 text-danger me-1"></i> Error
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Nama Produk --}}
                                    <td>
                                        <span class="fw-bolder text-gray-900 fs-7 d-block">{{ $row['name'] ?: '-' }}</span>
                                    </td>

                                    {{-- SKU --}}
                                    <td>
                                        @if($skuVal !== '')
                                            <span class="badge badge-light-dark font-monospace fs-8">{{ $skuVal }}</span>
                                        @else
                                            <span class="badge badge-light-primary fs-9 fw-semibold" title="Sistem akan otomatis meng-generate SKU unik">[Otomatis]</span>
                                        @endif
                                    </td>

                                    {{-- Barcode --}}
                                    <td>
                                        @if($barcodeVal !== '')
                                            <span class="badge badge-light-info font-monospace fs-8">{{ $barcodeVal }}</span>
                                        @else
                                            <span class="text-muted fs-8">-</span>
                                        @endif
                                    </td>

                                    {{-- Kategori --}}
                                    <td>
                                        @if($catVal !== '')
                                            <span class="fw-semibold text-gray-800">{{ $catVal }}</span>
                                        @else
                                            <span class="badge badge-light-secondary fs-9" title="Menggunakan kategori default UMUM">UMUM (Default)</span>
                                        @endif
                                    </td>

                                    {{-- Merek --}}
                                    <td>
                                        @if($brandVal !== '')
                                            <span class="fw-semibold text-gray-800">{{ $brandVal }}</span>
                                        @else
                                            <span class="text-muted fs-8">-</span>
                                        @endif
                                    </td>

                                    {{-- Satuan Dasar --}}
                                    <td>
                                        @if($unitVal !== '')
                                            <span class="fw-bold text-gray-800">{{ $unitVal }}</span>
                                        @else
                                            <span class="badge badge-light-secondary fs-9" title="Menggunakan satuan default PCS">PCS (Default)</span>
                                        @endif
                                    </td>

                                    {{-- Keterangan / Pesan Error --}}
                                    <td>
                                        @if($isValid)
                                            <span class="text-muted fs-8">Siap diimport ke katalog</span>
                                        @else
                                            <div class="d-flex flex-column gap-1">
                                                @foreach($rowErrors as $err)
                                                    <span class="text-danger fs-8 fw-semibold d-flex align-items-center gap-1">
                                                        <i class="ki-outline ki-cross fs-8 text-danger"></i> {{ $err }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>

                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Area Tombol Konfirmasi Commit --}}
                <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-4 pt-4 border-top">
                    <div>
                        <span class="text-gray-600 fs-7">
                            Proses commit dijalankan dalam <strong>1 transaksi database</strong> yang aman dan tercatat di riwayat aktivitas.
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <a href="{{ route('admin.products.import.index') }}" class="btn btn-light">
                            Batal
                        </a>
                        @if($hasErrors)
                            <button type="button" class="btn btn-secondary disabled" title="Perbaiki error terlebih dahulu sebelum commit" disabled>
                                <i class="ki-outline ki-lock fs-4 me-1"></i> Commit Import Dikunci (Ada Error)
                            </button>
                        @else
                            <form method="POST" action="{{ route('admin.products.import.commit') }}" id="form-commit-import" data-no-loading="true">
                                @csrf
                                <button type="button" class="btn btn-success btn-lg shadow-sm px-8" id="btn-commit-import">
                                    <i class="ki-outline ki-check-circle fs-3 me-2"></i> Commit Import ({{ number_format($validCount) }} Produk)
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    @endif

</div>

{{-- Modal Progress Bar Import Produk --}}
<x-metronic.import-progress-modal 
    id="product-import-progress-modal" 
    title="Sedang Memproses Data Produk..." 
    subtitle="Mohon menunggu, sistem sedang membaca dan memvalidasi berkas spreadsheet Anda." 
    icon="ki-file-sheet" />

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ----------------------------------------------------
    // Drag & Drop Zone UX Handling
    // ----------------------------------------------------
    const dropzoneBox = document.getElementById('dropzone-box');
    const fileInput = document.getElementById('file-input');
    const dropzonePrompt = document.getElementById('dropzone-prompt');
    const fileSelectedChip = document.getElementById('file-selected-chip');
    const selectedFilename = document.getElementById('selected-filename');
    const selectedFilesize = document.getElementById('selected-filesize');
    const btnClearFile = document.getElementById('btn-clear-file');
    const btnSubmitPreview = document.getElementById('btn-submit-preview');
    const formImportPreview = document.getElementById('form-import-preview');

    function formatFileSize(bytes) {
        if (!bytes || bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    function handleFileSelected(file) {
        if (!file) return;
        selectedFilename.textContent = file.name;
        selectedFilesize.textContent = formatFileSize(file.size);
        dropzonePrompt.classList.add('d-none');
        fileSelectedChip.classList.remove('d-none');
        btnSubmitPreview.disabled = false;
        dropzoneBox.classList.remove('border-primary');
        dropzoneBox.classList.add('border-success', 'bg-light-success');
    }

    function clearFileSelection() {
        fileInput.value = '';
        dropzonePrompt.classList.remove('d-none');
        fileSelectedChip.classList.add('d-none');
        btnSubmitPreview.disabled = true;
        dropzoneBox.classList.remove('border-success', 'bg-light-success');
        dropzoneBox.classList.add('border-primary', 'bg-light-primary');
    }

    if (fileInput) {
        fileInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                handleFileSelected(file);
            } else {
                clearFileSelection();
            }
        });
    }

    if (btnClearFile) {
        btnClearFile.addEventListener('click', function (e) {
            e.stopPropagation();
            clearFileSelection();
        });
    }

    // Drag-over styling
    if (dropzoneBox) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzoneBox.addEventListener(eventName, function (e) {
                e.preventDefault();
                dropzoneBox.classList.add('border-opacity-100', 'shadow-sm');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzoneBox.addEventListener(eventName, function (e) {
                e.preventDefault();
                dropzoneBox.classList.remove('border-opacity-100', 'shadow-sm');
            });
        });

        dropzoneBox.addEventListener('drop', function (e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files.length > 0) {
                fileInput.files = files;
                handleFileSelected(files[0]);
            }
        });
    }

    // Form submit loading spinner & Progress Bar
    if (formImportPreview) {
        formImportPreview.addEventListener('submit', function () {
            if (btnSubmitPreview) {
                btnSubmitPreview.disabled = true;
                btnSubmitPreview.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Membaca & Memvalidasi Spreadsheet...';
            }

            if (typeof window.startImportProgress === 'function') {
                window.startImportProgress({
                    modalId: 'product-import-progress-modal',
                    title: 'Memvalidasi Berkas Produk...',
                    subtitle: 'Sistem sedang membaca dan memverifikasi data produk dari berkas yang diunggah.',
                    icon: 'ki-magnifier',
                    stages: [
                        { percent: 18, text: 'Mengunggah berkas spreadsheet...' },
                        { percent: 42, text: 'Membaca baris data dan struktur kolom...' },
                        { percent: 68, text: 'Memeriksa kelengkapan nama, keunikan SKU & barcode...' },
                        { percent: 88, text: 'Menyiapkan tabel pratinjau verifikasi...' },
                        { percent: 96, text: 'Hampir selesai, memuat tampilan...' }
                    ]
                });
            }
        });
    }

    // ----------------------------------------------------
    // Filter Tabs di Tabel Preview
    // ----------------------------------------------------
    const filterTabs = document.querySelectorAll('#preview-filter-tabs .nav-link');
    const previewRows = document.querySelectorAll('#preview-table tbody tr.preview-row');
    const filterIndicator = document.getElementById('preview-filter-indicator');

    if (filterTabs.length > 0) {
        filterTabs.forEach(tab => {
            tab.addEventListener('click', function () {
                filterTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                const filterValue = this.getAttribute('data-filter');
                let shownCount = 0;

                previewRows.forEach(row => {
                    const rowStatus = row.getAttribute('data-row-status');
                    if (filterValue === 'all') {
                        row.classList.remove('d-none');
                        shownCount++;
                    } else if (filterValue === 'valid' && rowStatus === 'valid') {
                        row.classList.remove('d-none');
                        shownCount++;
                    } else if (filterValue === 'error' && rowStatus === 'error') {
                        row.classList.remove('d-none');
                        shownCount++;
                    } else {
                        row.classList.add('d-none');
                    }
                });

                if (filterIndicator) {
                    if (filterValue === 'all') {
                        filterIndicator.textContent = `Menampilkan seluruh ${shownCount} baris`;
                    } else if (filterValue === 'valid') {
                        filterIndicator.textContent = `Menampilkan ${shownCount} baris siap diimpor`;
                    } else if (filterValue === 'error') {
                        filterIndicator.textContent = `Menampilkan ${shownCount} baris yang memerlukan perbaikan`;
                    }
                }
            });
        });
    }

    // ----------------------------------------------------
    // Konfirmasi SweetAlert2 saat Commit Import
    // ----------------------------------------------------
    const btnCommit = document.getElementById('btn-commit-import');
    const formCommit = document.getElementById('form-commit-import');

    if (btnCommit && formCommit) {
        btnCommit.addEventListener('click', function () {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Simpan Import Produk?',
                    text: 'Seluruh produk valid akan disimpan ke dalam katalog master sistem.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '<i class="ki-outline ki-check fs-4 me-1"></i> Ya, Simpan Sekarang!',
                    cancelButtonText: 'Batal',
                    customClass: {
                        confirmButton: 'btn btn-success fw-bold',
                        cancelButton: 'btn btn-light fw-bold'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        btnCommit.disabled = true;
                        btnCommit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan ke database...';

                        if (typeof window.startImportProgress === 'function') {
                            window.startImportProgress({
                                modalId: 'product-import-progress-modal',
                                title: 'Menyimpan Produk ke Database...',
                                subtitle: 'Sistem sedang menyimpan data produk secara massal dalam 1 transaksi database yang aman.',
                                icon: 'ki-data',
                                stages: [
                                    { percent: 20, text: 'Memulai transaksi database...' },
                                    { percent: 45, text: 'Menyimpan produk baru dan mengenerate SKU otomatis...' },
                                    { percent: 70, text: 'Menyinkronkan barcode dan unit satuan...' },
                                    { percent: 92, text: 'Menyelesaikan transaksi database...' },
                                    { percent: 97, text: 'Memperbarui katalog produk...' }
                                ]
                            });
                        }

                        formCommit.submit();
                    }
                });
            } else {
                if (confirm('Simpan import produk ke database sekarang?')) {
                    btnCommit.disabled = true;

                    if (typeof window.startImportProgress === 'function') {
                        window.startImportProgress({
                            modalId: 'product-import-progress-modal',
                            title: 'Menyimpan Produk ke Database...',
                            subtitle: 'Sistem sedang menyimpan data produk secara massal dalam 1 transaksi database yang aman.',
                            icon: 'ki-data',
                            stages: [
                                { percent: 20, text: 'Memulai transaksi database...' },
                                { percent: 45, text: 'Menyimpan produk baru dan mengenerate SKU otomatis...' },
                                { percent: 70, text: 'Menyinkronkan barcode dan unit satuan...' },
                                { percent: 92, text: 'Menyelesaikan transaksi database...' },
                                { percent: 97, text: 'Memperbarui katalog produk...' }
                            ]
                        });
                    }

                    formCommit.submit();
                }
            }
        });
    }
});
</script>
@endpush
@endsection
