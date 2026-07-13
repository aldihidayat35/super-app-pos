@extends('layouts.metronic.auth')

@section('title', 'Verifikasi Email - ' . config('app.name'))

@section('content')
    <div class="card shadow-sm p-10">
        <div class="text-center mb-10"><h1 class="text-gray-900 fw-bold mb-3">Verifikasi email</h1><div class="text-muted">Link verifikasi dapat dikirim ulang jika Anda membutuhkannya.</div></div>
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        <form method="POST" action="{{ route('verification.send') }}" class="mb-5">
            @csrf
            <button type="submit" class="btn btn-primary w-100">Kirim Ulang Link Verifikasi</button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-light w-100">Keluar</button>
        </form>
    </div>
@endsection
