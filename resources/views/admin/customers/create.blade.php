@extends('layouts.metronic.app')
@section('title', 'Tambah Pelanggan')
@section('page_title', 'Tambah Pelanggan')

@section('page_guide')
    <x-metronic.page-guide id="admin-customer-create" title="Panduan Tambah Pelanggan Baru">
        <x-slot:function><p>Form untuk menambah pelanggan B2B baru yang dapat melakukan pesanan melalui langganan.</p></x-slot:function>
        <x-slot:workflow><ol><li>Pilih tipe pelanggan.</li><li>Isi kode, nama usaha, pemilik, PIC.</li><li>Isi kota, WA, email.</li><li>Set ring harga, min order, termin, dan limit kredit.</li><li>Set status verifikasi dan akun.</li><li>Isi alamat dan catatan opsional.</li><li>Simpan.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Tipe:</strong> kategori pelanggan (wajib).</li><li><strong>Kode:</strong> identitas unik.</li><li><strong>Nama Usaha:</strong> nama bisnis (wajib).</li><li><strong>Pemilik/PIC:</strong> kontak utama.</li><li><strong>Kota/WA/Email:</strong> informasi kontak.</li><li><strong>Ring Harga:</strong> tier harga.</li><li><strong>Minimum Order:</strong> jumlah minimal.</li><li><strong>Termin:</strong> pembayaran hari.</li><li><strong>Limit Kredit:</strong> batas kredit.</li><li><strong>Verifikasi/Akun:</strong> status pelanggan.</li><li><strong>Alamat/Catatan:</strong> info tambahan.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Pelanggan terverifikasi dapat order B2B. Limit kredit dipakai validasi order.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Isi identitas dan kontak.</li><li>Set ring harga dan limit.</li><li>Verifikasi.</li><li>Simpan.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0"><ul><li>Limit 0 berarti kredit tidak aktif.</li><li>Verifikasi wajib sebelum order.</li></ul></div></x-slot:warnings>
        <x-slot:example><p>PT Maju Jaya, tipe B2B, ring Gold, limit Rp 20jt, termin 30 hari, terverifikasi.</p></x-slot:example>
    </x-metronic.page-guide>
@endsection
@section('content') @include('admin.customers._form') @endsection
