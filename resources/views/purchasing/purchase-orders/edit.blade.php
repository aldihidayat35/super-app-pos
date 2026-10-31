@extends('layouts.metronic.app')

@section('title', 'Edit Pesanan ke Supplier ' . $purchaseOrder->number . ' - ' . config('app.name'))
@section('page_title', 'Edit Pesanan ke Supplier (PO)')

@section('content')
    <x-metronic.card title="Edit Pesanan {{ $purchaseOrder->number }}">
        <form method="POST" action="{{ route('purchasing.purchase-orders.update', $purchaseOrder) }}">
            @include('purchasing.purchase-orders._form', ['method' => 'PUT'])
        </form>
    </x-metronic.card>
@endsection
