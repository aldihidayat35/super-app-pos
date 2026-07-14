<header id="kt_app_header" class="app-header">
    <div class="app-container container-fluid d-flex align-items-stretch justify-content-between" id="kt_app_header_container">

        {{-- Left: mobile toggle + brand (logo & app name) --}}
        <div class="d-flex align-items-center">
            <button type="button" class="btn btn-icon btn-active-color-primary w-30px h-30px d-lg-none ms-n3 me-2" id="kt_app_sidebar_mobile_toggle" aria-label="Buka menu">
                <i class="ki-outline ki-abstract-14 fs-1"></i>
            </button>

            <a href="{{ route('dashboard') }}" class="d-flex align-items-center gap-2 text-decoration-none">
                <img alt="{{ config('app.name') }}" src="{{ asset('assets/media/logos/demo38-small.svg') }}" class="h-18px">
                <span class="text-gray-900 fw-bold fs-5">{{ config('app.name', 'GudangToko') }}</span>
            </a>
        </div>

        {{-- Right: actions --}}
        <div class="d-flex align-items-stretch flex-shrink-0">
            @include('layouts.metronic.partials.notification-area', ['compact' => true])
            <div class="d-flex align-items-center ms-2">
                <button type="button" class="btn btn-icon btn-active-light-primary" data-theme-value="light" title="Tema terang" aria-label="Tema terang"><i class="ki-outline ki-sun fs-1"></i></button>
                <button type="button" class="btn btn-icon btn-active-light-primary" data-theme-value="dark" title="Tema gelap" aria-label="Tema gelap"><i class="ki-outline ki-moon fs-1"></i></button>
            </div>
            @include('layouts.metronic.partials.user-menu')
        </div>
    </div>
</header>
