@extends('layouts.metronic.app')

@section('title', 'Pengaturan Umum Sistem - ' . config('app.name'))
@section('page_title', 'Pengaturan Umum')

@section('page_guide')
    <x-metronic.page-guide id="admin-settings-general" title="Panduan Konfigurasi Umum Sistem">
        <x-slot:function>
            <p>Halaman terpadu untuk mengelola seluruh pengaturan utama sistem: identitas perusahaan dan preferensi, konfigurasi printer dan struk/faktur, format nomor dokumen, izin koreksi stok langsung, serta koneksi integrasi WhatsApp dalam tab horizontal tanpa berpindah halaman.</p>
        </x-slot:function>
        <x-slot:parts>
            <ul>
                <li><strong>Identitas &amp; Preferensi:</strong> Nama perusahaan, logo, kontak, zona waktu, batas upload, dan proteksi margin.</li>
                <li><strong>Pengaturan Printer:</strong> Profil termal POS 80mm/58mm, ukuran kertas continuous form Epson LX-310, dan kustomisasi cetak.</li>
                <li><strong>Nomor Dokumen:</strong> Format penomoran otomatis dokumen PO, SO, GR, Invoice, Transfer, Retur, dll.</li>
                <li><strong>Akses Koreksi Stok:</strong> Pengendalian window waktu pembukaan akses koreksi stok bagi kepala cabang/gudang.</li>
                <li><strong>Koneksi WhatsApp:</strong> Integrasi gateway notifikasi WhatsApp perusahaan.</li>
            </ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Pengaturan ini memengaruhi branding, cetakan fisik kasir dan gudang, validasi audit nomor urut transaksi, serta notifikasi resmi ke pelanggan.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol>
                <li>Pilih tab horizontal yang ingin dikonfigurasi.</li>
                <li>Sesuaikan nilai parameter sesuai kebutuhan operasional.</li>
                <li>Klik tombol Simpan pada masing-masing tab. Halaman akan mempertahankan tab aktif.</li>
            </ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0">
                <ul>
                    <li>Perubahan format nomor dokumen hanya berlaku untuk dokumen baru ke depannya.</li>
                    <li>Aktivasi akses koreksi stok dicatat sebagai audit keamanan berisiko tinggi.</li>
                </ul>
            </div>
        </x-slot:warnings>
        <x-slot:example>
            <p>PT SuperApp POS Indonesia, printer termal 80mm, prefix invoice INV/{year}/{sequence}, integrasi WA terhubung.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    {{-- NAVIGASI TAB HORIZONTAL TERPUSAT --}}
    <div class="card card-flush shadow-xs border border-gray-200 mb-6">
        <div class="card-body py-3">
            <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x fs-6 border-transparent" id="settingsTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active fw-bold d-flex align-items-center py-3" data-bs-toggle="tab" href="#tab_general" role="tab">
                        <i class="ki-outline ki-setting-2 fs-4 me-2"></i> Identitas &amp; Preferensi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-bold d-flex align-items-center py-3" data-bs-toggle="tab" href="#tab_printer" role="tab">
                        <i class="ki-outline ki-printer fs-4 me-2"></i> Pengaturan Printer
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-bold d-flex align-items-center py-3" data-bs-toggle="tab" href="#tab_docnum" role="tab">
                        <i class="ki-outline ki-document fs-4 me-2"></i> Nomor Dokumen
                    </a>
                </li>
                @can('stock_direct_adjustments.manage_access')
                    <li class="nav-item">
                        <a class="nav-link fw-bold d-flex align-items-center py-3" data-bs-toggle="tab" href="#tab_stock_adjustment" role="tab">
                            <i class="ki-outline ki-key fs-4 me-2"></i> Akses Koreksi Stok
                        </a>
                    </li>
                @endcan
                @can('whatsapp_connection.view')
                    <li class="nav-item">
                        <a class="nav-link fw-bold d-flex align-items-center py-3" data-bs-toggle="tab" href="#tab_whatsapp" role="tab">
                            <i class="ki-outline ki-whatsapp fs-4 me-2"></i> Pengaturan WhatsApp
                        </a>
                    </li>
                @endcan
            </ul>
        </div>
    </div>

    {{-- KONTEN MASING-MASING TAB --}}
    <div class="tab-content" id="settingsTabContent">
        <div class="tab-pane fade show active" id="tab_general" role="tabpanel">
            @include('admin.settings.partials._tab_general')
        </div>

        <div class="tab-pane fade" id="tab_printer" role="tabpanel">
            @include('admin.settings.partials._tab_printer')
        </div>

        <div class="tab-pane fade" id="tab_docnum" role="tabpanel">
            @include('admin.settings.partials._tab_document_numbers')
        </div>

        @can('stock_direct_adjustments.manage_access')
            <div class="tab-pane fade" id="tab_stock_adjustment" role="tabpanel">
                @include('admin.settings.partials._tab_stock_adjustment')
            </div>
        @endcan

        @can('whatsapp_connection.view')
            <div class="tab-pane fade" id="tab_whatsapp" role="tabpanel">
                @include('admin.settings.partials._tab_whatsapp')
            </div>
        @endcan
    </div>
@endsection

@push('scripts')
<script>
    $(function() {
        // Handle URL Hash navigation on load
        var hash = window.location.hash;
        if (hash) {
            var triggerEl = document.querySelector('#settingsTabs a[href="' + hash + '"]');
            if (triggerEl) {
                var tab = new bootstrap.Tab(triggerEl);
                tab.show();
            }
        }

        // Sync URL Hash on tab switch without scrolling
        $('#settingsTabs a[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
            var target = $(e.target).attr('href');
            if (history.replaceState) {
                history.replaceState(null, null, target);
            } else {
                location.hash = target;
            }
        });
    });

    function openTestPrint(type) {
        var url = "{{ url('/admin/settings/printer/test') }}/" + type;
        var width = type === 'thermal' ? 420 : 850;
        var height = 650;
        var left = (screen.width - width) / 2;
        var top = (screen.height - height) / 2;
        window.open(url, 'TestPrintWindow', 'width=' + width + ',height=' + height + ',top=' + top + ',left=' + left + ',scrollbars=yes');
    }
</script>
@endpush
