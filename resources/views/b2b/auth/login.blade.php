@extends('layouts.metronic.auth')

@section('title', 'Login Portal Langganan')
@section('single_column', true)

@section('content')
    <form method="POST" action="{{ route('langganan.login.store') }}" class="w-100">
        @csrf
        <div class="text-center mb-8">
            <span class="d-inline-flex align-items-center justify-content-center bg-light-primary text-primary rounded-circle w-60px h-60px mb-4 shadow-sm">
                <i class="ki-outline ki-briefcase fs-2hx"></i>
            </span>
            <h1 class="text-gray-900 fw-bolder fs-2x mb-2">Portal Langganan</h1>
            <div class="text-gray-500 fs-6">Masuk dengan email atau username akun B2B aktif.</div>
        </div>
        <x-metronic.form-group name="login" label="Email atau Username" required>
            <input name="login" value="{{ old('login') }}" class="form-control form-control-lg form-control-solid" autofocus autocomplete="username">
        </x-metronic.form-group>
        <x-metronic.form-group name="password" label="Kata Sandi" required>
            <input type="password" name="password" class="form-control form-control-lg form-control-solid" autocomplete="current-password">
        </x-metronic.form-group>
        <div class="d-flex justify-content-between align-items-center mb-6">
            <label class="form-check form-check-custom form-check-solid form-check-sm">
                <input type="hidden" name="remember" value="0">
                <input class="form-check-input" type="checkbox" name="remember" value="1">
                <span class="form-check-label text-gray-700 fs-7 fw-medium ms-2">Ingat saya</span>
            </label>
            <a href="{{ route('langganan.password.request') }}" class="link-primary fs-7 fw-semibold">Lupa kata sandi?</a>
        </div>
        <button class="btn btn-primary btn-lg w-100 py-4 fw-bold">Masuk Portal</button>
    </form>
@endsection
