@extends('layouts.metronic.app')
@section('title', 'Dashboard Super Admin - ' . config('app.name'))
@section('page_title', 'Dashboard Super Admin')
@section('toolbar_actions')<a href="{{ route('admin.system.health') }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-pulse fs-4"></i>Kesehatan Sistem</a>@endsection
@section('content')
    @include('dashboards._placeholder', ['title' => 'Administrasi Sistem', 'description' => 'Pantau kesiapan konfigurasi dan pengguna aplikasi.', 'metrics' => [['label'=>'Pengguna Aktif','value'=>'0','icon'=>'ki-outline ki-people','color'=>'primary'],['label'=>'Role','value'=>'0','icon'=>'ki-outline ki-profile-user','color'=>'success'],['label'=>'Job Antrean','value'=>'0','icon'=>'ki-outline ki-abstract-26','color'=>'warning'],['label'=>'Error Hari Ini','value'=>'0','icon'=>'ki-outline ki-information','color'=>'danger']]])
@endsection
