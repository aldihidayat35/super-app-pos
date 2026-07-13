<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <base href="{{ url('/') }}/">
    <title>@yield('title', config('app.name', 'Super App POS'))</title>
    <meta charset="utf-8">
    <meta name="description" content="@yield('meta_description', 'Super App POS')">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="shortcut icon" href="{{ asset('assets/media/logos/favicon.ico') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700">
    <link rel="stylesheet" href="{{ asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/plugins/global/plugins.bundle.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.bundle.css') }}">
    @stack('styles')

    @include('partials.trackers._ga-tag-manager-for-head')
</head>
<body
    id="kt_app_body"
    data-kt-app-header-fixed="true"
    data-kt-app-header-fixed-mobile="true"
    data-kt-app-sidebar-enabled="true"
    data-kt-app-sidebar-fixed="true"
    data-kt-app-sidebar-hoverable="true"
    data-kt-app-sidebar-push-header="true"
    data-kt-app-sidebar-push-toolbar="true"
    data-kt-app-sidebar-push-footer="true"
    class="app-default"
>
    @include('partials.theme-mode._init')
    @include('partials.trackers._ga-tag-manager-for-body')

    @include('layout-main._default')

    @include('partials.engage-new._main')
    @include('partials._scrolltop')
    @include('partials.modals._upgrade-plan')
    @include('partials.modals._view-users')

    <script>window.hostUrl = "{{ asset('assets') }}/";</script>
    <script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('assets/js/scripts.bundle.js') }}"></script>
    <script src="{{ asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.js') }}"></script>
    <script src="https://cdn.amcharts.com/lib/5/index.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/percent.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/radar.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/map.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/geodata/worldLow.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/geodata/continentsLow.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/geodata/usaLow.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/geodata/worldTimeZonesLow.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/geodata/worldTimeZoneAreasLow.js"></script>
    <script src="{{ asset('assets/js/widgets.bundle.js') }}"></script>
    <script src="{{ asset('assets/js/custom/widgets.js') }}"></script>
    <script src="{{ asset('assets/js/custom/apps/chat/chat.js') }}"></script>
    <script src="{{ asset('assets/js/custom/utilities/modals/upgrade-plan.js') }}"></script>
    @stack('scripts')
</body>
</html>
