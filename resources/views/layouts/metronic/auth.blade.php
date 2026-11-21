<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @include('layouts.metronic.partials.pwa-meta')
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700">
    <link rel="stylesheet" href="{{ asset('assets/vendor/metronic/css/style.bundle.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/ki-icons-fallback.css') }}">
    @vite(['resources/js/vendor.js', 'resources/css/app.css', 'resources/js/app.js'])
    <script>document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('gudangtoko-theme') || 'light');</script>
</head>
<body class="app-blank auth-page">
    {{-- Opening Splash Screen PWA --}}
    @include('layouts.metronic.partials.pwa-splash')

    @php
        $companyName = \App\Models\SystemSetting::getCompanyName();
        $isSingleColumn = View::hasSection('single_column')
            || isset($exception)
            || request()->routeIs('password.*')
            || request()->routeIs('verification.*')
            || request()->routeIs('langganan.password.*');
    @endphp

    @if ($isSingleColumn)
        <main class="d-flex flex-column flex-root min-vh-100 justify-content-center align-items-center p-6 auth-single-wrapper">
            <div class="w-100 max-w-500px mx-auto">
                @yield('content')
            </div>
            <footer class="mt-8 text-center text-muted fs-8">
                &copy; {{ now()->year }} {{ $companyName }}. Seluruh hak cipta dilindungi.
            </footer>
        </main>
    @else
        <main class="d-flex flex-column flex-lg-row flex-column-fluid min-vh-100 auth-split-wrapper">
            {{-- Form Side --}}
            <div class="d-flex flex-column flex-lg-row-fluid w-lg-50 p-6 p-md-10 p-xl-16 justify-content-between auth-form-side">
                {{-- Header Brand Info --}}
                <div class="d-flex align-items-center justify-content-between mb-8 mb-lg-12">
                    <a href="{{ route('home') }}" class="d-flex align-items-center gap-2 text-decoration-none">
                        <span class="d-inline-flex align-items-center justify-content-center bg-light-primary text-primary rounded-2 w-32px h-32px">
                            <i class="ki-outline ki-shop fs-4"></i>
                        </span>
                        <span class="text-gray-900 fw-bold fs-6 tracking-tight">{{ $companyName }}</span>
                    </a>
                    <span class="badge badge-light-primary fw-semibold fs-8 px-3 py-1.5 rounded-pill d-inline-flex align-items-center gap-1">
                        <span class="badge badge-primary badge-circle w-6px h-6px"></span> Portal Sistem
                    </span>
                </div>

                {{-- Content Form Area --}}
                <div class="d-flex flex-center flex-column flex-column-fluid my-auto">
                    <div class="w-100 max-w-450px mx-auto">
                        @yield('content')
                    </div>
                </div>

                {{-- Form Footer --}}
                <div class="d-flex flex-stack pt-8 text-muted fs-8 fw-medium">
                    <div>&copy; {{ now()->year }} {{ $companyName }}.</div>
                    <div class="d-flex align-items-center gap-3">
                        <span>v2.0 Enterprise</span>
                        <span>•</span>
                        <span>Multi-Gudang & POS</span>
                    </div>
                </div>
            </div>

            {{-- Enterprise Showcase Side (Desktop Only) --}}
            <div class="d-none d-lg-flex flex-lg-row-fluid w-lg-50 p-12 p-xl-16 flex-column justify-content-between text-white auth-showcase-side position-relative overflow-hidden">
                @sectionMissing('showcase')
                    {{-- Showcase Header Pill --}}
                    <div class="position-relative z-index-2">
                        <div class="d-inline-flex align-items-center gap-2.5 px-3.5 py-1.5 rounded-pill bg-white bg-opacity-10 border border-white border-opacity-15 text-white fs-7 fw-semibold">
                            <span class="badge badge-success badge-circle w-8px h-8px"></span>
                            <span>Enterprise Warehouse & POS Architecture</span>
                        </div>
                    </div>

                    {{-- Showcase Main Body --}}
                    <div class="position-relative z-index-2 my-auto py-8">
                        <h2 class="display-6 fw-bolder text-white mb-5 lh-base tracking-tight" style="max-width: 540px;">
                            Satu Platform Terpadu untuk Pergudangan, Kasir Retail & Grosir B2B.
                        </h2>
                        <p class="text-white-50 fs-5 mb-8 lh-lg" style="max-width: 500px;">
                            Sinkronisasi inventori multi-gudang dan cabang secara instan, pengelolaan rak bin terstruktur, serta transaksi kasir cepat dengan proteksi margin modal anti-rugi.
                        </p>

                        {{-- Feature Highlight Cards --}}
                        <div class="d-flex flex-column gap-3" style="max-width: 520px;">
                            {{-- Feature 1 --}}
                            <div class="auth-feature-card p-4 d-flex align-items-start gap-4">
                                <div class="w-45px h-45px rounded-3 bg-primary bg-opacity-20 border border-primary border-opacity-25 d-flex align-items-center justify-content-center flex-shrink-0 text-primary">
                                    <i class="ki-outline ki-cube-2 fs-2 text-info"></i>
                                </div>
                                <div>
                                    <h3 class="fs-6 fw-bold text-white mb-1">Manajemen Stok Multi-Lokasi & Rak Bin</h3>
                                    <p class="fs-7 text-white-50 mb-0">Kontrol stok gudang pusat hingga display toko dengan barcode scanner, pelacakan batch, dan audit opname presisi.</p>
                                </div>
                            </div>

                            {{-- Feature 2 --}}
                            <div class="auth-feature-card p-4 d-flex align-items-start gap-4">
                                <div class="w-45px h-45px rounded-3 bg-success bg-opacity-20 border border-success border-opacity-25 d-flex align-items-center justify-content-center flex-shrink-0 text-success">
                                    <i class="ki-outline ki-shop fs-2 text-success"></i>
                                </div>
                                <div>
                                    <h3 class="fs-6 fw-bold text-white mb-1">POS Kasir Cepat & Proteksi Anti-Rugi</h3>
                                    <p class="fs-7 text-white-50 mb-0">Validasi otomatis mencegah penjualan di bawah HPP modal, multi-metode pembayaran, dan cetak struk instan.</p>
                                </div>
                            </div>

                            {{-- Feature 3 --}}
                            <div class="auth-feature-card p-4 d-flex align-items-start gap-4">
                                <div class="w-45px h-45px rounded-3 bg-warning bg-opacity-20 border border-warning border-opacity-25 d-flex align-items-center justify-content-center flex-shrink-0 text-warning">
                                    <i class="ki-outline ki-chart-line-star fs-2 text-warning"></i>
                                </div>
                                <div>
                                    <h3 class="fs-6 fw-bold text-white mb-1">Laporan Eksekutif & Margin Real-Time</h3>
                                    <p class="fs-7 text-white-50 mb-0">Visibilitas penuh profitabilitas, perputaran barang cepat/lambat, serta monitoring piutang tempo terperinci.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Showcase Metric Footer --}}
                    <div class="position-relative z-index-2 pt-6 border-top border-white border-opacity-10 d-flex align-items-center justify-content-between" style="max-width: 520px;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="ki-outline ki-shield-tick fs-3 text-success"></i>
                            <span class="fs-7 text-white-50">100% Audit Trail & Mutasi Stok</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="ki-outline ki-time fs-3 text-info"></i>
                            <span class="fs-7 text-white-50">Respon Kasir &lt; 2 Detik</span>
                        </div>
                    </div>
                @else
                    @yield('showcase')
                @endif
            </div>
        </main>
    @endif

    @include('layouts.metronic.partials.loading-overlay')
    @include('layouts.metronic.partials.flash-toast')
    @include('layouts.metronic.partials.pwa-scripts')
</body>
</html>
