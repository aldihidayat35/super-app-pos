@extends('layouts.metronic.app')

@section('title', 'Cetak Barcode/QR - '.config('app.name'))
@section('page_title', 'Cetak Barcode/QR')

@section('page_guide')
    <x-metronic.page-guide id="admin-products-barcodes" title="Panduan Cetak Barcode/QR">
        <x-slot:function>
            <p>Halaman ini digunakan untuk menyiapkan label produk dalam ukuran A4 atau Thermal. Preview memakai struktur label dan ukuran fisik yang sama dengan PDF.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol>
                <li>Pilih satu produk atau semua produk aktif.</li>
                <li>Tentukan jumlah label untuk setiap produk.</li>
                <li>Pilih A4 untuk printer laser/inkjet atau Thermal untuk printer label.</li>
                <li>Klik Preview, periksa susunan label, lalu klik Cetak PDF.</li>
            </ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul>
                <li><strong>Produk:</strong> menentukan produk yang dicetak.</li>
                <li><strong>Jumlah Label:</strong> jumlah salinan label per produk, maksimal 100.</li>
                <li><strong>Ukuran Kertas:</strong> A4 standar atau ukuran Thermal sesuai konfigurasi sistem.</li>
                <li><strong>Preview:</strong> menampilkan halaman dengan proporsi yang sama seperti PDF.</li>
                <li><strong>Cetak PDF:</strong> menghasilkan file siap cetak tanpa mengubah data produk.</li>
            </ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Proses ini hanya membaca produk, barcode, SKU, dan satuan. Tidak ada saldo stok atau data transaksi yang berubah.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol>
                <li>Pastikan barcode produk dapat dipindai. Jika barcode belum tersedia, sistem memakai SKU.</li>
                <li>Pilih ukuran kertas yang sama dengan media pada printer.</li>
                <li>Pada dialog printer gunakan skala 100% atau Actual Size.</li>
                <li>Hindari opsi Fit to Page karena dapat mengubah ukuran fisik barcode.</li>
            </ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0">Ukuran Thermal mengikuti konfigurasi aplikasi. Pastikan ukuran label fisik printer sama dengan ukuran yang tertulis di halaman.</div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Untuk mencetak 20 label satu produk pada printer laser: pilih produk, isi jumlah 20, pilih A4, periksa preview satu halaman, kemudian cetak PDF dengan skala 100%.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    @include('admin.products.partials.barcode-label-styles', ['paperSize' => $paperSize])

    <x-metronic.page-title title="Cetak Barcode/QR" description="Atur produk, jumlah label, dan ukuran kertas sebelum mencetak." />

    <x-metronic.card title="Pengaturan Cetak">
        <form method="GET" action="{{ route('admin.products.barcodes.index') }}" class="row g-4 align-items-end">
            <div class="col-lg-5">
                <x-metronic.form-group name="product_id" label="Produk">
                    <select name="product_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Semua produk aktif" data-allow-clear="true">
                        <option value="">Semua produk aktif</option>
                        @foreach($productOptions as $option)
                            <option value="{{ $option->id }}" @selected($selectedProductId === $option->id)>{{ $option->sku }} - {{ $option->name }}</option>
                        @endforeach
                    </select>
                </x-metronic.form-group>
            </div>
            <div class="col-sm-6 col-lg-2">
                <x-metronic.form-group name="label_count" label="Jumlah Label" required>
                    <input type="number" min="1" max="100" step="1" name="label_count" value="{{ $labelCount }}" class="form-control form-control-solid" required>
                </x-metronic.form-group>
            </div>
            <div class="col-sm-6 col-lg-3">
                <x-metronic.form-group name="paper_size" label="Ukuran Kertas" required>
                    <select name="paper_size" class="form-select form-select-solid" data-searchable="false" required>
                        @foreach($paperOptions as $value => $label)
                            <option value="{{ $value }}" @selected($paperSize->value === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-metronic.form-group>
            </div>
            <div class="col-lg-2">
                <button class="btn btn-primary w-100">
                    <i class="ki-outline ki-eye fs-5 me-2"></i>Preview
                </button>
            </div>
        </form>
    </x-metronic.card>

    <x-metronic.card title="Preview Siap Cetak" class="mt-6">
        @if($totalLabels > 0)
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
                <div>
                    <div class="fw-bold text-gray-900">{{ $totalLabels }} label pada {{ count($labelPages) }} halaman</div>
                    <div class="text-muted fs-7">Ukuran fisik: {{ $paperSize->label() }}. Preview menggunakan struktur yang sama dengan PDF.</div>
                </div>
                <a href="{{ route('admin.products.barcodes.pdf', ['product_id' => $selectedProductId, 'label_count' => $labelCount, 'paper_size' => $paperSize->value]) }}"
                   class="btn btn-light-primary">
                    <i class="ki-outline ki-file-down fs-5 me-2"></i>Cetak PDF
                </a>
            </div>

            <div class="barcode-preview-stage">
                @include('admin.products.partials.barcode-sheet', [
                    'labelPages' => $labelPages,
                    'paperSize' => $paperSize,
                ])
            </div>
        @else
            <x-metronic.empty-state title="Tidak ada produk" description="Pilih produk aktif yang memiliki barcode atau SKU valid untuk membuat label." />
        @endif
    </x-metronic.card>
@endsection
