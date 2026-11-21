@extends('layouts.metronic.auth')

@section('title', 'Masuk - ' . config('app.name'))

@section('content')
    @php
        $companyName = \App\Models\SystemSetting::getCompanyName();
        $logo = \App\Models\SystemSetting::getCompanyLogo();
    @endphp

    <div class="text-center mb-8">
        @if ($logo)
            <img src="{{ $logo }}" alt="Logo {{ $companyName }}" loading="eager"
                style="display: block; width: auto; height: auto; max-width: 240px; max-height: 120px; margin: 0 auto 1.25rem; object-fit: contain;">
        @else
            <div class="d-inline-flex align-items-center justify-content-center bg-light-primary text-primary rounded-circle w-65px h-65px mb-4 shadow-sm">
                <i class="ki-outline ki-shop fs-2hx"></i>
            </div>
        @endif
        <h1 class="text-gray-900 fw-bolder fs-2x mb-2 tracking-tight">Masuk ke Sistem</h1>
        <p class="text-gray-500 fw-medium fs-6 mb-0">Masukkan email atau username yang terdaftar untuk melanjutkan.</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success d-flex align-items-center p-4 mb-6 rounded-3" role="status">
            <i class="ki-outline ki-check-circle fs-2 text-success me-3"></i>
            <span class="fs-6 fw-medium text-success">{{ session('status') }}</span>
        </div>
    @endif
    @if (session('warning'))
        <div class="alert alert-warning d-flex align-items-center p-4 mb-6 rounded-3" role="alert">
            <i class="ki-outline ki-information-5 fs-2 text-warning me-3"></i>
            <span class="fs-6 fw-medium text-warning">{{ session('warning') }}</span>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger d-flex align-items-center p-4 mb-6 rounded-3" role="alert">
            <i class="ki-outline ki-cross-circle fs-2 text-danger me-3"></i>
            <span class="fs-6 fw-medium text-danger">{{ $errors->first() }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="w-100" novalidate>
        @csrf

        <div class="fv-row mb-5">
            <label for="login" class="form-label fs-6 fw-semibold text-gray-800 mb-2">Email atau Username <span class="text-danger">*</span></label>
            <div class="position-relative">
                <i class="ki-outline ki-profile-circle position-absolute top-50 translate-middle-y ms-4 text-gray-400 fs-2" aria-hidden="true"></i>
                <input id="login" type="text" name="login" value="{{ old('login') }}" class="form-control form-control-lg form-control-solid ps-12 fs-6 @error('login') is-invalid @enderror" placeholder="Masukkan email atau username" autocomplete="username" autofocus required>
            </div>
            @error('login')<div class="invalid-feedback d-block mt-2 fs-7">{{ $message }}</div>@enderror
        </div>

        <div class="fv-row mb-5">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label for="password" class="form-label fs-6 fw-semibold text-gray-800 mb-0">Kata Sandi <span class="text-danger">*</span></label>
                <a href="{{ route('password.request') }}" class="link-primary fs-7 fw-semibold text-hover-primary">Lupa kata sandi?</a>
            </div>
            <div class="position-relative">
                <i class="ki-outline ki-lock-2 position-absolute top-50 translate-middle-y ms-4 text-gray-400 fs-2" aria-hidden="true"></i>
                <input id="password" type="password" name="password" class="form-control form-control-lg form-control-solid ps-12 pe-12 fs-6 @error('password') is-invalid @enderror" placeholder="Masukkan kata sandi" autocomplete="current-password" required>
                <button type="button" class="btn btn-icon btn-sm position-absolute top-50 end-0 translate-middle-y me-2 text-gray-500 text-hover-primary auth-password-toggle" data-password-toggle="password" aria-label="Tampilkan kata sandi" aria-pressed="false">
                    <i class="ki-outline ki-eye fs-2 auth-eye--show" aria-hidden="true"></i>
                    <i class="ki-outline ki-eye-slash fs-2 auth-eye--hide" aria-hidden="true"></i>
                </button>
            </div>
            @error('password')<div class="invalid-feedback d-block mt-2 fs-7">{{ $message }}</div>@enderror
        </div>

        <div class="d-flex justify-content-between align-items-center mb-7">
            <label class="form-check form-check-custom form-check-solid form-check-sm cursor-pointer">
                <input class="form-check-input" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                <span class="form-check-label text-gray-700 fs-7 fw-medium ms-2">Ingat saya di perangkat ini</span>
            </label>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100 py-4 fs-6 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2 mb-6">
            <span>Masuk ke Akun</span>
            <i class="ki-outline ki-arrow-right fs-4"></i>
        </button>

        <div class="pt-5 border-top border-gray-200">
            <div class="d-flex flex-wrap align-items-center justify-content-center gap-3 text-muted fs-8">
                <span class="d-inline-flex align-items-center gap-1.5"><i class="ki-outline ki-shield-tick text-success fs-6"></i> Enkripsi TLS/SSL</span>
                <span class="bullet bullet-dot bg-gray-300"></span>
                <span class="d-inline-flex align-items-center gap-1.5"><i class="ki-outline ki-shop text-primary fs-6"></i> POS & Gudang</span>
                <span class="bullet bullet-dot bg-gray-300"></span>
                <span class="d-inline-flex align-items-center gap-1.5"><i class="ki-outline ki-check-circle text-info fs-6"></i> Proteksi Margin</span>
            </div>
        </div>
    </form>
@endsection
