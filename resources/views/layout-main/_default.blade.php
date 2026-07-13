<div class="d-flex flex-column flex-root app-root" id="kt_app_root">
    <div class="app-page flex-column flex-column-fluid" id="kt_app_page">
        @include('layout.partials._header')

        <div class="app-wrapper flex-column flex-row-fluid" id="kt_app_wrapper">
            @include('layout.partials._sidebar')

            <main class="app-main flex-column flex-row-fluid" id="kt_app_main">
                <div class="d-flex flex-column flex-column-fluid">
                    @yield('content')
                </div>

                @include('layout.partials._footer')
            </main>
        </div>
    </div>
</div>
