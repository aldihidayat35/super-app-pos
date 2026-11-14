@extends('layouts.metronic.app')
@section('title', 'Rekapitulasi Pajak – '.config('app.name'))
@section('hide_toolbar', '1')

@section('content')
    @include('reports.profiles.owner._executive-tax')
@endsection
