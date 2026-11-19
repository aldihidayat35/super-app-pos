@extends('layouts.metronic.app')
@section('title', 'Nilai & Kesehatan Stok – '.config('app.name'))
@section('hide_toolbar', '1')

@section('content')
    @include('reports.profiles.owner._executive-inventory')
@endsection

