@extends('layouts.metronic.app')
@section('title', 'Tambah Supplier')
@section('page_title', 'Tambah Supplier')

@section('page_guide')
    <x-metronic.page-guide id="admin-supplier-create" title="Panduan Tambah Supplier Baru">
        <x-slot:function><p>Form untuk menambah supplier baru yang akan memasok barang ke gudang.</p></x-slot:function>
        <x-slot:workflow><ol><li>Isi kode dan nama supplier.</li><li>Isi kontak, kota, dan alamat.</li><li>Set termin pembayaran.</li><li>Simpan.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Kode:</strong> identitas unik.</li><li><strong>Nama:</strong> nama perusahaan.</li><li><strong>Kontak/Email/WA:</strong> informasi kontak.</li><li><strong>Kota:</strong> lokasi supplier.</li><li><strong>Termin:</strong> hari pembayaran.</li><li><strong>Alamat/Website:</strong> info tambahan.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Supplier aktif dapat dipilih saat membuat PO.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Isi kode, nama, dan kontak.</li><li>Set termin.</li><li>Simpan.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0"><ul><li>Pastikan data kontak akurat untuk komunikasi.</li></ul></div></x-slot:warnings>
        <x-slot:example><p>PT Sumber Rezeki, Bandung, termin 30 hari.</p></x-slot:example>
    </x-metronic.page-guide>
@endsection
@section('content') @include('admin.suppliers._form') @endsection
