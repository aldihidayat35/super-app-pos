@extends('layouts.metronic.app')
@section('title', 'Dashboard Owner - ' . config('app.name'))
@section('page_title', 'Dashboard Owner')
@section('content')
    @include('dashboards._placeholder', ['title' => 'Ringkasan Bisnis', 'description' => 'Pantau performa gudang, toko, dan pelanggan B2B dari satu halaman.', 'metrics' => [['label'=>'Omzet Hari Ini','value'=>'Rp0','icon'=>'ki-outline ki-chart-line-up','color'=>'primary'],['label'=>'Nilai Stok','value'=>'Rp0','icon'=>'ki-outline ki-package','color'=>'success'],['label'=>'Piutang','value'=>'Rp0','icon'=>'ki-outline ki-wallet','color'=>'warning'],['label'=>'Perlu Approval','value'=>'0','icon'=>'ki-outline ki-shield-tick','color'=>'danger']]])
@endsection
