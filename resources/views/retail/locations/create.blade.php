@extends('layouts.metronic.app')

@section('title', 'Tambah Lokasi Toko - ' . config('app.name'))
@section('page_title', 'Tambah Lokasi Toko (Zona, Rak, Bin)')

@section('content')
    <x-metronic.card title="Data Lokasi Toko">
        <form method="POST" action="{{ route('retail.locations.store') }}">
            @include('retail.locations._form')
        </form>
    </x-metronic.card>
@endsection
