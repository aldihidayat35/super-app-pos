@extends('layouts.metronic.auth')
@section('title', '404 - ' . config('app.name'))
@section('content')
    <div class="text-center"><div class="fs-5x fw-bolder text-primary mb-5">404</div><h1 class="fw-bold text-gray-900 mb-4">Halaman Tidak Ditemukan</h1><p class="text-muted fs-5 mb-8">Halaman yang Anda cari tidak tersedia atau sudah dipindahkan.</p><a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="btn btn-primary">{{ auth()->check() ? 'Kembali ke Dashboard' : 'Kembali ke Login' }}</a></div>
@endsection
