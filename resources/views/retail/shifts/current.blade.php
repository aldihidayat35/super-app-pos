@extends('layouts.metronic.app')

@section('title', 'Shift Aktif - ' . config('app.name'))
@section('page_title', 'Shift Kasir Aktif')

@push('styles')
<style>
    .shift-stat-card { transition: transform 0.15s ease, box-shadow 0.15s ease; border-radius: 0.75rem; }
    .shift-stat-card:hover { transform: translateY(-2px); box-shadow: var(--bs-box-shadow-sm); }
    .stat-icon { width: 44px; height: 44px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; }
    .section-title { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--bs-gray-600); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--bs-gray-200); }
    .table-shift th { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--bs-gray-600); border-bottom: 1px solid var(--bs-gray-200); padding: 0.75rem 1rem; }
    .table-shift td { padding: 0.85rem 1rem; vertical-align: middle; font-size: 0.875rem; }
    .payment-pill { display: inline-block; padding: 0.25rem 0.65rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
    .payment-cash { background: #d1fae5; color: #065f46; }
    .payment-bank_transfer { background: #dbeafe; color: #1e40af; }
    .payment-qris { background: #ede9fe; color: #5b21b6; }
    .payment-manual { background: #fef3c7; color: #92400e; }
    .payment-credit { background: #fce7f3; color: #9d174d; }
    .expense-proof-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 6px; cursor: pointer; border: 1px solid var(--bs-gray-200); }
</style>
@endpush

@section('toolbar_actions')
@endsection

@section('page_guide')
    <x-metronic.page-guide id="retail-shift-current" title="Panduan Shift Kasir Aktif">
        <x-slot:function>
            <p>Halaman ini memonitor status sesi kasir POS yang sedang berjalan secara real-time, termasuk penjualan tunai/non-tunai, pengeluaran kas kecil, dan estimasi kas di laci.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol>
                <li>Pastikan modal awal laci telah sesuai saat pembukaan shift.</li>
                <li>Gunakan kartu <strong>Buka Kasir POS</strong> untuk melayani transaksi penjualan retail.</li>
                <li>Catat setiap pengeluaran kas kecil kasir via <strong>Catat Pengeluaran</strong>.</li>
                <li>Saat sesi selesai, lakukan <strong>Tutup Shift</strong> dengan menghitung fisik kas.</li>
            </ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul>
                <li><strong>Kartu Navigasi Aksi:</strong> Akses cepat kasir POS, catat pengeluaran, tutup shift, riwayat shift, dan download PDF.</li>
                <li><strong>Informasi Sesi:</strong> Nomor shift, kasir, cabang toko, waktu buka, dan terminal POS.</li>
                <li><strong>Ringkasan Penjualan:</strong> Transaksi tunai, non-tunai, serta expected cash laci.</li>
                <li><strong>Tabel Riwayat Transaksi &amp; Pengeluaran:</strong> Log detail setiap mutasi kas selama shift.</li>
            </ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Pencatatan pengeluaran kas kecil memotong expected cash fisik dan tercatat dalam laporan operasional cabang.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol>
                <li>Klik tombol aksi yang diinginkan di barisan kartu navigasi atas.</li>
                <li>Pantau kalkulasi expected cash sebelum menutup shift.</li>
            </ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0">
                Pastikan seluruh transaksi tertunda diselesaikan sebelum melakukan proses tutup shift.
            </div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Sesi dibuka modal awal Rp 100.000, penjualan tunai Rp 500.000, pengeluaran kas Rp 50.000, maka Expected Cash adalah Rp 550.000.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    @if(!$shift)
        <div class="row row-cols-1 row-cols-sm-2 g-4 mb-6">
            <div class="col">
                <a href="{{ route('retail.shifts.open') }}" class="card card-flush h-100 hover-elevate-up border border-gray-200 text-decoration-none bg-body shadow-xs">
                    <div class="card-body p-4 d-flex align-items-center">
                        <div class="symbol symbol-40px symbol-circle bg-light-primary me-3 flex-shrink-0">
                            <span class="symbol-label"><i class="ki-outline ki-plus fs-2 text-primary"></i></span>
                        </div>
                        <div class="overflow-hidden">
                            <div class="fs-7 fw-bold text-gray-900 text-truncate">Buka Shift Baru</div>
                            <div class="text-muted fs-8 text-truncate">Mulai sesi kasir POS hari ini</div>
                        </div>
                    </div>
                </a>
            </div>
            @can('cash_shifts.view')
            <div class="col">
                <a href="{{ route('retail.shifts.index') }}" class="card card-flush h-100 hover-elevate-up border border-gray-200 text-decoration-none bg-body shadow-xs">
                    <div class="card-body p-4 d-flex align-items-center">
                        <div class="symbol symbol-40px symbol-circle bg-light-info me-3 flex-shrink-0">
                            <span class="symbol-label"><i class="ki-outline ki-time fs-2 text-info"></i></span>
                        </div>
                        <div class="overflow-hidden">
                            <div class="fs-7 fw-bold text-gray-900 text-truncate">Riwayat Shift</div>
                            <div class="text-muted fs-8 text-truncate">Daftar seluruh sesi kasir</div>
                        </div>
                    </div>
                </a>
            </div>
            @endcan
        </div>

        <x-metronic.card>
            <x-metronic.empty-state title="Belum Ada Shift Aktif" description="Buka shift kasir sebelum memulai transaksi penjualan POS." icon="ki-outline ki-time">
                <a href="{{ route('retail.shifts.open') }}" class="btn btn-primary"><i class="ki-outline ki-plus fs-5 me-1"></i>Buka Shift Baru</a>
            </x-metronic.empty-state>
        </x-metronic.card>
    @else
        {{-- Grid 5 Menu Card Aksi Navigasi Shift (Desain Bersih Sesuai Standar Metronic) --}}
        <div class="row row-cols-2 row-cols-sm-3 row-cols-xl-5 g-4 mb-6">
            {{-- 1. Buka Kasir POS --}}
            <div class="col">
                <a href="{{ route('retail.pos.index') }}" class="card card-flush h-100 hover-elevate-up border border-gray-200 text-decoration-none bg-body shadow-xs">
                    <div class="card-body p-4 d-flex align-items-center">
                        <div class="symbol symbol-40px symbol-circle bg-light-success me-3 flex-shrink-0">
                            <span class="symbol-label"><i class="ki-outline ki-shop fs-2 text-success"></i></span>
                        </div>
                        <div class="overflow-hidden">
                            <div class="fs-7 fw-bold text-gray-900 text-truncate">Buka Kasir POS</div>
                            <div class="text-muted fs-8 text-truncate">Lanjut ke POS &amp; penjualan</div>
                        </div>
                    </div>
                </a>
            </div>

            {{-- 2. Catat Pengeluaran --}}
            <div class="col">
                @can('expense', $shift)
                    <a href="{{ route('retail.shifts.expenses', $shift) }}" class="card card-flush h-100 hover-elevate-up border border-gray-200 text-decoration-none bg-body shadow-xs">
                        <div class="card-body p-4 d-flex align-items-center">
                            <div class="symbol symbol-40px symbol-circle bg-light-warning me-3 flex-shrink-0">
                                <span class="symbol-label"><i class="ki-outline ki-wallet fs-2 text-warning"></i></span>
                            </div>
                            <div class="overflow-hidden">
                                <div class="fs-7 fw-bold text-gray-900 text-truncate">Catat Pengeluaran</div>
                                <div class="text-muted fs-8 text-truncate">Petty cash &amp; biaya kasir</div>
                            </div>
                        </div>
                    </a>
                @else
                    <div class="card card-flush h-100 border border-gray-200 text-decoration-none bg-body shadow-xs opacity-75">
                        <div class="card-body p-4 d-flex align-items-center">
                            <div class="symbol symbol-40px symbol-circle bg-light-secondary me-3 flex-shrink-0">
                                <span class="symbol-label"><i class="ki-outline ki-lock fs-2 text-muted"></i></span>
                            </div>
                            <div class="overflow-hidden">
                                <div class="fs-7 fw-bold text-gray-900 text-truncate">Catat Pengeluaran</div>
                                <div class="text-muted fs-8 text-truncate">Akses tidak diizinkan</div>
                            </div>
                        </div>
                    </div>
                @endcan
            </div>

            {{-- 3. Tutup Shift / Closing --}}
            <div class="col">
                @if($shift->status->value === 'open' || $shift->status->value === 'rejected')
                    @can('close', $shift)
                        <a href="{{ route('retail.shifts.close', $shift) }}" class="card card-flush h-100 hover-elevate-up border border-gray-200 text-decoration-none bg-body shadow-xs">
                            <div class="card-body p-4 d-flex align-items-center">
                                <div class="symbol symbol-40px symbol-circle bg-light-danger me-3 flex-shrink-0">
                                    <span class="symbol-label"><i class="ki-outline ki-cross-circle fs-2 text-danger"></i></span>
                                </div>
                                <div class="overflow-hidden">
                                    <div class="fs-7 fw-bold text-gray-900 text-truncate">Tutup Shift</div>
                                    <div class="text-muted fs-8 text-truncate">Hitung fisik kas &amp; closing</div>
                                </div>
                            </div>
                        </a>
                    @else
                        <div class="card card-flush h-100 border border-gray-200 text-decoration-none bg-body shadow-xs opacity-75">
                            <div class="card-body p-4 d-flex align-items-center">
                                <div class="symbol symbol-40px symbol-circle bg-light-secondary me-3 flex-shrink-0">
                                    <span class="symbol-label"><i class="ki-outline ki-lock fs-2 text-muted"></i></span>
                                </div>
                                <div class="overflow-hidden">
                                    <div class="fs-7 fw-bold text-gray-900 text-truncate">Tutup Shift</div>
                                    <div class="text-muted fs-8 text-truncate">Hanya kasir bertugas</div>
                                </div>
                            </div>
                        </div>
                    @endcan
                @elseif($shift->status->value === 'closing_submitted')
                    <a href="{{ route('retail.shifts.close', $shift) }}" class="card card-flush h-100 hover-elevate-up border border-gray-200 text-decoration-none bg-body shadow-xs">
                        <div class="card-body p-4 d-flex align-items-center">
                            <div class="symbol symbol-40px symbol-circle bg-light-warning me-3 flex-shrink-0">
                                <span class="symbol-label"><i class="ki-outline ki-time fs-2 text-warning"></i></span>
                            </div>
                            <div class="overflow-hidden">
                                <div class="fs-7 fw-bold text-gray-900 text-truncate">Status Closing</div>
                                <div class="text-muted fs-8 text-truncate">Menunggu approval</div>
                            </div>
                        </div>
                    </a>
                @else
                    <div class="card card-flush h-100 border border-gray-200 text-decoration-none bg-body shadow-xs">
                        <div class="card-body p-4 d-flex align-items-center">
                            <div class="symbol symbol-40px symbol-circle bg-light-primary me-3 flex-shrink-0">
                                <span class="symbol-label"><i class="ki-outline ki-check-circle fs-2 text-primary"></i></span>
                            </div>
                            <div class="overflow-hidden">
                                <div class="fs-7 fw-bold text-gray-900 text-truncate">Status Shift</div>
                                <div class="text-muted fs-8 text-truncate">{{ $shift->status->label() }}</div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- 4. Riwayat Shift --}}
            <div class="col">
                @can('cash_shifts.view')
                    <a href="{{ route('retail.shifts.index') }}" class="card card-flush h-100 hover-elevate-up border border-gray-200 text-decoration-none bg-body shadow-xs">
                        <div class="card-body p-4 d-flex align-items-center">
                            <div class="symbol symbol-40px symbol-circle bg-light-primary me-3 flex-shrink-0">
                                <span class="symbol-label"><i class="ki-outline ki-time fs-2 text-primary"></i></span>
                            </div>
                            <div class="overflow-hidden">
                                <div class="fs-7 fw-bold text-gray-900 text-truncate">Riwayat Shift</div>
                                <div class="text-muted fs-8 text-truncate">Daftar seluruh sesi kasir</div>
                            </div>
                        </div>
                    </a>
                @else
                    <div class="card card-flush h-100 border border-gray-200 text-decoration-none bg-body shadow-xs opacity-75">
                        <div class="card-body p-4 d-flex align-items-center">
                            <div class="symbol symbol-40px symbol-circle bg-light-secondary me-3 flex-shrink-0">
                                <span class="symbol-label"><i class="ki-outline ki-time fs-2 text-muted"></i></span>
                            </div>
                            <div class="overflow-hidden">
                                <div class="fs-7 fw-bold text-gray-900 text-truncate">Riwayat Shift</div>
                                <div class="text-muted fs-8 text-truncate">Log sesi kasir</div>
                            </div>
                        </div>
                    </div>
                @endcan
            </div>

            {{-- 5. Download PDF --}}
            <div class="col">
                <a href="{{ route('retail.shifts.export.pdf', $shift) }}" target="_blank" class="card card-flush h-100 hover-elevate-up border border-gray-200 text-decoration-none bg-body shadow-xs">
                    <div class="card-body p-4 d-flex align-items-center">
                        <div class="symbol symbol-40px symbol-circle bg-light-info me-3 flex-shrink-0">
                            <span class="symbol-label"><i class="ki-outline ki-printer fs-2 text-info"></i></span>
                        </div>
                        <div class="overflow-hidden">
                            <div class="fs-7 fw-bold text-gray-900 text-truncate">Download PDF</div>
                            <div class="text-muted fs-8 text-truncate">Cetak rekapitulasi shift</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        {{-- Header Card --}}
        <div class="card mb-6 border border-gray-200 shadow-xs">
            <div class="card-body p-6">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-4">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <span class="text-muted fs-8 fw-bold text-uppercase ls-1">SESI KASIR POS</span>
                            <x-metronic.status-badge :status="$shift->status" />
                        </div>
                        <h1 class="fw-bolder text-gray-900 mb-1 fs-2x">{{ $shift->number }}</h1>
                        <div class="text-muted fs-7 d-flex flex-wrap align-items-center gap-2">
                            <span><i class="ki-outline ki-calendar fs-6 me-1"></i>Dibuka {{ $shift->opened_at?->format('d/m/Y H:i') }}</span>
                            @if($shift->status->isOpen() && $shift->opened_at)
                                <span class="badge badge-light-success py-1 px-2 fs-8"><i class="ki-outline ki-time fs-7 me-1 text-success"></i>Berjalan {{ $shift->opened_at->diffForHumans(null, true) }}</span>
                            @elseif($shift->status->isLocked())
                                <span class="badge badge-light-secondary py-1 px-2 fs-8">&bull; Ditutup {{ $shift->closed_at?->format('d/m/Y H:i') ?? $shift->approved_at?->format('d/m/Y H:i') ?? $shift->rejected_at?->format('d/m/Y H:i') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @if($shift->status->isOpen())
                            <span class="badge badge-light-success px-3 py-2 fs-7 fw-semibold d-inline-flex align-items-center gap-2">
                                <span class="bullet bullet-dot bg-success h-6px w-6px"></span>
                                Kasir Sedang Aktif
                            </span>
                        @else
                            <span class="badge badge-light-secondary px-3 py-2 fs-7 fw-semibold">
                                {{ $shift->status->label() }}
                            </span>
                        @endif
                    </div>
                </div>

                <hr class="my-5 border-gray-200">

                <div class="row g-4">
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon bg-light-primary text-primary"><i class="ki-outline ki-user fs-4"></i></div>
                            <div>
                                <div class="text-muted fs-8">Kasir Bertugas</div>
                                <div class="fw-bold fs-6 text-gray-800">{{ $shift->cashier?->name ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon bg-light-success text-success"><i class="ki-outline ki-shop fs-4"></i></div>
                            <div>
                                <div class="text-muted fs-8">Cabang Toko</div>
                                <div class="fw-bold fs-6 text-gray-800">{{ $shift->branch?->name ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon bg-light-info text-info"><i class="ki-outline ki-wallet fs-4"></i></div>
                            <div>
                                <div class="text-muted fs-8">Modal Awal Laci</div>
                                <div class="fw-bold fs-6 text-gray-900">{{ \App\Support\CurrencyFormatter::rupiah($shift->opening_cash_amount) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon bg-light-warning text-warning"><i class="ki-outline ki-screen fs-4"></i></div>
                            <div>
                                <div class="text-muted fs-8">Terminal POS</div>
                                <div class="fw-bold fs-6 text-gray-800">{{ $shift->terminal_code ?: 'POS Utama' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Summary Stats --}}
        <div class="row g-4 mb-6">
            <div class="col-sm-6 col-xl-3">
                <div class="card shift-stat-card border border-gray-200 shadow-xs h-100">
                    <div class="card-body p-5">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="text-muted fs-8 fw-bold text-uppercase ls-1">Total Transaksi</span>
                            <div class="stat-icon bg-light-primary text-primary"><i class="ki-outline ki-basket fs-4"></i></div>
                        </div>
                        <div class="fs-2hx fw-bolder text-gray-900 mb-1">{{ number_format((int) ($summary['sales_count'] ?? 0), 0, ',', '.') }}</div>
                        <div class="text-muted fs-8 d-flex align-items-center"><i class="ki-outline ki-check fs-8 me-1 text-success"></i>Penjualan &amp; retur kasir</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card shift-stat-card border border-gray-200 shadow-xs h-100">
                    <div class="card-body p-5">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="text-muted fs-8 fw-bold text-uppercase ls-1">Penjualan Tunai</span>
                            <div class="stat-icon bg-light-success text-success"><i class="ki-outline ki-coin fs-4"></i></div>
                        </div>
                        <div class="fs-2hx fw-bolder text-success mb-1">{{ \App\Support\CurrencyFormatter::rupiah($summary['cash_sales'] ?? 0) }}</div>
                        <div class="text-muted fs-8 d-flex align-items-center"><i class="ki-outline ki-arrow-down-left fs-8 me-1 text-success"></i>Penerimaan fisik di kasir</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card shift-stat-card border border-gray-200 shadow-xs h-100">
                    <div class="card-body p-5">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="text-muted fs-8 fw-bold text-uppercase ls-1">Penjualan Non Tunai</span>
                            <div class="stat-icon bg-light-info text-info"><i class="ki-outline ki-credit-card fs-4"></i></div>
                        </div>
                        <div class="fs-2hx fw-bolder text-info mb-1">{{ \App\Support\CurrencyFormatter::rupiah($summary['non_cash_sales'] ?? 0) }}</div>
                        <div class="text-muted fs-8 d-flex align-items-center"><i class="ki-outline ki-arrows-circle fs-8 me-1 text-info"></i>QRIS, transfer &amp; piutang</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card shift-stat-card border border-gray-200 shadow-xs h-100 bg-light-warning">
                    <div class="card-body p-5">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="text-gray-700 fs-8 fw-bold text-uppercase ls-1">Expected Cash</span>
                            <div class="stat-icon bg-white text-warning shadow-xs"><i class="ki-outline ki-calculator fs-4"></i></div>
                        </div>
                        <div class="fs-2hx fw-bolder text-warning mb-1">{{ \App\Support\CurrencyFormatter::rupiah($summary['expected_cash'] ?? 0) }}</div>
                        <div class="text-muted fs-8 d-flex align-items-center"><i class="ki-outline ki-information fs-8 me-1 text-warning"></i>Estimasi kas fisik di laci</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Breakdown Row --}}
        <div class="row g-4 mb-6">
            <div class="col-lg-6">
                <div class="card border border-gray-200 shadow-xs h-100">
                    <div class="card-header border-0 pt-5 pb-0">
                        <h3 class="card-title align-items-start flex-column mb-0">
                            <span class="card-label fw-bold fs-5 text-gray-900">Rincian Komposisi Pembayaran</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Distribusi transaksi penjualan dan arus kas keluar</span>
                        </h3>
                    </div>
                    <div class="card-body pt-4">
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light-subtle">
                                <span class="text-gray-700 fw-semibold"><i class="ki-outline ki-coin fs-5 text-success me-2"></i>Penjualan Tunai</span>
                                <span class="fw-bolder text-success fs-6">{{ \App\Support\CurrencyFormatter::rupiah($summary['cash_sales'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light-subtle">
                                <span class="text-gray-700 fw-semibold"><i class="ki-outline ki-credit-card fs-5 text-info me-2"></i>Transfer Bank</span>
                                <span class="fw-bolder text-info fs-6">{{ \App\Support\CurrencyFormatter::rupiah($summary['transfer_sales'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light-subtle">
                                <span class="text-gray-700 fw-semibold"><i class="ki-outline ki-qr-code fs-5 text-primary me-2"></i>QRIS</span>
                                <span class="fw-bolder text-primary fs-6">{{ \App\Support\CurrencyFormatter::rupiah($summary['qris_sales'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light-subtle">
                                <span class="text-gray-700 fw-semibold"><i class="ki-outline ki-note-2 fs-5 text-secondary me-2"></i>Pembayaran Manual / Lainnya</span>
                                <span class="fw-bolder text-gray-800 fs-6">{{ \App\Support\CurrencyFormatter::rupiah($summary['manual_sales'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light-subtle">
                                <span class="text-gray-700 fw-semibold"><i class="ki-outline ki-bill fs-5 text-warning me-2"></i>Penjualan Piutang / Bon</span>
                                <span class="fw-bolder text-warning fs-6">{{ \App\Support\CurrencyFormatter::rupiah($summary['receivable_sales'] ?? 0) }}</span>
                            </div>

                            <div class="separator separator-dashed my-2"></div>

                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light-subtle">
                                <span class="text-danger fw-semibold"><i class="ki-outline ki-arrow-back fs-5 text-danger me-2"></i>Refund Pelanggan</span>
                                <span class="fw-bolder text-danger fs-6">-{{ \App\Support\CurrencyFormatter::rupiah($summary['refunds'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light-subtle">
                                <span class="text-success fw-semibold"><i class="ki-outline ki-box fs-5 text-success me-2"></i>Refund Pemasok (Kas Masuk)</span>
                                <span class="fw-bolder text-success fs-6">+{{ \App\Support\CurrencyFormatter::rupiah($summary['supplier_refunds'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light-subtle">
                                <span class="text-danger fw-semibold"><i class="ki-outline ki-wallet fs-5 text-danger me-2"></i>Pengeluaran Kas Kecil (Petty Cash)</span>
                                <span class="fw-bolder text-danger fs-6">-{{ \App\Support\CurrencyFormatter::rupiah($summary['expenses'] ?? 0) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border border-gray-200 shadow-xs h-100">
                    <div class="card-header border-0 pt-5 pb-0">
                        <h3 class="card-title align-items-start flex-column mb-0">
                            <span class="card-label fw-bold fs-5 text-gray-900">Rekonsiliasi Saldo Kas (Laci POS)</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Kalkulasi kas fisik aktual vs saldo sistem</span>
                        </h3>
                    </div>
                    <div class="card-body pt-4">
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light-subtle">
                                <span class="text-gray-700 fw-semibold">Modal Awal Laci</span>
                                <span class="fw-bolder text-gray-900 fs-6">{{ \App\Support\CurrencyFormatter::rupiah($summary['opening_cash'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light-subtle">
                                <span class="text-success fw-semibold">+ Penjualan Tunai</span>
                                <span class="fw-bolder text-success fs-6">{{ \App\Support\CurrencyFormatter::rupiah($summary['cash_sales'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light-subtle">
                                <span class="text-success fw-semibold">+ Refund Pemasok Tunai</span>
                                <span class="fw-bolder text-success fs-6">{{ \App\Support\CurrencyFormatter::rupiah($summary['supplier_refunds'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light-subtle">
                                <span class="text-danger fw-semibold">- Refund Pelanggan Tunai</span>
                                <span class="fw-bolder text-danger fs-6">-{{ \App\Support\CurrencyFormatter::rupiah($summary['refunds'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light-subtle">
                                <span class="text-danger fw-semibold">- Pengeluaran Kas Kecil</span>
                                <span class="fw-bolder text-danger fs-6">-{{ \App\Support\CurrencyFormatter::rupiah($summary['expenses'] ?? 0) }}</span>
                            </div>

                            <div class="p-4 rounded bg-light-warning border border-warning border-dashed mt-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bolder text-gray-800 fs-6">Expected Cash (Estimasi Kas Fisik)</div>
                                        <div class="text-muted fs-8">Jumlah uang kas yang seharusnya ada di laci saat ini</div>
                                    </div>
                                    <span class="fw-bolder fs-3 text-warning">{{ \App\Support\CurrencyFormatter::rupiah($summary['expected_cash'] ?? 0) }}</span>
                                </div>
                            </div>

                            @if($shift->status->isLocked())
                                <div class="separator separator-dashed my-2"></div>
                                <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light-subtle">
                                    <span class="text-gray-700 fw-semibold">Uang Fisik Dihitung (Actual Cash)</span>
                                    <span class="fw-bolder fs-5 text-gray-900">{{ \App\Support\CurrencyFormatter::rupiah($summary['actual_cash'] ?? 0) }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light-subtle">
                                    <span class="text-gray-700 fw-semibold">Selisih Kas Fisik</span>
                                    @php $diff = floatval($summary['difference'] ?? 0); @endphp
                                    <span class="fw-bolder fs-5 {{ $diff > 0 ? 'text-primary' : ($diff < 0 ? 'text-danger' : 'text-success') }}">
                                        {{ \App\Support\CurrencyFormatter::rupiah(abs($diff)) }}
                                        <span class="badge badge-light-{{ $diff > 0 ? 'primary' : ($diff < 0 ? 'danger' : 'success') }} ms-1 fs-9">
                                            {{ $diff > 0 ? 'Lebih' : ($diff < 0 ? 'Kurang' : 'Pas') }}
                                        </span>
                                    </span>
                                </div>
                                @if($shift->discrepancy_reason)
                                    <div class="alert alert-warning py-3 px-4 mt-2 mb-0 d-flex align-items-start gap-2">
                                        <i class="ki-outline ki-information-5 fs-4 text-warning mt-1"></i>
                                        <div>
                                            <strong class="d-block mb-1">Alasan Selisih:</strong>
                                            <span class="text-gray-700">{{ $shift->discrepancy_reason }}</span>
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Transactions Table --}}
        <div class="card mb-6 border border-gray-200 shadow-xs">
            <div class="card-header border-0 pt-5 pb-2">
                <h3 class="card-title align-items-start flex-column mb-0">
                    <span class="card-label fw-bold fs-5 text-gray-900">Riwayat Transaksi POS</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">{{ count($shift->sales ?? []) }} transaksi selesai &amp; retur pada sesi shift ini</span>
                </h3>
            </div>
            <div class="card-body p-0">
                @if($shift->sales && $shift->sales->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle mb-0 gs-5">
                            <thead>
                                <tr class="text-muted fw-bold text-uppercase fs-8 bg-light">
                                    <th class="ps-5">No. Faktur</th>
                                    <th>Waktu</th>
                                    <th>Pelanggan</th>
                                    <th>Status</th>
                                    <th class="text-end">Subtotal</th>
                                    <th class="text-end">Diskon</th>
                                    <th class="text-end">Total Akhir</th>
                                    <th class="pe-5 text-center">Metode Bayar</th>
                                </tr>
                            </thead>
                            <tbody class="fw-semibold text-gray-700">
                                @foreach($shift->sales as $sale)
                                <tr>
                                    <td class="ps-5">
                                        <span class="fw-bolder text-gray-900 font-monospace">{{ $sale->number }}</span>
                                    </td>
                                    <td>{{ $sale->completed_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td>
                                        @if($sale->customer)
                                            <div class="fw-bold text-gray-800">{{ $sale->customer->business_name }}</div>
                                            <div class="text-muted fs-8">{{ $sale->customer->code }}</div>
                                        @else
                                            <span class="text-muted">Pelanggan Umum</span>
                                        @endif
                                    </td>
                                    <td>
                                        <x-metronic.status-badge :status="$sale->status" />
                                    </td>
                                    <td class="text-end">{{ \App\Support\CurrencyFormatter::rupiah($sale->subtotal_amount) }}</td>
                                    <td class="text-end text-muted">{{ \App\Support\CurrencyFormatter::rupiah($sale->discount_amount) }}</td>
                                    <td class="text-end fw-bolder text-gray-900">{{ \App\Support\CurrencyFormatter::rupiah($sale->grand_total_amount) }}</td>
                                    <td class="pe-5 text-center">
                                        @forelse($sale->payments as $payment)
                                            <span class="payment-pill payment-{{ $payment->method?->value ?? 'cash' }} me-1">
                                                {{ $payment->method?->label() ?? $payment->method?->value ?? 'Cash' }}
                                            </span>
                                        @empty
                                            <span class="text-muted fs-8">-</span>
                                        @endforelse
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-6">
                        <x-metronic.empty-state 
                            title="Belum Ada Transaksi" 
                            description="Belum ada transaksi POS yang dicatat pada sesi shift ini." 
                            icon="ki-outline ki-basket" />
                    </div>
                @endif
            </div>
        </div>

        {{-- Expenses Table --}}
        <div class="card mb-6 border border-gray-200 shadow-xs">
            <div class="card-header border-0 pt-5 pb-2">
                <div class="d-flex align-items-center justify-content-between w-100">
                    <h3 class="card-title align-items-start flex-column mb-0">
                        <span class="card-label fw-bold fs-5 text-gray-900">Pengeluaran Kas Kecil (Petty Cash)</span>
                        <span class="text-muted mt-1 fw-semibold fs-7">Total pengeluaran tercatat: {{ \App\Support\CurrencyFormatter::rupiah($summary['expenses'] ?? 0) }}</span>
                    </h3>
                    @can('expense', $shift)
                        <a href="{{ route('retail.shifts.expenses', $shift) }}" class="btn btn-sm btn-light-warning">
                            <i class="ki-outline ki-plus fs-5 me-1"></i>Tambah Pengeluaran
                        </a>
                    @endcan
                </div>
            </div>
            <div class="card-body p-0">
                @if($shift->expenses && $shift->expenses->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle mb-0 gs-5">
                            <thead>
                                <tr class="text-muted fw-bold text-uppercase fs-8 bg-light">
                                    <th class="ps-5">Waktu</th>
                                    <th>Kategori</th>
                                    <th>Metode Bayar</th>
                                    <th class="text-end">Nominal</th>
                                    <th>Catatan / Keterangan</th>
                                    <th class="text-center">Bukti Nota</th>
                                    <th class="pe-5">Dicatat Oleh</th>
                                </tr>
                            </thead>
                            <tbody class="fw-semibold text-gray-700">
                                @foreach($shift->expenses as $expense)
                                <tr>
                                    <td class="ps-5">{{ $expense->spent_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td><span class="badge badge-light-secondary fs-8">{{ ucfirst($expense->category) }}</span></td>
                                    <td><span class="payment-pill payment-{{ $expense->payment_method ?? 'cash' }}">{{ ucfirst(str_replace('_', ' ', $expense->payment_method ?? 'cash')) }}</span></td>
                                    <td class="text-end fw-bolder text-danger">-{{ \App\Support\CurrencyFormatter::rupiah($expense->amount) }}</td>
                                    <td class="text-gray-800">{{ $expense->notes ?? '-' }}</td>
                                    <td class="text-center">
                                        @if($expense->proof_path)
                                            <a href="{{ Storage::url($expense->proof_path) }}" target="_blank" class="d-inline-block">
                                                <x-metronic.image-preview url="{{ Storage::url($expense->proof_path) }}" title="Bukti Pengeluaran" classes="expense-proof-thumb" size="" />
                                            </a>
                                        @else
                                            <span class="text-muted fs-8">-</span>
                                        @endif
                                    </td>
                                    <td class="pe-5">{{ $expense->creator?->name ?? '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-6">
                        <x-metronic.empty-state 
                            title="Belum Ada Pengeluaran Kecil" 
                            description="Belum ada pengeluaran kas atau biaya operasional yang dicatat pada shift ini." 
                            icon="ki-outline ki-wallet" />
                    </div>
                @endif
            </div>
        </div>

        {{-- Cash Count (if closing submitted or locked) --}}
        @if($shift->status->isLocked() && $shift->cashCounts && $shift->cashCounts->isNotEmpty())
        <div class="card mb-6 border border-gray-200 shadow-xs">
            <div class="card-header border-0 pt-5 pb-2">
                <h3 class="card-title align-items-start flex-column mb-0">
                    <span class="card-label fw-bold fs-5 text-gray-900">Rincian Perhitungan Fisik Uang Tunai</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Rincian lembar dan koin saat penutupan shift</span>
                </h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle mb-0 gs-5">
                        <thead>
                            <tr class="text-muted fw-bold text-uppercase fs-8 bg-light">
                                <th class="ps-5">Denominasi</th>
                                <th>Jenis Pecahan</th>
                                <th class="text-center">Jumlah Lembar / Koin</th>
                                <th class="pe-5 text-end">Subtotal Nilai</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700">
                            @foreach($shift->cashCounts as $cc)
                            <tr>
                                <td class="ps-5"><strong>Rp {{ number_format($cc->denomination, 0, ',', '.') }}</strong></td>
                                <td>
                                    <span class="badge badge-light-{{ $cc->denomination >= 1000 ? 'primary' : 'warning' }} fs-8">
                                        {{ $cc->denomination >= 1000 ? 'Uang Kertas' : 'Uang Logam' }}
                                    </span>
                                </td>
                                <td class="text-center">{{ number_format($cc->quantity, 0, ',', '.') }}</td>
                                <td class="pe-5 text-end fw-bolder text-gray-900">{{ \App\Support\CurrencyFormatter::rupiah($cc->amount) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-light fw-bolder">
                                <td colspan="3" class="ps-5 text-end">Total Uang Fisik Terhitung:</td>
                                <td class="pe-5 text-end text-primary fs-5">{{ \App\Support\CurrencyFormatter::rupiah($shift->actual_cash_amount) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- Approval / Closing Info --}}
        @if($shift->status->isLocked())
        <div class="card mb-6 border border-gray-200 shadow-xs">
            <div class="card-header border-0 pt-5 pb-2">
                <h3 class="card-title align-items-start flex-column mb-0">
                    <span class="card-label fw-bold fs-5 text-gray-900">Informasi Penutupan &amp; Verifikasi</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Catatan penyerahan shift dan riwayat persetujuan supervisor</span>
                </h3>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6 col-lg-3">
                        <div class="text-muted fs-8 mb-1">Status Sesi</div>
                        <div class="fw-bold">{{ $shift->status->label() }}</div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="text-muted fs-8 mb-1">Waktu Penutupan</div>
                        <div class="fw-bold">{{ $shift->closed_at?->format('d/m/Y H:i') ?? '-' }}</div>
                    </div>
                    @if($shift->approved_by)
                    <div class="col-md-6 col-lg-3">
                        <div class="text-muted fs-8 mb-1">Diverifikasi Oleh</div>
                        <div class="fw-bold text-success">{{ $shift->approvals->firstWhere('approved_by', $shift->approved_by)?->actor?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="text-muted fs-8 mb-1">Catatan Verifikasi</div>
                        <div class="text-gray-800">{{ $shift->approval_notes ?? '-' }}</div>
                    </div>
                    @endif
                    @if($shift->rejected_at)
                    <div class="col-md-6 col-lg-3">
                        <div class="text-muted fs-8 mb-1">Ditolak Oleh</div>
                        <div class="fw-bold text-danger">{{ $shift->approvals->firstWhere('rejected_by', $shift->rejected_at)?->actor?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="text-muted fs-8 mb-1">Alasan Penolakan</div>
                        <div class="text-danger fw-semibold">{{ $shift->approval_notes ?? '-' }}</div>
                    </div>
                    @endif
                    @if($shift->handover_notes)
                    <div class="col-12">
                        <div class="text-muted fs-8 mb-1">Catatan Serah Terima Kasir</div>
                        <div class="p-3 rounded bg-light border border-gray-200 text-gray-800">{{ $shift->handover_notes }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

    @endif
@endsection
