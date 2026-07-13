@extends('layouts.metronic.auth')

@section('title', 'Konfirmasi Kata Sandi - ' . config('app.name'))

@section('content')
    <form method="POST" action="{{ route('password.confirm.store') }}" class="card shadow-sm p-10" novalidate>
        @csrf
        <div class="text-center mb-10"><h1 class="text-gray-900 fw-bold mb-3">Konfirmasi kata sandi</h1><div class="text-muted">Masukkan kata sandi saat ini sebelum melanjutkan aksi sensitif.</div></div>
        @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <x-metronic.form-group name="password" label="Kata Sandi" required>
            <input id="password" type="password" name="password" class="form-control form-control-lg form-control-solid @error('password') is-invalid @enderror" autocomplete="current-password" autofocus required>
        </x-metronic.form-group>
        <button type="submit" class="btn btn-primary w-100 mb-5">Konfirmasi</button>
        <a href="{{ route('dashboard') }}" class="btn btn-light w-100">Kembali</a>
    </form>
@endsection
