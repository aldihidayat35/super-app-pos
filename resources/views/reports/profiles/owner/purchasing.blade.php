@extends('layouts.metronic.app')
@section('title', 'Pembelian & Kinerja Supplier – '.config('app.name'))
@section('hide_toolbar', '1')

@section('content')
    @include('reports.profiles.owner._executive-purchasing')
@endsection
