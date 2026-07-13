@extends('layouts.metronic.app')

@section('title', 'Edit Lokasi Gudang - ' . config('app.name'))
@section('page_title', 'Edit Lokasi Gudang')

@section('content')
    <x-metronic.card title="{{ $location->full_code }}">
        <form method="POST" action="{{ route('warehouse.locations.update', $location) }}">
            @include('warehouse.locations._form', ['method' => 'PUT'])
        </form>
    </x-metronic.card>
@endsection
