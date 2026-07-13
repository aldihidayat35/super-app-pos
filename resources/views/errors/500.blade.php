@extends('layouts.metronic.auth')
@section('title', '500 - ' . config('app.name'))
@section('content')
    <div class="text-center"><div class="fs-5x fw-bolder text-primary mb-5">500</div><h1 class="fw-bold text-gray-900 mb-4">Terjadi Kesalahan</h1><p class="text-muted fs-5 mb-8">Sistem mengalami kendala. Silakan coba beberapa saat lagi atau hubungi administrator.</p><a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="btn btn-primary">Kembali</a></div>
@endsection
