@extends('layouts.metronic.app')

@section('title', 'Buat Pesanan ke Supplier (PO) - ' . config('app.name'))
@section('page_title', 'Form Purchase Order')

@section('content')
    <x-metronic.card title="Form Purchase Order - Pesanan ke Supplier (PO)">
        <form method="POST" action="{{ route('purchasing.purchase-orders.store') }}">
            @include('purchasing.purchase-orders._form')
        </form>
    </x-metronic.card>
@endsection
