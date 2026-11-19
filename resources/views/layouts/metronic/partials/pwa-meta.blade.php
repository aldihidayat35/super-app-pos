@php
    $companyName = \App\Models\SystemSetting::getCompanyName();
    $companyLogo = \App\Models\SystemSetting::getCompanyLogo();
    $dynamicFavicon = $companyLogo ?? asset('favicon.ico');
    $pwaIcon192 = public_path('assets/media/pwa/icon-192x192.png');
    $pwaCacheKey = '?v=' . (file_exists($pwaIcon192) ? filemtime($pwaIcon192) : '1');
@endphp

{{-- Favicon Multi-Ukuran & Kompatibilitas Browser --}}
<link rel="shortcut icon" href="{{ $dynamicFavicon }}">
<link rel="icon" type="image/x-icon" href="{{ $dynamicFavicon }}">
@if(file_exists(public_path('favicon-32x32.png')))
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') . $pwaCacheKey }}">
@endif
@if(file_exists(public_path('favicon-16x16.png')))
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') . $pwaCacheKey }}">
@endif

{{-- PWA Manifest & Mobile Web App Meta Tags --}}
<link rel="manifest" href="{{ asset('manifest.webmanifest') . $pwaCacheKey }}">
<meta name="theme-color" content="#1B84FF" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#1E1E2D" media="(prefers-color-scheme: dark)">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $companyName }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/media/pwa/apple-touch-icon.png') . $pwaCacheKey }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('assets/media/pwa/icon-192x192.png') . $pwaCacheKey }}">
<link rel="icon" type="image/png" sizes="512x512" href="{{ asset('assets/media/pwa/icon-512x512.png') . $pwaCacheKey }}">
