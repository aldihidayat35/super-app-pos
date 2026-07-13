@extends('layouts.metronic.auth')

@section('title', 'Reset Kata Sandi - ' . config('app.name'))

@section('content')
    <form method="POST" action="{{ route('password.store') }}" class="card shadow-sm p-10" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="text-center mb-10"><h1 class="text-gray-900 fw-bold mb-3">Reset kata sandi</h1><div class="text-muted">Gunakan kata sandi baru yang kuat dan tidak dipakai di layanan lain.</div></div>
        @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <x-metronic.form-group name="email" label="Alamat Email" required>
            <input id="email" type="email" name="email" value="{{ old('email', $request->query('email')) }}" class="form-control form-control-lg form-control-solid @error('email') is-invalid @enderror" autocomplete="email" required>
        </x-metronic.form-group>
        <x-metronic.form-group name="password" label="Kata Sandi Baru" required>
            <input id="password" type="password" name="password" class="form-control form-control-lg form-control-solid @error('password') is-invalid @enderror" autocomplete="new-password" required>
        </x-metronic.form-group>
        <x-metronic.form-group name="password_confirmation" label="Konfirmasi Kata Sandi Baru" required>
            <input id="password_confirmation" type="password" name="password_confirmation" class="form-control form-control-lg form-control-solid" autocomplete="new-password" required>
        </x-metronic.form-group>
        <button type="submit" class="btn btn-primary w-100">Simpan Kata Sandi Baru</button>
    </form>
@endsection
