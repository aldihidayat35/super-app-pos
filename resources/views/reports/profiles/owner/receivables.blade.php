@extends('layouts.metronic.app')
@section('title', 'Piutang & Umur Tagihan – '.config('app.name'))
@section('hide_toolbar', '1')

@section('content')
    @include('reports.profiles.owner._executive-receivables')
@endsection
