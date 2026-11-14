@extends('layouts.metronic.auth')
@section('title', '419 - ' . config('app.name'))
@section('content')
    <meta http-equiv="refresh" content="0;url={{ route('login') }}">
    <div class="text-center">
        <div class="fs-5x fw-bolder text-primary mb-5">419</div>
        <h1 class="fw-bold text-gray-900 mb-4">Sesi Kedaluwarsa</h1>
        <p class="text-muted fs-5 mb-8">Sesi Anda telah berakhir. Mengalihkan ke halaman login...</p>
        <a href="{{ route('login') }}" class="btn btn-primary">Kembali ke Login</a>
    </div>
    <script>
        window.location.replace("{{ route('login') }}");
    </script>
@endsection
