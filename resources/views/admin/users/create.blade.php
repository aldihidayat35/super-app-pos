@extends('layouts.metronic.app')

@section('title', 'Tambah Pengguna - ' . config('app.name'))
@section('page_title', 'Tambah Pengguna')

@section('content')
    <x-metronic.page-title title="Tambah Pengguna" description="Buat akun internal dan tentukan role awal melalui RBAC." />
    @include('admin.users._form')
@endsection
