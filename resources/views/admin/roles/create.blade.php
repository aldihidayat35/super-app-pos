@extends('layouts.metronic.app')

@section('title', 'Tambah Role - ' . config('app.name'))
@section('page_title', 'Tambah Role')

@section('page_guide')
    <x-metronic.page-guide id="admin-role-create" title="Panduan Tambah Role Baru">
        <x-slot:function><p>Form untuk menambah role baru dan mengatur permission aksesnya.</p></x-slot:function>
        <x-slot:workflow><ol><li>Isi nama internal dan label tampilan role.</li><li>Pilih permission per modul.</li><li>Simpan role baru.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Nama Internal:</strong> identifier teknis role.</li><li><strong>Label Tampilan:</strong> nama yang terlihat user.</li><li><strong>Permission:</strong> hak akses per modul/action.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Role baru dapat diassign ke user setelah dibuat.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Isi nama dan label.</li><li>Pilih permission.</li><li>Simpan.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0"><ul><li>Role kustom tidak dapat dihapus jika ada user yang menggunakan.</li></ul></div></x-slot:warnings>
        <x-slot:example><p>Role "Staff Gudang Branch B", permission stock.create, stock.read, stock.update.</p></x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title
        title="Tambah Role"
        description="Buat role baru dan pilih permission yang sesuai."
        help="Role adalah kelompok akses. Buat role baru jika ada jenis pekerjaan baru yang membutuhkan hak akses berbeda dari role yang sudah ada."
    />
    @include('admin.roles._form')
@endsection
