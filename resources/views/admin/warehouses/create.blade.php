@extends('layouts.metronic.app')

@section('title', 'Tambah Gudang - ' . config('app.name'))
@section('page_title', 'Tambah Gudang')

@section('content')
    <x-metronic.page-title title="Tambah Gudang" description="Buat master gudang dan lokasi kerja warehouse." />
    @include('admin.warehouses._form')
@endsection
