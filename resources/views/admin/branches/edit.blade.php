@extends('layouts.metronic.app')

@section('title', 'Edit Cabang - ' . config('app.name'))
@section('page_title', 'Edit Cabang')

@section('content')
    <x-metronic.page-title title="Edit Cabang/Toko" description="Perbarui cabang/toko. Kode dikunci setelah dipakai transaksi." />
    @include('admin.branches._form')
@endsection
