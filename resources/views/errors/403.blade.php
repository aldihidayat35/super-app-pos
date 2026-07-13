@extends('layouts.metronic.auth')
@section('title', '403 - ' . config('app.name'))
@section('content')
    <div class="text-center"><div class="fs-5x fw-bolder text-primary mb-5">403</div><h1 class="fw-bold text-gray-900 mb-4">Akses Ditolak</h1><p class="text-muted fs-5 mb-8">Anda tidak memiliki izin untuk membuka halaman ini.</p><a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="btn btn-primary">Kembali</a></div>
@endsection
