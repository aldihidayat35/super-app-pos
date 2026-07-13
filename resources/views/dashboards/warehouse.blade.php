@extends('layouts.metronic.app')
@section('title', 'Dashboard Gudang - ' . config('app.name'))
@section('page_title', 'Dashboard Gudang')
@section('content')
    @include('dashboards._placeholder', ['title' => 'Operasional Gudang', 'description' => 'Ringkasan stok dan dokumen gudang yang perlu ditindaklanjuti.', 'metrics' => [['label'=>'Produk Aktif','value'=>'0','icon'=>'ki-outline ki-box','color'=>'primary'],['label'=>'Stok Kritis','value'=>'0','icon'=>'ki-outline ki-information','color'=>'danger'],['label'=>'Penerimaan Hari Ini','value'=>'0','icon'=>'ki-outline ki-delivery-2','color'=>'success'],['label'=>'Transfer Diproses','value'=>'0','icon'=>'ki-outline ki-truck','color'=>'warning']]])
@endsection
