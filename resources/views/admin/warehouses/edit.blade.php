@extends('layouts.metronic.app')

@section('title', 'Edit Gudang - ' . config('app.name'))
@section('page_title', 'Edit Gudang')

@section('content')
    <x-metronic.page-title title="Edit Gudang" description="Perbarui master gudang. Kode dikunci setelah dipakai transaksi." />
    @include('admin.warehouses._form')
@endsection
