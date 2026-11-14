@extends('layouts.metronic.app')

@section('title', 'Identitas & Preferensi Sistem - ' . config('app.name'))
@section('page_title', 'Pengaturan Umum')

@section('page_guide')
    <x-metronic.page-guide id="admin-settings-general" title="Panduan Identitas &amp; Preferensi Sistem">
        <x-slot:function>
            <p>Kelola identitas resmi perusahaan (nama, alamat, kontak, logo) serta preferensi operasional aplikasi seperti zona waktu, batas upload file, template nota, dan batas toleransi margin.</p>
        </x-slot:function>
        <x-slot:parts>
            <ul>
                <li><strong>Identitas Perusahaan:</strong> Nama bisnis, alamat lengkap, kontak telepon, email, dan logo resmi yang tampil pada header faktur dan struk.</li>
                <li><strong>Preferensi Operasional:</strong> Zona waktu (WIB/WITA/WIT), mata uang default, batas ukuran unggahan berkas, dan batasan margin keuntungan minimum.</li>
                <li><strong>Template Dokumen:</strong> Format layout bawaan untuk struk kasir dan faktur penjualan.</li>
            </ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Pengaturan ini memengaruhi kop dokumen transaksi cetak, perhitungan validasi margin penjualan kasir, dan audit identitas resmi toko.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol>
                <li>Ubah data identitas atau preferensi yang diinginkan pada formulir.</li>
                <li>Klik tombol <strong>Simpan Pengaturan</strong> di pojok kanan bawah.</li>
                <li>Gunakan menu kartu di bagian atas untuk berpindah ke konfigurasi Printer, Nomor Dokumen, Akses Koreksi Stok, atau WhatsApp.</li>
            </ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0">
                <i class="ki-outline ki-information-2 me-1"></i>
                Perubahan logo perusahaan akan langsung memperbarui tampilan kop faktur dotmatrix dan thermal di seluruh terminal kasir.
            </div>
        </x-slot:warnings>
        <x-slot:example>
            <p>PT GudangToko Nusantara, Telp: 021-5551234, Zona Waktu: Asia/Jakarta, Margin Min: 10%.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    {{-- Grid Menu Card Berikon untuk Navigasi Pengaturan Sistem --}}
    @include('admin.settings._nav_cards')

    {{-- Form Pengaturan Identitas & Preferensi --}}
    @include('admin.settings.partials._tab_general')
@endsection
