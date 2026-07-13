<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="shortcut icon" href="{{ asset('assets/media/logos/favicon.ico') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700">
    <link rel="stylesheet" href="{{ asset('assets/vendor/metronic/css/style.bundle.css') }}">
    @vite(['resources/js/vendor.js', 'resources/css/app.css', 'resources/js/app.js'])
    <script>document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('gudangtoko-theme') || 'light');</script>
</head>
<body class="app-blank">
    <main class="d-flex flex-column flex-lg-row flex-column-fluid auth-panel">
        <aside class="d-none d-lg-flex flex-lg-row-fluid w-lg-50 auth-brand-panel p-15 flex-column justify-content-center align-items-center text-center">
            <img src="{{ asset('assets/media/logos/demo38-small.svg') }}" class="h-60px mb-10" alt="GudangToko">
            <h1 class="text-white fw-bold fs-2qx mb-5">GudangToko</h1>
            <p class="text-white text-opacity-75 fs-5 mw-500px">Satu pusat kendali untuk gudang, toko internal, dan pelanggan langganan/B2B.</p>
        </aside>
        <section class="d-flex flex-column flex-lg-row-fluid w-lg-50 p-5 p-lg-10">
            <div class="d-flex flex-center flex-column flex-column-fluid">
                <div class="w-100 mw-450px">@yield('content')</div>
            </div>
            <div class="text-center text-muted fs-7">&copy; {{ now()->year }} {{ config('app.name') }}</div>
        </section>
    </main>
    @include('layouts.metronic.partials.loading-overlay')
    @include('layouts.metronic.partials.flash-toast')
</body>
</html>
