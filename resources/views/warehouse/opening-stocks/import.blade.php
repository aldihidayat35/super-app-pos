@extends('layouts.metronic.app')

@section('title', 'Import Stok Awal - ' . config('app.name'))
@section('page_title', 'Import Stok Awal')

@section('toolbar_actions')
    <a href="{{ route('warehouse.opening-stocks.index') }}" class="btn btn-light">
        <i class="ki-outline ki-arrow-left fs-2 me-1"></i> Kembali ke Pendataan Stok
    </a>
@endsection

@section('page_guide')
    <x-metronic.page-guide id="warehouse-opening-stock-import" title="Panduan Import Stok Awal Excel">
        <x-slot:function>
            <p>Halaman ini digunakan untuk mengunggah saldo stok fisik awal dan HPP produk secara massal dari file Excel (XLSX) ke gudang atau toko tertentu.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol>
                <li>Download file template Excel (.xlsx) resmi.</li>
                <li>Isi kolom SKU produk, kode rak/bin (opsional), jumlah stok, HPP, dan alasan.</li>
                <li>Unggah file ke form lalu klik <strong>Preview Validasi</strong>.</li>
                <li>Periksa tabel hasil verifikasi. Jika seluruh baris valid, klik <strong>Commit ke Database</strong> untuk menyimpan saldo.</li>
            </ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul>
                <li><strong>Download Template:</strong> mengunduh berkas template XLSX dengan header yang sesuai sistem.</li>
                <li><strong>File Excel XLSX:</strong> berkas data yang akan diunggah (maksimal 5MB).</li>
                <li><strong>Tabel Preview:</strong> menampilkan 50 baris pertama untuk memastikan keakuratan mapping kolom dan memeriksa pesan error bila ada data yang tidak cocok.</li>
                <li><strong>Commit ke Database:</strong> tombol eksekusi untuk memperbarui saldo fisik stok, mencatat mutasi penyesuaian (*InventoryService*), dan memperbarui histori HPP.</li>
            </ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Saldo stok pada lokasi yang dituju akan langsung diperbarui (*set on hand*) dan mutasi penyesuaian stok dicatat. HPP produk dan nilai persediaan otomatis disesuaikan.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol>
                <li>Klik tombol Download Template Excel.</li>
                <li>Isi data stok fisik toko/gudang.</li>
                <li>Pilih file dan klik Preview Validasi.</li>
                <li>Tinjau data, lalu klik tombol Commit.</li>
            </ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0">
                <ul class="mb-0">
                    <li>SKU produk dan kode rak harus sudah terdaftar aktif di sistem.</li>
                    <li>Pastikan angka kuantitas dan HPP bernilai 0 atau positif (bukan negatif).</li>
                </ul>
            </div>
        </x-slot:warnings>
        <x-slot:example>
            <p>SKU: <code>PRD-001</code>, Lokasi: <code>TKO-DEMO</code>, Rak: <code>RAK-A1</code>, Qty: <code>50</code>, HPP: <code>15000</code>, Alasan: <code>Saldo Awal Toko</code>.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <div class="row g-5 mb-5">
        {{-- Card Download Template --}}
        <div class="col-md-5">
            <x-metronic.card title="Template Excel">
                <div class="border rounded p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="fw-bold fs-6 mb-2">Template Stok Awal & HPP</div>
                        <p class="text-muted fs-7 mb-3">
                            Format file wajib menggunakan Excel (.xlsx) dengan susunan kolom:
                        </p>
                        @if ($template)
                            <div class="d-flex flex-wrap gap-1 mb-4">
                                @foreach ($template['columns'] as $colKey => $colLabel)
                                    <span class="badge badge-light-secondary fs-8">{{ $colLabel }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div>
                        <a href="{{ route('warehouse.opening-stocks.import.template') }}" class="btn btn-light-primary w-100">
                            <i class="ki-outline ki-file-down fs-2 me-1"></i> Download Template (.xlsx)
                        </a>
                    </div>
                </div>
            </x-metronic.card>
        </div>

        {{-- Card Upload & Preview --}}
        <div class="col-md-7">
            <x-metronic.card title="Unggah Berkas Excel">
                <form method="POST" action="{{ route('warehouse.opening-stocks.import.preview') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-4">
                        <label class="form-label fw-semibold required">Pilih Gudang / Cabang Tujuan</label>
                        <select name="work_location_id" class="form-select @error('work_location_id') is-invalid @enderror" data-control="select2" data-placeholder="Pilih lokasi..." required>
                            <option value=""></option>
                            @foreach ($workLocations ?? [] as $location)
                                <option value="{{ $location->id }}" @selected(old('work_location_id') == $location->id)>
                                    {{ $location->typeLabel() }} - {{ $location->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('work_location_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="form-text">Semua baris di dalam file Excel akan dimasukkan ke lokasi ini.</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold required">Pilih File XLSX</label>
                        <input type="file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                        @error('file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="form-text">Pilih berkas Excel (.xlsx) yang sudah diisi sesuai format template resmi.</div>
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

    {{-- Card Hasil Preview --}}
    @if ($preview)
        <x-metronic.card title="Hasil Verifikasi Preview: {{ $preview['label'] ?? 'Stok Awal' }}" class="mt-5">
            <div class="row g-4 mb-5">
                <div class="col-md-3">
                    <div class="border rounded p-3 text-center">
                        <div class="text-muted fs-7">Total Baris</div>
                        <div class="fs-2 fw-bold text-gray-800">{{ $preview['totals']['rows'] ?? 0 }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 text-center">
                        <div class="text-muted fs-7">Baris Valid</div>
                        <div class="fs-2 fw-bold text-success">{{ $preview['totals']['valid_rows'] ?? 0 }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 text-center">
                        <div class="text-muted fs-7">Error Validasi</div>
                        <div class="fs-2 fw-bold {{ ($preview['totals']['invalid_rows'] ?? 0) > 0 ? 'text-danger' : 'text-gray-400' }}">
                            {{ $preview['totals']['invalid_rows'] ?? 0 }}
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 text-center">
                        <div class="text-muted fs-7">Lokasi Tujuan</div>
                        <div class="fs-4 fw-bold text-primary text-truncate px-2" title="{{ \App\Models\WorkLocation::find($preview['extra_data'])?->name }}">{{ \App\Models\WorkLocation::find($preview['extra_data'])?->name ?? '-' }}</div>
                    </div>
                </div>
            </div>

            @if ($preview['errors'])
                <div class="alert alert-warning mb-5">
                    <div class="fw-bold mb-2"><i class="ki-outline ki-information-5 fs-4 me-1"></i> Ditemukan Error Validasi:</div>
                    <ul class="mb-0">
                        @foreach ($preview['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="alert alert-success d-flex align-items-center mb-5">
                    <i class="ki-outline ki-check-circle fs-2 text-success me-3"></i>
                    <div>
                        <strong>Seluruh data valid!</strong> Periksa tabel di bawah ini, lalu klik tombol <strong>Commit ke Database</strong> untuk menyimpan saldo stok dan HPP secara permanen.
                    </div>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-3">
                    <thead>
                        <tr class="fw-bold text-muted">
                            @foreach ($preview['headers'] as $header)
                                <th>{{ $preview['header_labels'][$header] ?? $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($preview['rows'] as $row)
                            <tr>
                                @foreach ($preview['headers'] as $header)
                                    <td>{{ $row[$header] ?? '-' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Tombol Commit --}}
            @if ($preview['errors'] === [])
                <div class="separator my-6"></div>
                <form method="POST" action="{{ route('warehouse.opening-stocks.import.commit') }}" onsubmit="return confirm('Commit akan memperbarui saldo stok dan HPP sesuai baris file Excel. Lanjutkan?');">
                    @csrf
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted fs-7">
                            <i class="ki-outline ki-shield-tick text-success fs-5 me-1"></i> Data mutasi stok penyesuaian akan dicatat secara transparan oleh sistem.
                        </div>
                        <button type="submit" class="btn btn-danger">
                            <i class="ki-outline ki-check-circle fs-2 me-1"></i> Commit ke Database Sekarang
                        </button>
                    </div>
                </form>
            @endif
        </x-metronic.card>
    @endif
@endsection
