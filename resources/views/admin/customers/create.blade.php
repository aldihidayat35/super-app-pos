@extends('layouts.metronic.app')
@section('title', 'Tambah Pelanggan')
@section('page_title', 'Tambah Pelanggan')

@section('page_guide')
    <x-metronic.page-guide id="admin-customer-create" title="Panduan Tambah Pelanggan Baru">
        <x-slot:function><p>Form untuk menambah pelanggan baru beserta dokumen usaha atau dokumen pendukung awal.</p></x-slot:function>
        <x-slot:workflow><ol><li>Pilih tipe pelanggan.</li><li>Isi nama usaha, pemilik, PIC, kontak, alamat, dan kota.</li><li>Set ring harga, minimum order, tempo pembayaran, dan batas maksimum kredit.</li><li>Set status verifikasi dan akun.</li><li>Upload dokumen usaha bila sudah tersedia.</li><li>Simpan pelanggan atau cetak formulir kosong untuk pendaftaran manual.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Tipe:</strong> kategori pelanggan (wajib).</li><li><strong>Kode:</strong> dibuat otomatis berdasarkan tipe dan tanggal.</li><li><strong>Nama Usaha:</strong> nama bisnis (wajib).</li><li><strong>Pemilik/PIC:</strong> kontak utama.</li><li><strong>Kota/WA/Email:</strong> informasi kontak.</li><li><strong>Ring Harga:</strong> kategori harga pelanggan.</li><li><strong>Minimum Order:</strong> minimal nilai order.</li><li><strong>Tempo Pembayaran:</strong> jumlah hari pembayaran setelah invoice.</li><li><strong>Batas Maksimum Kredit:</strong> batas utang pelanggan.</li><li><strong>Dokumen Usaha:</strong> NIB, NPWP, KTP, Akta, izin usaha, atau dokumen lain.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Pelanggan terverifikasi dapat order B2B. Limit kredit dipakai validasi order.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Isi identitas dan kontak.</li><li>Set ring harga dan limit.</li><li>Tambahkan dokumen jika ada.</li><li>Simpan.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0"><ul><li>Limit 0 berarti kredit tidak aktif.</li><li>Verifikasi wajib sebelum order.</li></ul></div></x-slot:warnings>
        <x-slot:example><p>PT Maju Jaya, tipe B2B, ring Gold, limit Rp 20jt, termin 30 hari, terverifikasi.</p></x-slot:example>
    </x-metronic.page-guide>
@endsection
@section('content') @include('admin.customers._form') @endsection
