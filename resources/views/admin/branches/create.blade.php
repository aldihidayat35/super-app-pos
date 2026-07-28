@extends('layouts.metronic.app')

@section('title', 'Tambah Cabang - ' . config('app.name'))
@section('page_title', 'Tambah Cabang')

@section('page_guide')
    <x-metronic.page-guide id="admin-branch-create" title="Panduan Tambah Cabang Baru">
        <x-slot:function><p>Form untuk menambah cabang/toko baru yang melayani pelanggan B2B.</p></x-slot:function>
        <x-slot:workflow><ol><li>Isi kode dan nama cabang.</li><li>Pilih gudang pemasok utama.</li><li>Tunjuk kepala toko.</li><li>Isi target dan konfigurasi harga/closing.</li><li>Simpan.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Kode/Nama:</strong> identitas cabang (wajib).</li><li><strong>Gudang Pemasok Utama:</strong> gudang pemasok (wajib).</li><li><strong>Kepala Toko:</strong> user manajer cabang.</li><li><strong>Target Penjualan:</strong> target periode.</li><li><strong>Konfigurasi Harga/Closing:</strong> strategi harga dan closing harian.</li><li><strong>Alamat:</strong> lokasi fisik cabang.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Cabang baru menjadi lokasi pelanggan B2B dan pilihan stock transfer.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Isi kode dan nama.</li><li>Pilih gudang pemasok dan kepala toko.</li><li>Isi target dan konfigurasi.</li><li>Simpan.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0"><ul><li>Gudang pemasok harus aktif.</li></ul></div></x-slot:warnings>
        <x-slot:example><p>Cabang CAB-BDG, "Toko Bandung", gudang pemasok GDG-PUSAT, target Rp 50jt, closing wajib.</p></x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title title="Tambah Cabang/Toko" description="Buat master cabang/toko dan hubungkan ke gudang pemasok utama." />
    @include('admin.branches._form')
@endsection
