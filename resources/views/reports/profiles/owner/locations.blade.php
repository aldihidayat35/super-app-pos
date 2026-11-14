@extends('layouts.metronic.app')
@section('title', 'Performa Cabang – '.config('app.name'))
@section('hide_toolbar', '1')

@section('content')
    @include('reports.profiles.owner._executive-locations')
@endsection
