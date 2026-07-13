@extends('layouts.metronic.app')

@section('title', 'Edit Role - ' . config('app.name'))
@section('page_title', 'Edit Role')

@section('content')
    <x-metronic.page-title title="Edit Role" description="Perbarui metadata role dan matriks permission." />
    @include('admin.roles._form')
@endsection
