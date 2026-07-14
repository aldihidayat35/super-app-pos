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
    <link rel="stylesheet" href="{{ asset('assets/css/ki-icons-fallback.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/sidebar-custom.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/header-custom.css') }}">
    @vite(['resources/js/vendor.js', 'resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    <script>
        document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('gudangtoko-theme') || 'light');
        window.hostUrl = @json(asset('assets') . '/');
    </script>
</head>
<body id="kt_app_body" class="app-default" data-kt-app-header-fixed="true" data-kt-app-sidebar-enabled="true" data-kt-app-sidebar-fixed="true">
    <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
        <div class="app-page flex-column flex-column-fluid" id="kt_app_page">
            @include('layouts.metronic.partials.header')

            <div class="app-wrapper flex-column flex-row-fluid" id="kt_app_wrapper">
                @include('layouts.metronic.partials.sidebar')

                <main class="app-main flex-column flex-row-fluid" id="kt_app_main">
                    <div class="d-flex flex-column flex-column-fluid">
                        @include('layouts.metronic.partials.toolbar')
                        <div id="kt_app_content" class="app-content flex-column-fluid">
                            <div id="kt_app_content_container" class="app-container container-fluid">
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

    @include('layouts.metronic.partials.loading-overlay')
    @include('layouts.metronic.partials.flash-toast')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/dist/apexcharts.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
    @stack('scripts')
</body>
</html>
