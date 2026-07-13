@extends('layouts.metronic.app')

@section('title', 'Tambah Role - ' . config('app.name'))
@section('page_title', 'Tambah Role')

@section('content')
    <x-metronic.page-title title="Tambah Role" description="Buat role baru dan pilih permission yang sesuai." />
    @include('admin.roles._form')
@endsection
