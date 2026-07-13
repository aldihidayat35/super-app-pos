@extends('layout-main.app')

@section('title', 'Dashboard - ' . config('app.name', 'Super App POS'))

@section('content')
    @include('layout.partials._content')
@endsection
