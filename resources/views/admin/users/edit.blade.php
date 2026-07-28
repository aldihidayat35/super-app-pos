@extends('layouts.metronic.app')

@section('title', 'Edit Pengguna - ' . config('app.name'))
@section('page_title', 'Edit Pengguna')

@section('page_guide')
    <x-metronic.page-guide id="admin-user-edit" title="Panduan Edit Pengguna">
        <x-slot:function><p>Form mengubah data pengguna, role, dan lokasi kerja.</p></x-slot:function>
        <x-slot:workflow><ol><li>Periksa data user.</li><li>Update nama, email, password jika perlu.</li><li>Ubah role jika diperlukan.</li><li>Simpan.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Identitas:</strong> nama, username, email.</li><li><strong>Password:</strong> opsional, hanya jika ingin reset.</li><li><strong>Role:</strong> peran pengguna.</li><li><strong>Lokasi Kerja:</strong> gudang/cabang default.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Perubahan role langsung memengaruhi akses user.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Periksa data.</li><li>Ubah field yang diperlukan.</li><li>Simpan.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0"><ul><li>Reset password akan memutuskan sesi aktif user.</li></ul></div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title title="Edit Pengguna" description="Perbarui profil, status aktif, kata sandi, dan role pengguna." />
    @include('admin.users._form')
@endsection
