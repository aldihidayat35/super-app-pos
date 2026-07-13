@extends('layouts.metronic.app')

@section('title', 'Edit Purchase Order - ' . config('app.name'))
@section('page_title', 'Edit Purchase Order')

@section('content')
    <x-metronic.card title="{{ $purchaseOrder->number }}">
        <form method="POST" action="{{ route('purchasing.purchase-orders.update', $purchaseOrder) }}">
            @include('purchasing.purchase-orders._form', ['method' => 'PUT'])
        </form>
    </x-metronic.card>
@endsection
