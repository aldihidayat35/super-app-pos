@extends('layouts.metronic.app')

@section('title', 'Pengajuan Retur - ' . config('app.name'))
@section('page_title', 'Pengajuan Retur')

@section('content')
    <x-metronic.page-title title="Form Pengajuan Retur" description="Catat barang yang dikembalikan berdasarkan dokumen transaksi asal.">
        <x-slot:actions>
            <a href="{{ route('returns.index') }}" class="btn btn-light">
                <i class="ki-outline ki-arrow-left fs-5 me-2"></i>Kembali
            </a>
        </x-slot:actions>
    </x-metronic.page-title>

    <form id="return-form" method="POST" action="{{ route('returns.store') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @include('returns._form')
    </form>
@endsection
