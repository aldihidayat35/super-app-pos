@extends('layouts.metronic.app')

@section('title', 'Import Merek Produk - ' . config('app.name'))
@section('page_title', 'Import Merek Produk')

@section('toolbar_actions')
    <a href="{{ route('admin.product-brands.index') }}" class="btn btn-light">
        <i class="ki-outline ki-arrow-left fs-2 me-1"></i> Kembali ke Merek
    </a>
@endsection

@section('page_guide')
    <x-metronic.page-guide id="admin-product-brands-import" title="Panduan Import Merek Produk">
        <x-slot:function>
            <p>Halaman untuk mengimpor dan memperbarui daftar merek/brand produk secara massal dari berkas spreadsheet CSV atau Excel.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol>
                <li>Unduh template CSV/Excel yang disediakan.</li>
                <li>Isi kode merek, nama merek, deskripsi, dan status aktif.</li>
                <li>Unggah berkas untuk validasi preview.</li>
                <li>Periksa kesesuaian baris data, lalu klik Commit Import untuk menyimpan ke sistem.</li>
            </ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul>
                <li><strong>Download Template:</strong> mengunduh contoh struktur kolom yang valid.</li>
                <li><strong>Upload Berkas:</strong> menerima format CSV, TXT, XLS, dan XLSX (maksimal 5MB).</li>
                <li><strong>Preview Validasi:</strong> memverifikasi kelengkapan kode dan format data merek.</li>
                <li><strong>Commit Import:</strong> mengeksekusi upsert ke database dalam satu transaksi.</li>
            </ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Merek yang diimpor akan langsung tersedia sebagai opsi brand pada master produk dan filter katalog.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol>
                <li>Klik tombol Download Template.</li>
                <li>Lengkapi data merek di aplikasi spreadsheet.</li>
                <li>Pilih berkas lalu klik Preview Validasi.</li>
                <li>Jika valid, klik Commit Import.</li>
            </ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0">
                <ul class="mb-0">
                    <li>Kode merek harus unik. Jika kode sudah ada, nama dan deskripsi merek akan diperbarui (UPSERT).</li>
                    <li>Logo merek tidak dapat diimpor melalui berkas teks; logo dapat diunggah melalui formulir edit merek.</li>
                </ul>
            </div>
        </x-slot:warnings>
        <x-slot:example>
            <p><code>BRD-TORABIKA</code> (Torabika, Deskripsi: Kopi Mayora, Aktif: 1), <code>BRD-INDOMIE</code> (Indomie, Aktif: 1).</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <div class="row g-5 mb-5">
        {{-- Card Download Template --}}
        <div class="col-md-5">
            <x-metronic.card title="Template Import">
                <div class="border rounded p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="fw-bold fs-6 mb-2">Template Merek (.csv / .xlsx)</div>
                        <p class="text-muted fs-7 mb-3">
                            Format berkas wajib memiliki header kolom berikut:
                        </p>
                        <div class="d-flex flex-wrap gap-1 mb-4">
                            <span class="badge badge-light-primary fs-8">code (Wajib)</span>
                            <span class="badge badge-light-primary fs-8">name (Wajib)</span>
                            <span class="badge badge-light-secondary fs-8">description (Opsional)</span>
                            <span class="badge badge-light-secondary fs-8">is_active</span>
                        </div>
                    </div>
                    <div>
                        <a href="{{ route('admin.product-brands.import.template') }}" class="btn btn-light-primary w-100">
                            <i class="ki-outline ki-file-down fs-2 me-1"></i> Download Template CSV
                        </a>
                    </div>
                </div>
            </x-metronic.card>
        </div>

        {{-- Card Upload & Preview --}}
        <div class="col-md-7">
            <x-metronic.card title="Unggah Berkas Merek">
                <form method="POST" action="{{ route('admin.product-brands.import.preview') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-4">
                        <label class="form-label fw-semibold required">Pilih Berkas CSV / Excel</label>
                        <input type="file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".csv,.txt,.xlsx,.xls" required>
                        @error('file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="form-text">Mendukung format berkas .csv, .xlsx, .xls (maksimal 5MB).</div>
                        @enderror
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="ki-outline ki-magnifier fs-2 me-1"></i> Preview Validasi
                        </button>
                    </div>
                </form>
            </x-metronic.card>
        </div>
    </div>

    {{-- Hasil Import Terakhir --}}
    @if (!empty($result))
        <div class="alert alert-success d-flex align-items-center p-5 mb-5">
            <i class="ki-outline ki-check-circle fs-2hx text-success me-4"></i>
            <div class="d-flex flex-column">
                <h4 class="mb-1 text-success">Import Berhasil Disimpan</h4>
                <span>Data merek berhasil diproses: <strong>{{ $result['created'] ?? 0 }}</strong> data baru ditambahkan, <strong>{{ $result['updated'] ?? 0 }}</strong> data diperbarui.</span>
            </div>
        </div>
    @endif

    {{-- Tabel Preview & Commit --}}
    @if ($preview)
        @php
            $hasErrors = filled($preview['errors'] ?? []);
            $rows = $preview['rows'] ?? [];
            $previewErrors = $preview['errors'] ?? [];
        @endphp

        <x-metronic.card title="Hasil Preview Validasi">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    @if ($hasErrors)
                        <span class="badge badge-light-danger fs-7 py-2 px-3">
                            <i class="ki-outline ki-cross-circle fs-6 me-1 text-danger"></i> Masih ada error validasi ({{ count($previewErrors) }} baris bermasalah)
                        </span>
                    @else
                        <span class="badge badge-light-success fs-7 py-2 px-3">
                            <i class="ki-outline ki-check-circle fs-6 me-1 text-success"></i> Semua baris valid ({{ count($rows) }} baris siap diimpor)
                        </span>
                    @endif
                </div>

                @if (! $hasErrors && count($rows) > 0)
                    <form method="POST" action="{{ route('admin.product-brands.import.commit') }}">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            <i class="ki-outline ki-check fs-2 me-1"></i> Commit Import Merek
                        </button>
                    </form>
                @endif
            </div>

            @if ($hasErrors)
                <div class="alert alert-danger p-4 mb-4">
                    <div class="fw-bold mb-2">Rincian Kesalahan Validasi:</div>
                    <ul class="mb-0">
                        @foreach ($previewErrors as $line => $messages)
                            <li>
                                <strong>Baris {{ $line }}:</strong>
                                {{ implode(', ', $messages) }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-row-dashed align-middle gs-4 gy-3">
                    <thead>
                        <tr class="text-muted fw-bold text-uppercase fs-7">
                            <th>Baris</th>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Deskripsi</th>
                            <th>Status Aktif</th>
                            <th class="text-center">Status Validasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (array_slice($rows, 0, 50) as $idx => $row)
                            @php
                                $lineNum = $idx + 2;
                                $rowError = $previewErrors[$lineNum] ?? null;
                            @endphp
                            <tr class="{{ $rowError ? 'bg-light-danger' : '' }}">
                                <td class="fw-bold text-muted">{{ $lineNum }}</td>
                                <td class="fw-bold text-dark">{{ $row['code'] ?? '-' }}</td>
                                <td>{{ $row['name'] ?? '-' }}</td>
                                <td>{{ $row['description'] ?? '-' }}</td>
                                <td>
                                    <span class="badge badge-light-{{ in_array(strtolower((string)($row['is_active'] ?? '1')), ['0', 'false', 'inactive', 'nonaktif'], true) ? 'secondary' : 'success' }}">
                                        {{ in_array(strtolower((string)($row['is_active'] ?? '1')), ['0', 'false', 'inactive', 'nonaktif'], true) ? 'Nonaktif' : 'Aktif' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if ($rowError)
                                        <span class="badge badge-danger">Error</span>
                                    @else
                                        <span class="badge badge-success">Valid</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Tidak ada baris data.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (count($rows) > 50)
                <div class="text-muted fs-7 text-center mt-3">
                    Menampilkan 50 dari total {{ count($rows) }} baris data pada berkas.
                </div>
            @endif
        </x-metronic.card>
    @endif
@endsection
