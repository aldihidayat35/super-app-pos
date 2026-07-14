@extends('layouts.metronic.auth')

@section('title', 'Lupa Kata Sandi Portal Langganan')

@section('content')
    <form method="POST" action="{{ route('langganan.password.email') }}" class="card shadow-sm p-10" novalidate>
        @csrf
        <div class="text-center mb-10">
            <h1 class="text-gray-900 fw-bold mb-3">Lupa kata sandi portal?</h1>
            <div class="text-muted">Masukkan email akun langganan. Link reset akan dikirim melalui mailer aktif.</div>
        </div>
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <x-metronic.form-group name="email" label="Alamat Email" required>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control form-control-lg form-control-solid @error('email') is-invalid @enderror" autocomplete="email" autofocus required>
        </x-metronic.form-group>
        <button type="submit" class="btn btn-primary w-100 mb-5">Kirim Link Reset</button>
        <a href="{{ route('langganan.login') }}" class="btn btn-light w-100">Kembali ke Login Portal</a>
    </form>
@endsection
