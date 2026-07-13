@extends('layouts.metronic.app')

@section('title', 'Buat Penerimaan - ' . config('app.name'))
@section('page_title', 'Form Penerimaan dan QC')

@section('content')
    @include('warehouse.goods-receipts._form', [
        'action' => route('warehouse.goods-receipts.store'),
        'method' => 'POST',
    ])
@endsection
