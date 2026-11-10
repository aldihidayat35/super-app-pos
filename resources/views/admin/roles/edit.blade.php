@extends('layouts.metronic.app')

@section('title', 'Edit Role - ' . config('app.name'))
@section('page_title', 'Edit Role')

@section('toolbar_actions')
    <a href="{{ route('admin.roles.show', $role) }}" class="btn btn-light me-2"><i class="ki-outline ki-arrow-left fs-5 me-1"></i> Kembali ke Rincian</a>
    @if(auth()->user()?->hasRole('super_admin') && $role->name !== 'super_admin')
        @if((int) $role->users->count() === 0)
            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" id="delete-role-edit-{{ $role->id }}" class="d-inline">
                @csrf
                @method('DELETE')
                <button
                    type="submit"
                    class="btn btn-light-danger"
                    data-confirm
                    data-confirm-form="delete-role-edit-{{ $role->id }}"
                    data-confirm-title="Hapus role ini?"
                    data-confirm-text="Role '{{ $role->label ?: $role->name }}' akan dihapus permanen dari sistem."
                    data-confirm-button="Ya, hapus role"
                >
                    <i class="ki-outline ki-trash fs-5 me-1"></i> Hapus Role
                </button>
            </form>
        @else
            <button
                type="button"
                class="btn btn-light-secondary opacity-50"
                disabled
                title="Role tidak dapat dihapus karena masih digunakan oleh {{ $role->users->count() }} pengguna"
            >
                <i class="ki-outline ki-trash fs-5 me-1"></i> Hapus Role (Masih Dipakai)
            </button>
        @endif
    @endif
@endsection

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
