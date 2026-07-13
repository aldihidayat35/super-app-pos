@extends('layouts.metronic.app')
@section('title', 'Dashboard Retail - ' . config('app.name'))
@section('page_title', 'Dashboard Retail')
@section('content')
    @include('dashboards._placeholder', ['title' => 'Operasional Toko', 'description' => 'Ringkasan penjualan, shift, stok, dan transaksi cabang.', 'metrics' => [['label'=>'Penjualan Hari Ini','value'=>'Rp0','icon'=>'ki-outline ki-chart-simple','color'=>'primary'],['label'=>'Transaksi','value'=>'0','icon'=>'ki-outline ki-receipt-square','color'=>'success'],['label'=>'Shift Aktif','value'=>'0','icon'=>'ki-outline ki-time','color'=>'warning'],['label'=>'Stok Kritis','value'=>'0','icon'=>'ki-outline ki-information','color'=>'danger']]])
@endsection
