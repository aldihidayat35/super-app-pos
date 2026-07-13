@extends('layouts.metronic.app')

@section('title', 'Tambah Cabang - ' . config('app.name'))
@section('page_title', 'Tambah Cabang')

@section('content')
    <x-metronic.page-title title="Tambah Cabang/Toko" description="Buat master cabang/toko dan hubungkan ke gudang pemasok utama." />
    @include('admin.branches._form')
@endsection
