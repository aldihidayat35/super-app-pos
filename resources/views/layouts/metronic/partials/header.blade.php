<header id="kt_app_header" class="app-header">
    <div class="app-container container-fluid d-flex align-items-stretch justify-content-between" id="kt_app_header_container">
        <div class="d-flex align-items-center d-lg-none ms-n3">
            <button type="button" class="btn btn-icon btn-active-color-primary w-35px h-35px" id="kt_app_sidebar_mobile_toggle" aria-label="Buka menu"><i class="ki-outline ki-abstract-14 fs-2"></i></button>
        </div>
        <div class="d-flex align-items-center flex-grow-1"></div>
        <div class="d-flex align-items-stretch flex-shrink-0">
            @include('layouts.metronic.partials.notification-area', ['compact' => true])
            <div class="d-flex align-items-center ms-2">
                <button type="button" class="btn btn-icon btn-active-light-primary" data-theme-value="light" title="Tema terang"><i class="ki-outline ki-sun fs-2"></i></button>
                <button type="button" class="btn btn-icon btn-active-light-primary" data-theme-value="dark" title="Tema gelap"><i class="ki-outline ki-moon fs-2"></i></button>
            </div>
            @include('layouts.metronic.partials.user-menu')
        </div>
    </div>
</header>
