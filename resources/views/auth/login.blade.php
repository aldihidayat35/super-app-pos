@extends('layouts.metronic.auth')

@section('title', 'Masuk - ' . config('app.name'))

@section('content')
    <form method="POST" action="{{ route('login.store') }}" class="card shadow-sm p-10" novalidate>
        @csrf
        <div class="text-center mb-10"><h1 class="text-gray-900 fw-bold mb-3">Masuk ke GudangToko</h1><div class="text-muted">Gunakan email atau username yang diberikan administrator.</div></div>
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <x-metronic.form-group name="login" label="Email atau Username" required>
            <input id="login" type="text" name="login" value="{{ old('login') }}" class="form-control form-control-lg form-control-solid @error('login') is-invalid @enderror" autocomplete="username" autofocus required>
        </x-metronic.form-group>
        <x-metronic.form-group name="password" label="Kata Sandi" required>
            <input id="password" type="password" name="password" class="form-control form-control-lg form-control-solid @error('password') is-invalid @enderror" autocomplete="current-password" required>
        </x-metronic.form-group>
        <div class="d-flex flex-stack mb-8">
            <label class="form-check form-check-custom form-check-solid"><input class="form-check-input" type="checkbox" name="remember" value="1"><span class="form-check-label">Ingat saya</span></label>
            <a href="{{ route('password.request') }}" class="link-primary fw-semibold">Lupa kata sandi?</a>
        </div>
        <button type="submit" class="btn btn-primary w-100"><span class="indicator-label">Masuk</span></button>
    </form>
@endsection
