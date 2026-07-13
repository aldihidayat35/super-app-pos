@extends('layouts.metronic.app')

@section('title', 'Tambah Lokasi Gudang - ' . config('app.name'))
@section('page_title', 'Tambah Zona, Rak, atau Bin')

@section('content')
    <x-metronic.card title="Data Lokasi">
        <form method="POST" action="{{ route('warehouse.locations.store') }}">
            @include('warehouse.locations._form')
        </form>
    </x-metronic.card>
@endsection
