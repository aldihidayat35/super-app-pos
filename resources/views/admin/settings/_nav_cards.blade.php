{{-- Grid Menu Card Berikon untuk Navigasi Pengaturan Sistem (Sesuai Standar Tampilan Menu Master) --}}
@php
    $isGeneralActive = request()->routeIs('admin.settings.general.*');
    $isPrinterActive = request()->routeIs('admin.settings.printer.*');
    $isDocNumActive = request()->routeIs('admin.settings.document-numbers.*');
    $isStockAdjActive = request()->routeIs('admin.settings.stock-adjustment-access.*');
    $isWhatsappActive = request()->routeIs('admin.whatsapp.*');
@endphp

<div class="row row-cols-2 row-cols-sm-3 row-cols-xl-5 g-4 mb-6">
    {{-- Card 1: Identitas & Preferensi --}}
    <div class="col">
        <a href="{{ route('admin.settings.general.edit') }}"
           class="card card-flush h-100 hover-elevate-up border text-decoration-none shadow-xs {{ $isGeneralActive ? 'border-primary bg-light-primary bg-opacity-25' : 'border-gray-200 bg-body' }}">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="symbol symbol-40px symbol-circle {{ $isGeneralActive ? 'bg-primary text-white' : 'bg-light-primary text-primary' }} me-3 flex-shrink-0">
                    <span class="symbol-label"><i class="ki-outline ki-setting-2 fs-2 {{ $isGeneralActive ? 'text-white' : 'text-primary' }}"></i></span>
                </div>
                <div class="overflow-hidden">
                    <div class="fs-7 fw-bold text-truncate {{ $isGeneralActive ? 'text-primary' : 'text-gray-900' }}">Identitas &amp; Preferensi</div>
                    <div class="{{ $isGeneralActive ? 'text-gray-700' : 'text-muted' }} fs-8 text-truncate">Branding &amp; profil sistem</div>
                </div>
            </div>
        </a>
    </div>

    {{-- Card 2: Pengaturan Printer --}}
    <div class="col">
        <a href="{{ route('admin.settings.printer.edit') }}"
           class="card card-flush h-100 hover-elevate-up border text-decoration-none shadow-xs {{ $isPrinterActive ? 'border-success bg-light-success bg-opacity-25' : 'border-gray-200 bg-body' }}">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="symbol symbol-40px symbol-circle {{ $isPrinterActive ? 'bg-success text-white' : 'bg-light-success text-success' }} me-3 flex-shrink-0">
                    <span class="symbol-label"><i class="ki-outline ki-printer fs-2 {{ $isPrinterActive ? 'text-white' : 'text-success' }}"></i></span>
                </div>
                <div class="overflow-hidden">
                    <div class="fs-7 fw-bold text-truncate {{ $isPrinterActive ? 'text-success' : 'text-gray-900' }}">Pengaturan Printer</div>
                    <div class="{{ $isPrinterActive ? 'text-gray-700' : 'text-muted' }} fs-8 text-truncate">Thermal POS &amp; LX-310</div>
                </div>
            </div>
        </a>
    </div>

    {{-- Card 3: Nomor Dokumen --}}
    <div class="col">
        <a href="{{ route('admin.settings.document-numbers.index') }}"
           class="card card-flush h-100 hover-elevate-up border text-decoration-none shadow-xs {{ $isDocNumActive ? 'border-info bg-light-info bg-opacity-25' : 'border-gray-200 bg-body' }}">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="symbol symbol-40px symbol-circle {{ $isDocNumActive ? 'bg-info text-white' : 'bg-light-info text-info' }} me-3 flex-shrink-0">
                    <span class="symbol-label"><i class="ki-outline ki-document fs-2 {{ $isDocNumActive ? 'text-white' : 'text-info' }}"></i></span>
                </div>
                <div class="overflow-hidden">
                    <div class="fs-7 fw-bold text-truncate {{ $isDocNumActive ? 'text-info' : 'text-gray-900' }}">Nomor Dokumen</div>
                    <div class="{{ $isDocNumActive ? 'text-gray-700' : 'text-muted' }} fs-8 text-truncate">Format prefix &amp; sequence</div>
                </div>
            </div>
        </a>
    </div>

    {{-- Card 4: Akses Koreksi Stok --}}
    @if(auth()->user()?->can('stock_direct_adjustments.manage_access') || auth()->user()?->hasRole('super_admin'))
    <div class="col">
        <a href="{{ route('admin.settings.stock-adjustment-access.index') }}"
           class="card card-flush h-100 hover-elevate-up border text-decoration-none shadow-xs {{ $isStockAdjActive ? 'border-warning bg-light-warning bg-opacity-25' : 'border-gray-200 bg-body' }}">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="symbol symbol-40px symbol-circle {{ $isStockAdjActive ? 'bg-warning text-white' : 'bg-light-warning text-warning' }} me-3 flex-shrink-0">
                    <span class="symbol-label"><i class="ki-outline ki-key fs-2 {{ $isStockAdjActive ? 'text-white' : 'text-warning' }}"></i></span>
                </div>
                <div class="overflow-hidden">
                    <div class="fs-7 fw-bold text-truncate {{ $isStockAdjActive ? 'text-warning' : 'text-gray-900' }}">Akses Koreksi Stok</div>
                    <div class="{{ $isStockAdjActive ? 'text-gray-700' : 'text-muted' }} fs-8 text-truncate">Window izin koreksi stok</div>
                </div>
            </div>
        </a>
    </div>
    @endif

    {{-- Card 5: Koneksi WhatsApp --}}
    @can('whatsapp_connection.view')
    <div class="col">
        <a href="{{ route('admin.whatsapp.index') }}"
           class="card card-flush h-100 hover-elevate-up border text-decoration-none shadow-xs {{ $isWhatsappActive ? 'border-success bg-light-success bg-opacity-25' : 'border-gray-200 bg-body' }}">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="symbol symbol-40px symbol-circle {{ $isWhatsappActive ? 'bg-success text-white' : 'bg-light-success text-success' }} me-3 flex-shrink-0">
                    <span class="symbol-label"><i class="ki-outline ki-whatsapp fs-2 {{ $isWhatsappActive ? 'text-white' : 'text-success' }}"></i></span>
                </div>
                <div class="overflow-hidden">
                    <div class="fs-7 fw-bold text-truncate {{ $isWhatsappActive ? 'text-success' : 'text-gray-900' }}">Koneksi WhatsApp</div>
                    <div class="{{ $isWhatsappActive ? 'text-gray-700' : 'text-muted' }} fs-8 text-truncate">Gateway notifikasi resmi</div>
                </div>
            </div>
        </a>
    </div>
    @endcan
</div>

