@extends('layouts.metronic.app')

@section('title', 'Edit Pengguna - ' . config('app.name'))
@section('page_title', 'Edit Pengguna')

@section('content')
    <x-metronic.page-title title="Edit Pengguna" description="Perbarui profil, status aktif, kata sandi, dan role pengguna." />
    @include('admin.users._form')
@endsection
