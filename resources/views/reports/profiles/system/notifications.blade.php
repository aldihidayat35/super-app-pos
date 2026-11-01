@extends('layouts.metronic.app')
@section('title', $currentReport['label'].' - '.config('app.name'))
@section('page_title', $currentReport['label'])
@section('content')
    @include('reports.profiles.system._content')
@endsection
