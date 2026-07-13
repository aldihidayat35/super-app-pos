@extends('layouts.metronic.app')

@section('title', 'Edit Penerimaan - ' . config('app.name'))
@section('page_title', 'Edit Penerimaan Draft')

@section('content')
    @include('warehouse.goods-receipts._form', [
        'action' => route('warehouse.goods-receipts.update', $receipt),
        'method' => 'PUT',
    ])
@endsection
