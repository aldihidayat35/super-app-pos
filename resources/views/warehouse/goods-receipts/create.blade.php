@extends('layouts.metronic.app')

@section('title', 'Penerimaan Barang Supplier - ' . config('app.name'))
@section('page_title', 'Form Penerimaan dan QC')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-goods-receipt-create" title="Panduan Form Penerimaan Barang Supplier">
        <x-slot:function>
            <p>Form terpadu satu halaman untuk mencatat barang yang datang dari supplier, melakukan Quality Control (QC), menghitung HPP final dengan alokasi ongkir proporsional, serta memvalidasi harga minimum jual sebelum posting otomatis ke stok FIFO.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol>
                <li>Pilih lokasi penerima dan supplier.</li>
                <li>Isi tanggal datang, nomor surat jalan/faktur, dan biaya ongkir aktual jika ada.</li>
                <li>Pilih produk atau gunakan tombol <strong>Tambah Produk Baru</strong> jika barang belum terdaftar.</li>
                <li>Tentukan satuan pembelian, input kuantitas datang dan rincian QC (diterima, rusak, ditolak, retur).</li>
                <li>Masukkan HPP beli; sistem otomatis menghitung alokasi ongkir dan HPP final per pcs secara proporsional.</li>
                <li>Tentukan harga minimum jual di atas modal (atau klik tombol shortcut margin +10%, +20%, +30%, +50%).</li>
                <li>Klik <strong>Simpan &amp; Posting Stok</strong> untuk langsung mengaktifkan persediaan di kasir POS, atau <strong>Simpan Sebagai Draft</strong> jika QC ingin dijeda.</li>
            </ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul>
                <li><strong>Lokasi Penerima:</strong> Cabang toko atau gudang tujuan penerimaan.</li>
                <li><strong>Total Biaya Ongkir:</strong> Didistribusikan secara proporsional ke barang yang lolos QC.</li>
                <li><strong>Kuantitas QC:</strong> Diterima baik (masuk stok aktif), rusak (masuk stok rusak), ditolak/retur (tidak masuk stok).</li>
                <li><strong>HPP Final:</strong> HPP beli + alokasi ongkir per unit dasar (pcs).</li>
                <li><strong>Proteksi Anti-Rugi:</strong> Harga jual minimum dilarang &le; HPP final atau modal batch aktif lama.</li>
            </ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Posting penerimaan langsung menambah stok aktif, membuat batch FIFO baru, mencatat histori HPP, menghitung skor kualitas supplier, dan memperbarui harga kasir POS di lokasi tersebut.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol>
                <li>Gunakan scanner barcode atau ketik SKU/nama untuk memilih produk.</li>
                <li>Manfaatkan tombol shortcut margin untuk menghitung harga jual secara instan.</li>
                <li>Periksa indikator QC Seimbang sebelum melakukan posting stok.</li>
            </ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0">
                <ul class="mb-0">
                    <li>Total rincian QC (diterima + rusak + ditolak + retur) harus sama persis dengan kuantitas datang.</li>
                    <li>Sistem menolak posting jika ada harga jual minimum yang lebih rendah atau sama dengan HPP final.</li>
                </ul>
            </div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Penerimaan 100 Dus Kopi. 95 Dus Diterima Baik, 3 Dus Rusak, 2 Dus Ditolak. Ongkir Rp 100.000 dialokasikan proporsional ke 95 Dus yang diterima baik. Klik tombol +20% untuk menentukan harga jual, lalu klik Simpan &amp; Posting Stok.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    @include('warehouse.goods-receipts._form', [
        'action' => route('warehouse.goods-receipts.store'),
        'method' => 'POST',
    ])
@endsection
