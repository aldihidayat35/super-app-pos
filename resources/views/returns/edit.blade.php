@extends('layouts.metronic.app')

@section('title', 'Ubah Draft Retur - ' . config('app.name'))
@section('page_title', 'Ubah Draft Retur')

@section('content')
    <x-metronic.page-title :title="'Ubah ' . $return->number" description="Perbarui draft retur sebelum diajukan ke pemeriksaan QC.">
        <x-slot:actions>
            <a href="{{ route('returns.show', $return) }}" class="btn btn-light">
                <i class="ki-outline ki-arrow-left fs-5 me-2"></i>Kembali
            </a>
        </x-slot:actions>
    </x-metronic.page-title>

    <form id="return-form" method="POST" action="{{ route('returns.update', $return) }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')
        @include('returns._form')
    </form>
@endsection
