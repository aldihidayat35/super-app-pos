@extends('layouts.metronic.auth')

@section('title', 'Login Portal Langganan')

@section('content')
    <form method="POST" action="{{ route('langganan.login.store') }}" class="card shadow-sm p-10">
        @csrf
        <div class="text-center mb-8">
            <h1 class="fw-bold">Portal Langganan</h1>
            <div class="text-muted">Masuk dengan email atau username akun B2B aktif.</div>
        </div>
        <x-metronic.form-group name="login" label="Email atau Username" required>
            <input name="login" value="{{ old('login') }}" class="form-control form-control-lg form-control-solid" autofocus autocomplete="username">
        </x-metronic.form-group>
        <x-metronic.form-group name="password" label="Kata Sandi" required>
            <input type="password" name="password" class="form-control form-control-lg form-control-solid" autocomplete="current-password">
        </x-metronic.form-group>
        <label class="form-check form-check-custom form-check-solid mb-6">
            <input type="hidden" name="remember" value="0">
            <input class="form-check-input" type="checkbox" name="remember" value="1">
            <span class="form-check-label">Ingat saya</span>
        </label>
        <button class="btn btn-primary w-100">Masuk Portal</button>
        <div class="text-center mt-5"><a href="{{ route('langganan.password.request') }}">Lupa kata sandi?</a></div>
    </form>
@endsection
