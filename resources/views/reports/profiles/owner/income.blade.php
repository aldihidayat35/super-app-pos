@extends('layouts.metronic.app')
@section('title', 'Laba Rugi & Finansial – '.config('app.name'))
@section('hide_toolbar', '1')

@section('content')
    @include('reports.profiles.owner._executive-income')
@endsection
