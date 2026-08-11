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
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/ki-icons-fallback.css') }}">
    @vite(['resources/js/vendor.js', 'resources/css/app.css', 'resources/js/app.js'])
    <script>document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('gudangtoko-theme') || 'light');</script>
</head>
<body class="app-blank auth-page">
    @php
        $companyName = \App\Models\SystemSetting::getCompanyName();
    @endphp
    <main class="auth-panel">
        <div class="auth-decoration auth-decoration--circle-left" aria-hidden="true"></div>
        <div class="auth-decoration auth-decoration--circle-right" aria-hidden="true"></div>
        <div class="auth-decoration auth-decoration--dots auth-decoration--dots-top" aria-hidden="true"></div>
        <div class="auth-decoration auth-decoration--dots auth-decoration--dots-bottom" aria-hidden="true"></div>

        <svg class="auth-warehouse-illustration" viewBox="0 0 420 320" aria-hidden="true">
            <g fill="none" stroke="currentColor" stroke-width="7">
                <path d="M18 296V102l112-76 112 76v194M48 296V126h164v170M75 153h46v42H75zm70 0h42v42h-42M75 219h112v77" />
                <path d="M254 296h106v-91H254zm14-91v-39h77v39M291 166v-37h42v37" />
            </g>
            <g fill="currentColor">
                <rect x="20" y="286" width="350" height="12" rx="6" />
                <rect x="87" y="235" width="42" height="51" rx="3" />
                <rect x="135" y="235" width="42" height="51" rx="3" />
                <circle cx="288" cy="305" r="8" /><circle cx="340" cy="305" r="8" />
            </g>
        </svg>

        <svg class="auth-boxes-illustration" viewBox="0 0 330 300" aria-hidden="true">
            <g fill="none" stroke="currentColor" stroke-width="6">
                <path d="M35 271h264M58 169h112v98H58zm112 0h103v98H170zM105 72h112v97H105z" />
                <path d="M93 169v35h42v-35m111 0v35h-42v-35M140 72v34h42V72" />
            </g>
        </svg>

        <section class="auth-content">
            <div class="auth-content__inner">@yield('content')</div>
            <footer class="auth-footer">
                <span class="auth-footer__icon" aria-hidden="true"><i class="ki-outline ki-cube-2 fs-2"></i></span>
                <span>&copy; {{ now()->year }} {{ $companyName }}</span>
            </footer>
        </section>
    </main>
    @include('layouts.metronic.partials.loading-overlay')
    @include('layouts.metronic.partials.flash-toast')
</body>
</html>
