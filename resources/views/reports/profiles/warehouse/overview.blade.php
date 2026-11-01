@extends('layouts.metronic.app')
@section('title', 'Laporan Operasional Gudang - '.config('app.name'))
@section('hide_toolbar', '1')

@section('content')
    @include('reports.profiles.warehouse._operational-overview')
@endsection
