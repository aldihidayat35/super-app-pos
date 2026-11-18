<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="@yield('html_classes')" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @php
        $dynamicLogo = \App\Models\SystemSetting::getCompanyLogo() ?? asset('assets/media/logos/favicon.ico');
    @endphp
    <link rel="shortcut icon" href="{{ $dynamicLogo }}">
    @include('layouts.metronic.partials.pwa-meta')
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700">
    <link rel="stylesheet" href="{{ asset('assets/vendor/metronic/css/style.bundle.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/ki-icons-fallback.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/sidebar-custom.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/header-custom.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/spa-transitions.css') }}">
    <style>
        .spa-progress-bar { position: fixed; top: 0; left: 0; height: 3.5px; width: 0%; background: linear-gradient(90deg, #059669, #10b981, #34d399); box-shadow: 0 0 12px rgba(16,185,129,.8), 0 0 5px rgba(52,211,153,.9); z-index: 999999; pointer-events: none; opacity: 0; }
    </style>
    @vite(['resources/js/vendor.js', 'resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    <script>
        document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('gudangtoko-theme') || 'light');
        window.hostUrl = @json(asset('assets') . '/');
    </script>
</head>
<body id="kt_app_body" class="app-default @yield('body_classes')" data-kt-app-header-fixed="true" data-kt-app-sidebar-enabled="true" data-kt-app-sidebar-fixed="true" data-kt-app-sidebar-hoverable="true" @yield('body_attributes')>
    <script>
        if (localStorage.getItem('gudangtoko-sidebar-pinned') === 'false' && window.innerWidth >= 992) {
            document.body.setAttribute('data-kt-app-sidebar-minimize', 'on');
        }
    </script>
    {{-- Progress bar instan untuk refresh halaman & inisialisasi PWA --}}
    <div id="spa-initial-progress-bar" class="spa-progress-bar" style="opacity: 1; width: 28%; transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s ease;"></div>
    <script>
        (function() {
            var bar = document.getElementById('spa-initial-progress-bar');
            if (!bar) return;
            var w = 28;
            var timer = setInterval(function() {
                if (w < 86) {
                    w += (86 - w) * 0.14;
                    bar.style.width = w + '%';
                }
            }, 120);
            function finish() {
                clearInterval(timer);
                bar.style.width = '100%';
                setTimeout(function() {
                    bar.style.opacity = '0';
                    setTimeout(function() {
                        if (bar && bar.parentNode) bar.parentNode.removeChild(bar);
                    }, 250);
                }, 160);
            }
            if (document.readyState === 'complete') {
                finish();
            } else {
                window.addEventListener('load', finish, { once: true });
                document.addEventListener('DOMContentLoaded', function() {
                    if (w < 72) {
                        w = 72;
                        bar.style.width = '72%';
                    }
                }, { once: true });
            }
        })();
    </script>
    <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
        <div class="app-page flex-column flex-column-fluid" id="kt_app_page">
            @include('layouts.metronic.partials.header')

            <div class="app-wrapper flex-column flex-row-fluid" id="kt_app_wrapper">
                @include('layouts.metronic.partials.sidebar')

                <main class="app-main flex-column flex-row-fluid mt-4 " id="kt_app_main" >
                    <div class="d-flex flex-column flex-column-fluid" id="kt_app_main_content">
                        @unless (trim($__env->yieldContent('hide_toolbar')) !== '')
                            @include('layouts.metronic.partials.toolbar')
                        @endunless
                        <div id="kt_app_content" class="app-content flex-column-fluid">
                            <div id="kt_app_content_container" class="app-container container-fluid mt-6">
                                @include('layouts.metronic.partials.notification-area')
                                @yield('content')
                            </div>
                        </div>
                    </div>
                    @include('layouts.metronic.partials.footer')
                </main>
            </div>
        </div>
    </div>

    @include('components.metronic.image-preview-modal')
    @include('layouts.metronic.partials.loading-overlay')
    @include('layouts.metronic.partials.flash-toast')
    @include('layouts.metronic.partials.pwa-scripts')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/dist/apexcharts.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
    @stack('scripts')
</body>
</html>
