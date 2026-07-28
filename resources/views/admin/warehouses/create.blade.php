@extends('layouts.metronic.app')

@section('title', 'Tambah Gudang - ' . config('app.name'))
@section('page_title', 'Tambah Gudang')
@section('page_guide')
    <x-metronic.page-guide id="admin-warehouse-create" title="Panduan Tambah Gudang Baru">
        <x-slot:function><p>Form ini digunakan untuk menambah gudang baru dalam sistem.</p></x-slot:function>
        <x-slot:workflow><ol><li>Isi kode gudang unik (tidak bisa diubah jika sudah punya transaksi).</li><li>Isi nama, kota, telepon, alamat, area layanan.</li><li>Pilih kepala gudang dari daftar user.</li><li>Isi kapasitas gudang jika ada.</li><li>Simpan gudang baru.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Kode Gudang:</strong> identitas unik, readonly jika ada transaksi.</li><li><strong>Nama Gudang:</strong> nama lengkap gudang (wajib).</li><li><strong>Kota:</strong> lokasi geografis.</li><li><strong>Nomor Telepon:</strong> kontak gudang.</li><li><strong>Kepala Gudang:</strong> user manajer gudang.</li><li><strong>Kapasitas:</strong> batas muatan maksimum.</li><li><strong>Alamat:</strong> alamat lengkap gudang.</li><li><strong>Area Layanan:</strong> wilayah distribusi.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Gudang baru menjadi pilihan pada form penerimaan barang, transfer stok, dan cabang.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Isi kode dan nama gudang.</li><li>Lengkapi alamat, kota, dan telepon.</li><li>Pilih kepala gudang dan isi kapasitas.</li><li>Simpan gudang.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0"><ul><li>Kode gudang tidak dapat diubah setelah ada transaksi.</li><li>Pastikan kepala gudang sudah dibuat sebagai user sistem.</li></ul></div></x-slot:warnings>
        <x-slot:example><p>Gudang GDG-SUR, nama "Gudang Surabaya", kota "Surabaya", kapasitas 15000 unit, kepala gudang Budi.</p></x-slot:example>
    </x-metronic.page-guide>
@endsection
@section('content')
    <x-metronic.page-title title="Tambah Gudang" description="Buat master gudang dan lokasi kerja warehouse." />
    @include('admin.warehouses._form')
@endsection
