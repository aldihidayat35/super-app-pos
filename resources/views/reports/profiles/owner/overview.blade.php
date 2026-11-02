@extends('layouts.metronic.app')
@section('title', 'Laporan Owner - '.config('app.name'))
@section('hide_toolbar', '1')

@section('content')
    @include('reports.profiles.owner._executive-overview')
@endsection
