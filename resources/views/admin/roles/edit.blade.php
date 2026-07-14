@extends('layouts.metronic.app')

@section('title', 'Edit Role - ' . config('app.name'))
@section('page_title', 'Edit Role')

@section('content')
    <x-metronic.page-title
        title="Edit Role"
        description="Perbarui metadata role dan matriks permission."
        help="Halaman ini dipakai untuk mengubah nama tampilan, keterangan, dan hak akses role. Perubahan permission akan berdampak ke semua pengguna yang memakai role ini."
    />
    @include('admin.roles._form')
@endsection
