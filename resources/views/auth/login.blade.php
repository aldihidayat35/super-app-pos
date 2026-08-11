@extends('layouts.metronic.auth')

@section('title', 'Masuk - ' . config('app.name'))

@section('content')
    @php
        $companyName = \App\Models\SystemSetting::getCompanyName();
    @endphp
    <form method="POST" action="{{ route('login.store') }}" class="card auth-login-card" novalidate>
        @csrf
        <header class="auth-login-header">
            @php $logo = \App\Models\SystemSetting::getCompanyLogo(); @endphp
            <span class="auth-brand-mark" aria-hidden="true">
                @if ($logo)
                    <img src="{{ $logo }}" alt="Logo {{ $companyName }}" class="auth-brand-logo-img" loading="lazy">
                @else
                    <svg viewBox="0 0 64 64" role="img">
                        <path d="M10 27 32 13l22 14M14 27h36M17 27v24m30-24v24" />
                        <path d="M22 34h20v17H22zM27 34v7h10v-7" />
                    </svg>
                @endif
            </span>
            <h1>Masuk ke {{ $companyName }}</h1>
            <p>Gunakan email atau username yang diberikan administrator.</p>
        </header>

        @if (session('status'))<div class="alert alert-success auth-alert" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger auth-alert" role="alert">{{ $errors->first() }}</div>@endif

        <div class="auth-field">
            <label for="login">Email atau Username</label>
            <div class="auth-input-wrap">
                <i class="ki-outline ki-profile-circle auth-input-icon" aria-hidden="true"></i>
                <input id="login" type="text" name="login" value="{{ old('login') }}" class="form-control @error('login') is-invalid @enderror" placeholder="Masukkan email atau username" autocomplete="username" autofocus required>
            </div>
            @error('login')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="auth-field">
            <label for="password">Kata Sandi</label>
            <div class="auth-input-wrap">
                <i class="ki-outline ki-lock-2 auth-input-icon" aria-hidden="true"></i>
                <input id="password" type="password" name="password" class="form-control auth-password-input @error('password') is-invalid @enderror" placeholder="Masukkan kata sandi" autocomplete="current-password" required>
                <button type="button" class="auth-password-toggle" data-password-toggle="password" aria-label="Tampilkan kata sandi" aria-pressed="false">
                    <svg class="auth-eye auth-eye--show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>
                    <svg class="auth-eye auth-eye--hide" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 6.1A10 10 0 0 1 12 6c6 0 9.5 6 9.5 6a15 15 0 0 1-2.4 3M6.2 6.2C3.8 8 2.5 12 2.5 12s3.5 6 9.5 6a9.8 9.8 0 0 0 3-.5M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                </button>
            </div>
            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="auth-login-actions">
            <label class="auth-remember"><input type="checkbox" name="remember" value="1" @checked(old('remember'))><span>Ingat saya</span></label>
            <a href="{{ route('password.request') }}">Lupa kata sandi?</a>
        </div>
        <button type="submit" class="btn auth-login-button w-100">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8l4 4-4 4M18 12H8m3 7H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h6"/></svg>
            <span>Masuk</span>
        </button>
    </form>
@endsection
