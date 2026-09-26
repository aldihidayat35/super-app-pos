@extends('layouts.metronic.app')

@section('title', 'Edit Lokasi Toko - ' . config('app.name'))
@section('page_title', 'Edit Lokasi Toko')

@section('content')
    <x-metronic.card title="{{ $location->full_code }} — {{ $location->name }}">
        <form method="POST" action="{{ route('retail.locations.update', $location) }}">
            @include('retail.locations._form', ['method' => 'PUT'])
        </form>
    </x-metronic.card>
@endsection
