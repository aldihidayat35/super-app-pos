<header id="kt_app_header" class="app-header">
    <div class="app-container container-fluid d-flex align-items-stretch justify-content-between" id="kt_app_header_container">

        {{-- Left: mobile toggle + brand (logo & app name) --}}
        <div class="d-flex align-items-center overflow-hidden me-2">
            <button type="button" class="btn btn-icon btn-active-color-primary w-30px h-30px d-lg-none ms-n3 me-2 flex-shrink-0" id="kt_app_sidebar_mobile_toggle" aria-label="Buka menu" aria-controls="kt_app_sidebar">
                <i class="ki-outline ki-abstract-14 fs-1"></i>
            </button>

            @php
                $companyName = \App\Models\SystemSetting::getCompanyName();
                $companyLogo = \App\Models\SystemSetting::getCompanyLogo();
                $logoUrl = $companyLogo ?? asset('assets/media/logos/demo38-small.svg');

                $catalogUrl = null;
                $isCatalogActive = false;
                if (auth()->check()) {
                    if (auth()->user()->hasRole(['langganan_owner', 'langganan_pic'])) {
                        $catalogUrl = route('langganan.katalog.index');
                        $isCatalogActive = request()->routeIs('langganan.katalog.*');
                    } else {
                        $catalogUrl = route('admin.products.index');
                        $isCatalogActive = request()->routeIs('admin.products.*');
                    }
                }
            @endphp
            <a href="{{ route('dashboard') }}" class="d-flex align-items-center gap-2 text-decoration-none overflow-hidden">
                <img alt="{{ $companyName }}" src="{{ $logoUrl }}" class="gt-header-logo flex-shrink-0">
                <span class="text-gray-900 fw-bold fs-5 gt-header-brand-name">{{ $companyName }}</span>
            </a>
        </div>

        {{-- Right: actions --}}
        <div class="d-flex align-items-stretch flex-shrink-0 gap-2">
            {{-- Menu Katalog (Tampil di Desktop & Mobile) --}}
            @if($catalogUrl)
                <div class="d-flex align-items-center">
                    <a href="{{ $catalogUrl }}" 
                       class="gt-header-catalog-btn {{ $isCatalogActive ? 'active' : '' }}" 
                       title="Buka Katalog Produk"
                       aria-label="Katalog Produk">
                        <i class="ki-outline ki-cube-2 fs-5"></i>
                        <span class="gt-header-catalog-label">Katalog</span>
                    </a>
                </div>
            @endif

            {{-- Theme Toggle Pill --}}
            <div class="d-flex align-items-center">
                <div class="d-flex align-items-center gt-theme-toggle-pill">
                    <button type="button" class="btn btn-icon btn-theme-toggle" data-theme-value="light" title="Tema terang" aria-label="Tema terang">
                        <i class="ki-outline ki-sun fs-5"></i>
                    </button>
                    <button type="button" class="btn btn-icon btn-theme-toggle" data-theme-value="dark" title="Tema gelap" aria-label="Tema gelap">
                        <i class="ki-outline ki-moon fs-5"></i>
                    </button>
                </div>
            </div>

            @include('layouts.metronic.partials.user-menu')
        </div>
    </div>
</header>
