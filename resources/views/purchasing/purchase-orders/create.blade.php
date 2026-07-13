@extends('layouts.metronic.app')

@section('title', 'Tambah Purchase Order - ' . config('app.name'))
@section('page_title', 'Form Purchase Order')

@section('content')
    <x-metronic.card title="PO Draft">
        <form method="POST" action="{{ route('purchasing.purchase-orders.store') }}">
            @include('purchasing.purchase-orders._form')
        </form>
    </x-metronic.card>
@endsection
