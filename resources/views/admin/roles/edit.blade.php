@extends('layouts.metronic.app')

@section('title', 'Edit Role - ' . config('app.name'))
@section('page_title', 'Edit Role')

@section('page_guide')
    <x-metronic.page-guide id="admin-role-edit" title="Panduan Edit Role">
        <x-slot:function><p>Form mengubah metadata role dan matriks permission.</p></x-slot:function>
        <x-slot:workflow><ol><li>Ubah label dan deskripsi role.</li><li>Centang/Uncheck permission per modul.</li><li>Simpan.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Label/Deskripsi:</strong> metadata role.</li><li><strong>Permission Grid:</strong> centang hak akses per modul.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Perubahan permission berlaku instan untuk semua user dengan role ini.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Ubah metadata.</li><li>Update permission.</li><li>Simpan.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0"><ul><li>Role sistem tidak dapat diedit.</li><li>Hati-hati menghapus permission akses kritis.</li></ul></div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title
        title="Edit Role"
        description="Perbarui metadata role dan matriks permission."
        help="Halaman ini dipakai untuk mengubah nama tampilan, keterangan, dan hak akses role. Perubahan permission akan berdampak ke semua pengguna yang memakai role ini."
    />
    @include('admin.roles._form')
@endsection
