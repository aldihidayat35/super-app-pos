@extends('layouts.metronic.app')

@section('title', 'Tambah Pengguna - ' . config('app.name'))
@section('page_title', 'Tambah Pengguna')

@section('page_guide')
    <x-metronic.page-guide id="admin-user-create" title="Panduan Tambah Pengguna">
        <x-slot:function><p>Form untuk menambah pengguna baru ke sistem dan memberikan role akses.</p></x-slot:function>
        <x-slot:workflow><ol><li>Isi nama, username, email.</li><li>Set password.</li><li>Pilih role.</li><li>Tetapkan lokasi kerja utama.</li><li>Simpan.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Nama:</strong> nama lengkap user (wajib).</li><li><strong>Username:</strong> login name unik (wajib).</li><li><strong>Email:</strong> alamat email (wajib).</li><li><strong>Password:</strong> password akun (wajib).</li><li><strong>Role:</strong> peran dan hak akses.</li><li><strong>Lokasi Kerja:</strong> gudang/cabang user.</li></ul></x-slot:parts>
        <x-slot:impacts><p>User baru dapat login dan mengakses modul sesuai role-nya.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Isi identitas dan kredensial.</li><li>Pilih role.</li><li>Tetapkan lokasi kerja.</li><li>Simpan.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0"><ul><li>Username dan email harus unik.</li><li>Pastikan role sesuai dengan tanggung jawab user.</li></ul></div></x-slot:warnings>
        <x-slot:example><p>Budi, username "budi", email budi@email.com, role "Staff Gudang", lokasi "Gudang Pusat".</p></x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title title="Tambah Pengguna" description="Buat akun internal dan tentukan role awal melalui RBAC." />
    @include('admin.users._form')
@endsection
