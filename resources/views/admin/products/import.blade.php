@extends('layouts.metronic.app')
@section('title', 'Import Produk')
@section('page_title', 'Import dan Export Produk')
@section('page_guide')
    <x-metronic.page-guide id="admin-products-import" title="Panduan Halaman Import Produk">
        <x-slot:function>
            <p class="mb-2"><strong>Fungsi utama halaman ini:</strong> Halaman untuk mengimpor (menambahkan/memperbarui) data produk secara massal dari file CSV atau Excel. Admin bisa mengupload file berisi daftar produk dalam bentuk spreadsheet, lalu sistem melakukan validasi dan preview sebelum menyimpan.</p>
            
            <p class="mb-2"><strong>Siapa yang menggunakan halaman ini:</strong></p>
            <ul class="mb-0">
                <li><strong>Admin:</strong> Mengimpor katalog produk lengkap dari supplier atau migrasi data.</li>
                <li><strong>Staff Gudang:</strong> Mengimpor stok masuk ke dalam sistem.</li>
                <li><strong>Tim Pembelian:</strong> Mengimpor daftar produk dari purchase order ke dalam katalog.</li>
            </ul>
        </x-slot:function>
        <x-slot:workflow>
            <p class="mb-3"><strong>Halaman ini terhubung erat dengan modul-modul berikut:</strong></p>
            <ul class="mb-0">
                <li><strong>Daftar Produk:</strong> Produk yang berhasil diimport langsung muncul di halaman daftar produk.</li>
                <li><strong>Export Produk:</strong> Fitur export bisa digunakan untuk mendownload data produk yang sudah ada, modifikasi di luar (misal via Excel), lalu di-import kembali.</li>
                <li><strong>Kategori, Merek, Satuan:</strong> Data referensi ini dibaca dari halaman-halaman masing-masing. Harus sudah ada sebelum import produk.</li>
                <li><strong>Template:</strong> Halaman ini menyediakan tombol Download Template CSV yang berisi format kolom yang benar.</li>
            </ul>
        </x-slot:workflow>
        <x-slot:parts>
            <p class="mb-3"><strong>Cara kerja halaman ini:</strong></p>
            <ol class="mb-0">
                <li>Admin membuka halaman Import Produk dan mengklik tombol <strong>"Download Template"</strong> untuk mendapatkan file CSV contoh.</li>
                <li>File template diisi di Excel dengan data produk. Setiap baris mewakili satu produk.</li>
                <li>File Excel/CSV yang sudah diisi diupload melalui tombol "Pilih File" lalu klik <strong>"Preview Validasi"</strong>.</li>
                <li>Sistem membaca file dan memvalidasi setiap baris: memastikan kategori ada, SKU unik, satuan valid, dsb.</li>
                <li>Preview ditampilkan berupa tabel dengan kolom: SKU, Nama, Kategori, Merek, Satuan, Status.</li>
                <li>Jika ada error validasi, daftar error ditampilkan per baris. Perbaiki file lalu upload ulang.</li>
                <li>Jika semua valid, klik tombol <strong>"Commit Import"</strong> untuk menyimpan semua produk sekaligus.</li>
                <li>Proses commit dijalankan dalam <strong>1 transaksi database</strong> — semua berhasil atau semua gagal.</li>
            </ol>
        </x-slot:parts>
        <x-slot:impacts>
            <p class="mb-3"><strong>Dampak import terhadap data dan modul lain:</strong></p>
            <ul class="mb-0">
                <li><strong>Produk baru:</strong> Produk baru otomatis ditambahkan ke daftar produk dan tersedia di Kasir/POS jika status=Aktif.</li>
                <li><strong>Produk sudah ada (SKU duplikat):</strong> Sistem akan melakukan <strong>UPSERT</strong> — memperbarui data produk yang sudah ada (mengupdate nama, kategori, harga, dll) tapi tidak menghapus histori transaksi.</li>
                <li><strong>Kategori:</strong> Jika kategori yang direferensikan tidak ada, baris tersebut akan gagal validasi.</li>
                <li><strong>Merek:</strong> Jika merek tidak ada, baris tersebut akan gagal validasi.</li>
                <li><strong>Satuan:</strong> Jika satuan dasar tidak ada, baris tersebut akan gagal validasi.</li>
                <li>Setiap import dicatat di activity log.</li>
            </ul>
        </x-slot:impacts>
        <x-slot:operation>
            <p class="mb-3"><strong>Alur Pengoperasian Lengkap:</strong></p>
            
            <p class="fw-bold mt-3">Persiapan File Import</p>
            <ol>
                <li>Klik tombol <strong>"Download Template"</strong> untuk mendownload file CSV template.</li>
                <li>Buka template di Excel atau aplikasi spreadsheet.</li>
                <li>Isi kolom-kolom berikut:
                    <ul>
                        <li><strong>SKU:</strong> Kode unik produk (wajib). Kosongkan jika ingin auto-generate.</li>
                        <li><strong>Nama:</strong> Nama produk (wajib).</li>
                        <li><strong>Category_Code:</strong> Kode kategori produk (misal: UMUM, MINUMAN, MAKANAN).</li>
                        <li><strong>Brand_Code:</strong> Kode merek (misal: FRISO, SOPRINO, NO-BRAND).</li>
                        <li><strong>Base_Unit_Code:</strong> Kode satuan dasar (misal: PCS, BOX, KG).</li>
                        <li><strong>Status:</strong> 'active' atau 'inactive'.</li>
                    </ul>
                </li>
                <li>Simpan file sebagai CSV (.csv) atau Excel (.xlsx).</li>
            </ol>
            
            <p class="fw-bold mt-3">Upload dan Preview</p>
            <ol>
                <li>Buka halaman Import Produk.</li>
                <li>Klik "Choose File" dan pilih file yang sudah diisi.</li>
                <li>Klik tombol <strong>"Preview Validasi"</strong>.</li>
                <li>Anda akan diarahkan kembali ke halaman daftar dengan preview tabel.</li>
                <li>Periksa apakah ada error validasi.</li>
            </ol>
            
            <p class="fw-bold mt-3">Commit Import</p>
            <ol>
                <li>Jika preview semua valid, klik tombol <strong>"Commit Import"</strong>.</li>
                <li>Sistem menyimpan semua baris sekaligus dalam 1 transaksi.</li>
                <li>Setelah selesai, Anda melihat ringkasan: berapa produk dibuat dan berapa diperbarui.</li>
            </ol>
        </x-slot:operation>
        <x-slot:warnings>
            <p class="mb-3"><strong>Peringatan Penting:</strong></p>
            <div class="alert alert-warning mb-0">
                <ul class="mb-0">
                    <li><strong>SKU Duplikat:</strong> Jika SKU sudah ada di sistem, data produk akan diupdate (bukan dibuat baru). Pastikan ini yang diinginkan.</li>
                    <li><strong>Format File:</strong> Gunakan format CSV atau XLSX yang didukung. File format lain tidak bisa diupload.</li>
                    <li><strong>Encoding:</strong> Pastikan file CSV menggunakan encoding UTF-8 agar karakter Indonesia terbaca benar.</li>
                    <li><strong>Jumlah Baris:</strong> File dengan ribuan baris mungkin memakan waktu beberapa detik untuk diproses.</li>
                    <li><strong>Referensi Kategori/Merek/Satuan:</strong> Pastikan semua kode kategori, merek, dan satuan yang direferensikan sudah ada di sistem sebelum import.</li>
                    <li><strong>Commit Satu Kali:</strong> Setelah commit, tidak ada undo. Jika ingin memperbaiki data, lakukan manual edit per produk di halaman detail produk.</li>
                </ul>
            </div>
        </x-slot:warnings>
        <x-slot:example>
            <p class="mb-3"><strong>Contoh Penggunaan Nyata:</strong></p>
            <p><strong>Skenario — Mengimpor Katalog Supplier Baru (100 Produk):</strong></p>
            <ol>
                <li>Admin download template CSV.</li>
                <li>Admin kirim template ke supplier, supplier mengisi 100 produk di Excel.</li>
                <li>Admin menerima file dari supplier, upload ke halaman import.</li>
                <li>Sistem memvalidasi — tidak ada error karena semua kategori dan merek sudah ada di sistem.</li>
                <li>Admin klik "Commit Import".</li>
                <li>100 produk berhasil dibuat (jika SKU baru) atau diupdate (jika SKU duplikat).</li>
            </ol>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection
@section('toolbar_actions')
    <a href="{{ route('admin.products.import.template') }}" class="btn btn-light">Download Template</a>
    <a href="{{ route('admin.products.export') }}" class="btn btn-light-primary">Export Data Produk</a>
@endsection
@section('content')
<x-metronic.card title="Upload File">
    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.products.import.preview') }}" class="row g-3 align-items-end">@csrf
        <div class="col-md-8"><x-metronic.form-group name="file" label="File XLSX/CSV" required><input type="file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".xlsx,.xls,.csv,.txt"></x-metronic.form-group></div>
        <div class="col-md-4"><button class="btn btn-primary w-100">Preview Validasi</button></div>
    </form>
</x-metronic.card>
@if($result)
    <div class="alert alert-success mt-6">Import selesai. Dibuat: {{ $result['created'] ?? 0 }}, diperbarui: {{ $result['updated'] ?? 0 }}.</div>
@endif
@if($preview)
    <x-metronic.card title="Preview Import" class="mt-6">
        @if(filled($preview['errors'] ?? []))
            <div class="alert alert-danger">Masih ada error validasi. Perbaiki file lalu upload ulang.</div>
            <ul>@foreach($preview['errors'] as $row => $rowErrors)<li>Baris {{ $row }}: {{ implode(', ', $rowErrors) }}</li>@endforeach</ul>
        @else
            <div class="alert alert-success">Semua baris valid. Klik commit untuk menyimpan dalam transaksi database.</div>
            <form method="POST" action="{{ route('admin.products.import.commit') }}">@csrf<button class="btn btn-success">Commit Import</button></form>
        @endif
        <div class="table-responsive mt-5"><table class="table table-row-dashed"><thead><tr><th>SKU</th><th>Nama</th><th>Kategori</th><th>Merek</th><th>Satuan</th><th>Status</th></tr></thead><tbody>@foreach(($preview['rows'] ?? []) as $row)<tr><td>{{ $row['sku'] ?? '-' }}</td><td>{{ $row['name'] ?? '-' }}</td><td>{{ $row['category_code'] ?? '-' }}</td><td>{{ $row['brand_code'] ?? '-' }}</td><td>{{ $row['base_unit_code'] ?? '-' }}</td><td>{{ $row['status'] ?? '-' }}</td></tr>@endforeach</tbody></table></div>
    </x-metronic.card>
@endif
@endsection

