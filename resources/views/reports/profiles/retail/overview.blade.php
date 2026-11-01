@extends('layouts.metronic.app')
@section('title', 'Laporan Operasional Toko - '.config('app.name'))
@section('hide_toolbar', '1')

@section('content')
    @include('reports.profiles.retail._operational-overview')
@endsection
