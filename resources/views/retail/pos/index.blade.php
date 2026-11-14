@extends('layouts.metronic.app')

@section('title', 'Kasir POS - ' . config('app.name'))
@section('page_title', 'Kasir POS')
@section('html_classes', 'pos-page-root')
@section('body_classes', 'pos-page')

@section('content')
    @if (!$activeShift || !$branch)
        <div class="pos-shift-required bg-body border rounded p-10 text-center">
            <i class="ki-outline ki-warning fs-3x text-warning"></i>
            <h1 class="fs-2 fw-bold mt-5">Shift kasir belum dibuka</h1>
            <p class="text-muted mb-6">Cabang POS ditentukan otomatis dari shift aktif. Buka shift sebelum memindai produk.
            </p>
            <a href="{{ route('retail.shifts.open') }}" class="btn btn-primary btn-lg"><i
                    class="ki-outline ki-wallet fs-4 me-2"></i>Buka Shift</a>
        </div>
    @else
        @if ($errors->any())
            <div class="alert alert-danger d-flex align-items-center mb-4"><i
                    class="ki-outline ki-information-5 fs-2 me-3"></i>
                <div>{{ $errors->first() }}</div>
            </div>
        @endif

        <div class="pos-shell" id="pos-workspace">
            <header
                class="pos-context-bar bg-body border rounded px-4 py-3 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex flex-wrap align-items-center gap-5">
                    <div><span class="text-muted fs-8 d-block">CABANG</span><strong><i
                                class="ki-outline ki-shop me-1"></i>{{ $branch->name }}</strong></div>
                    <div><span class="text-muted fs-8 d-block">KASIR</span><strong>{{ auth()->user()->name }}</strong></div>
                    <div><span class="text-muted fs-8 d-block">SHIFT</span><strong>{{ $activeShift->number }}</strong></div>
                    <div><span class="text-muted fs-8 d-block">TRANSAKSI</span><strong id="temporary-number">BARU</strong>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    @can('emergency_purchases.create')<a href="{{ route('retail.emergency.create', ['branch_id' => $branch->id]) }}" class="btn btn-light-warning">Pembelian Darurat</a>@endcan
                    <button type="button" id="open-holds" class="btn btn-light-warning"><i
                            class="ki-outline ki-time fs-5 me-1"></i>Hold (<span id="hold-count">{{ $holdCount }}</span>)
                        <kbd>F6</kbd></button>
                    <button type="button" id="open-return-modal" class="btn btn-light-danger fw-bold d-inline-flex align-items-center" title="Retur barang penjualan (F7)">
                        <i class="ki-outline ki-arrows-circle fs-4 me-1"></i>Retur <kbd class="ms-1">F7</kbd>
                    </button>
                    <a href="{{ route('retail.shifts.current') }}" class="btn btn-light-info fw-bold d-inline-flex align-items-center" id="btn-shift-detail" title="Detail shift aktif dan rekap kasir">
                        <i class="ki-outline ki-time fs-4 me-1"></i>Detail Shift
                    </a>
                </div>
            </header>

            <div class="pos-workspace-row">
                <section class="pos-section-products" aria-labelledby="product-finder-title">
                    <div class="pos-panel bg-body border rounded h-100 d-flex flex-column">
                        <div class="p-4 border-bottom">
                            <h2 id="product-finder-title" class="fs-4 fw-bold mb-3">Cari Produk</h2>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light"><i class="ki-outline ki-search fs-2"></i></span>
                                <input id="pos-scanner" type="search" class="form-control" autocomplete="off" autofocus
                                    placeholder="Scan barcode / Cari produk" aria-label="Scan barcode atau cari produk">
                                <span class="input-group-text bg-light"><kbd>F2</kbd></span>
                            </div>
                            <div id="scanner-message" class="fs-7 mt-2 text-muted" aria-live="polite">Scan barcode lalu
                                Enter, atau ketik nama/SKU untuk mencari.</div>
                            <div class="row g-2 mt-2">
                                <div class="col-6">
                                    <select id="brand-filter" class="form-select form-select-sm"
                                        data-placeholder="Semua merek" data-allow-clear="true">
                                        <option value="">Semua merek</option>
                                        @foreach ($brands as $brand)
                                            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 d-flex align-items-center">
                                    <label class="form-check form-switch mb-0"><input id="stock-filter"
                                            class="form-check-input" type="checkbox" checked><span
                                            class="form-check-label fs-7">Hanya stok tersedia</span></label>
                                </div>
                            </div>
                            <div id="category-filter" class="d-flex gap-2 overflow-auto pt-3 pb-1">
                                <button type="button" class="btn btn-sm btn-primary pos-category active"
                                    data-category="">Semua</button>
                                @foreach ($categories as $category)
                                    <button type="button" class="btn btn-sm btn-light pos-category flex-shrink-0"
                                        data-category="{{ $category->id }}">{{ $category->name }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div id="product-results" class="pos-product-results p-4 flex-grow-1">
                            <div class="text-center text-muted py-10"><span
                                    class="spinner-border spinner-border-sm me-2"></span>Memuat produk...</div>
                        </div>
                        <div class="px-4 pb-4"><button type="button" id="load-more-products"
                                class="btn btn-sm btn-light w-100 d-none">Muat lebih banyak</button></div>
                    </div>
                </section>

                <section class="pos-section-cart" aria-labelledby="cart-title">
                    <div class="pos-panel bg-body border rounded h-100 d-flex flex-column">
                        <div class="p-4 border-bottom">
                            <div class="d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <h2 id="cart-title" class="fs-4 fw-bold mb-0">Keranjang</h2><span id="cart-item-label"
                                        class="text-muted fs-7">0 item</span>
                                </div>
                                <div class="pos-customer-wrap">
                                    <label for="customer_id" class="text-muted fs-8 mb-1 d-block">PELANGGAN <kbd>F4</kbd></label>
                                    <div class="d-flex align-items-center gap-1">
                                        <div class="flex-grow-1 min-w-0">
                                            <select id="customer_id" class="form-select form-select-sm"
                                                data-placeholder="Pelanggan Umum" data-allow-clear="true">
                                                <option value="">Pelanggan Umum</option>
                                                @foreach ($customers as $customer)
                                                    @php
                                                        $creditLimit = (float) ($customer->credit_limit ?? 0);
                                                        $receivableBalance = (float) ($customer->receivable_balance ?? 0);
                                                        $availableCredit = max(0, $creditLimit - $receivableBalance);
                                                        $minOrder = (float) ($customer->minimum_order ?? 0);
                                                    @endphp
                                                    <option value="{{ $customer->id }}"
                                                        data-payment-terms="{{ (int) ($customer->payment_term_days ?? 0) }}"
                                                        data-credit-limit="{{ $creditLimit }}"
                                                        data-receivable-balance="{{ $receivableBalance }}"
                                                        data-available-credit="{{ $availableCredit }}"
                                                        data-minimum-order="{{ $minOrder }}"
                                                    >{{ $customer->business_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="button" class="btn btn-icon btn-sm btn-light-primary flex-shrink-0" id="btn-quick-customer" data-bs-toggle="modal" data-bs-target="#modal-quick-customer" title="Tambah Pelanggan Baru" aria-label="Tambah Pelanggan Baru">
                                            <i class="ki-outline ki-plus fs-4"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div id="customer-info-strip" class="d-none bg-light-primary border border-primary border-dashed rounded p-2 mt-3">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 fs-8">
                                    <div id="customer-min-order-chip" class="d-flex align-items-center gap-1">
                                        <span class="text-muted fw-semibold">Min. Order:</span>
                                        <span id="customer-min-order-val" class="fw-bold">Rp 0</span>
                                    </div>
                                    <div id="customer-credit-limit-chip" class="d-flex align-items-center gap-1">
                                        <span class="text-muted fw-semibold">Sisa Limit:</span>
                                        <span id="customer-credit-limit-val" class="fw-bold">Rp 0</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pos-cart-scroll flex-grow-1">
                            <div id="cart-empty" class="text-center py-15 px-5">
                                <i class="ki-outline ki-credit-cart fs-3x text-muted"></i>
                                <div class="fw-bold fs-5 mt-4">Keranjang masih kosong</div>
                                <div class="text-muted">Scan barcode atau pilih produk dari panel kiri.</div>
                            </div>
                            <div id="cart-table-wrap" class="d-none p-3">
                                <div id="cart-items" class="d-grid gap-2"></div>
                            </div>
                        </div>

                        <footer class="pos-cart-footer border-top p-4">
                            <div id="cart-alert" class="alert alert-warning py-2 px-3 fs-7 d-none mb-3"></div>
                            <div class="row align-items-end g-3">
                                <div class="col-sm-5">
                                    <div class="d-flex justify-content-between fs-7 mb-1"><span
                                            class="text-muted">Subtotal</span><strong id="cart-subtotal">Rp 0</strong>
                                    </div>
                                    <div class="d-flex justify-content-between fs-7"><span
                                            class="text-muted">Diskon</span><strong id="cart-discount">Rp 0</strong></div>
                                </div>
                                <div class="col-sm-7 text-sm-end">
                                    <div class="text-muted fs-8">GRAND TOTAL</div>
                                    <div id="cart-grand-total" class="pos-grand-total fw-bold text-primary">Rp 0</div>
                                </div>
                            </div>
                            <div class="d-grid gap-3 mt-4 pos-main-actions">
                                <button type="button" id="hold-cart" class="btn btn-light-warning btn-lg"><i
                                        class="ki-outline ki-time fs-4 me-2"></i>HOLD <kbd>F6</kbd></button>
                                <button type="button" id="open-payment" class="btn btn-primary btn-lg"><i
                                        class="ki-outline ki-wallet fs-3 me-2"></i>BAYAR <kbd>F8</kbd></button>
                            </div>
                        </footer>
                    </div>
                </section>
            </div>
        </div>

        <div class="modal fade" id="payment-modal" tabindex="-1" aria-labelledby="payment-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-sm-down">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h2 id="payment-title" class="modal-title">Pembayaran</h2>
                            <div class="text-muted fs-7">Periksa ringkasan sebelum menyelesaikan transaksi.</div>
                        </div><button type="button" class="btn btn-sm btn-icon btn-light" data-bs-dismiss="modal"
                            aria-label="Tutup"><i class="ki-outline ki-cross fs-3"></i></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center border-bottom pb-5 mb-5">
                            <div class="text-muted fs-7">TOTAL BELANJA</div>
                            <div id="payment-grand-total" class="pos-payment-total fw-bold text-primary">Rp 0</div>
                        </div>
                        <div id="payment-price-warning" class="alert alert-danger d-none"></div>
                        <div class="row g-5">
                            <div class="col-lg-8">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h3 class="fs-5 mb-0">Metode Pembayaran</h3><button type="button" id="add-payment"
                                        class="btn btn-sm btn-light-primary"><i class="ki-outline ki-plus fs-5"></i>
                                        Split</button>
                                </div>
                                <div id="payment-rows" class="d-grid gap-3"></div>
                                <div id="cash-quick-buttons" class="d-flex flex-wrap gap-2 mt-4"></div>
                            </div>
                            <div class="col-lg-4">
                                <div class="bg-light rounded p-5 pos-payment-summary">
                                    <div class="d-flex justify-content-between mb-3"><span>Total Belanja</span><strong
                                            id="payment-summary-total">Rp 0</strong></div>
                                    <div class="d-flex justify-content-between mb-3"><span>Dibayar</span><strong
                                            id="payment-paid">Rp 0</strong></div>
                                    <div class="d-flex justify-content-between mb-2"><span>Kekurangan</span><strong
                                            id="payment-shortage" class="text-danger">Rp 0</strong></div>
                                    <div class="separator my-3"></div>
                                    <div class="text-muted fs-8">KEMBALIAN</div>
                                    <div id="payment-change" class="pos-payment-change fw-bold text-success">Rp 0</div>
                                </div>
                                <div class="mt-4 fs-7">
                                    <div><strong id="payment-item-count">0 item</strong></div>
                                    <div id="payment-customer" class="text-muted">Pelanggan Umum</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light"
                            data-bs-dismiss="modal">Batal</button><button type="button" id="confirm-payment"
                            class="btn btn-primary btn-lg"><i class="ki-outline ki-check fs-4 me-2"></i>SELESAIKAN
                            TRANSAKSI</button></div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="holds-modal" tabindex="-1" aria-labelledby="holds-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="holds-title" class="modal-title">Transaksi Hold</h2><button type="button"
                            class="btn btn-sm btn-icon btn-light" data-bs-dismiss="modal" aria-label="Tutup"><i
                                class="ki-outline ki-cross fs-3"></i></button>
                    </div>
                    <div class="modal-body" id="holds-list"></div>
                    <div class="modal-footer"><a href="{{ route('retail.pos.holds') }}" class="btn btn-light">Lihat
                            Semua</a><button type="button" class="btn btn-primary"
                            data-bs-dismiss="modal">Tutup</button></div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="success-modal" tabindex="-1" aria-labelledby="success-title"
            data-bs-backdrop="static" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content text-center">
                    <div class="modal-body p-8"><i class="ki-outline ki-check-circle text-success fs-3x"></i>
                        <h2 id="success-title" class="mt-4">Transaksi Berhasil</h2>
                        <div id="success-number" class="text-muted fw-semibold"></div>
                        <div class="mt-5 text-muted">Total</div>
                        <div id="success-total" class="fs-2x fw-bold"></div>
                        <div class="mt-3 text-muted">Kembalian</div>
                        <div id="success-change" class="fs-2 fw-bold text-success"></div>
                        <div class="d-grid gap-2.5 mt-7">
                            <button type="button" id="success-print" class="btn btn-success fw-bold py-3">
                                <i class="ki-outline ki-receipt-square fs-4 me-1"></i>Cetak Struk (Thermal 80mm)
                            </button>
                            <button type="button" id="success-print-dotmatrix" class="btn btn-light-primary fw-bold py-3">
                                <i class="ki-outline ki-document fs-4 me-1"></i>Cetak Faktur (Epson LX-310)
                            </button>
                            <button type="button" id="new-transaction" class="btn btn-light-success fw-bold py-3">
                                <i class="ki-outline ki-plus fs-4 me-1"></i>Transaksi Baru (Tanpa Struk)
                            </button>
                            <a id="success-detail" class="btn btn-light">Lihat Detail</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal Tambah Pelanggan Cepat --}}
        <div class="modal fade" id="modal-quick-customer" tabindex="-1" aria-labelledby="modal-quick-customer-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form id="form-quick-customer" method="POST" data-no-spa novalidate>
                        <div class="modal-header">
                            <h3 class="modal-title fs-5 fw-bold" id="modal-quick-customer-title">
                                <i class="ki-outline ki-user fs-3 text-primary me-2"></i>Tambah Pelanggan Cepat
                            </h3>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-6">
                            <div class="alert alert-light-primary border-primary border border-dashed p-3 mb-4 fs-8 text-gray-700">
                                Pelanggan yang ditambahkan akan otomatis berstatus <strong>Aktif</strong> dan langsung terpilih pada transaksi kasir saat ini.
                            </div>
                            <div class="mb-4">
                                <label class="form-label required fs-7 fw-bold">Tipe Pelanggan</label>
                                <select name="type" id="qc-type" class="form-select form-select-solid form-select-sm" required>
                                    <option value="general">Umum / Retail</option>
                                    <option value="retail_credit">Retail Kredit</option>
                                    <option value="b2b">Mitra B2B</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="form-label required fs-7 fw-bold">Nama Usaha / Nama Pelanggan</label>
                                <input type="text" name="name" id="qc-name" class="form-control form-control-solid form-control-sm" placeholder="Contoh: Toko Berkah / Budi Santoso" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fs-7 fw-semibold">Nomor WhatsApp</label>
                                <input type="text" name="whatsapp_number" id="qc-whatsapp" class="form-control form-control-solid form-control-sm" placeholder="08xxxxxxxxxx">
                            </div>
                            <div class="row g-3 mb-4">
                                <div class="col-6">
                                    <label class="form-label fs-7 fw-semibold">Minimum Order (Rp)</label>
                                    <input type="number" step="1000" min="0" name="minimum_order" id="qc-min-order" class="form-control form-control-solid form-control-sm" value="0">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fs-7 fw-semibold">Tempo Piutang (Hari)</label>
                                    <input type="number" min="0" max="365" name="payment_term_days" id="qc-payment-terms" class="form-control form-control-solid form-control-sm" value="0">
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label fs-7 fw-semibold">Maksimal Kredit / Limit (Rp)</label>
                                <input type="number" step="10000" min="0" name="credit_limit" id="qc-credit-limit" class="form-control form-control-solid form-control-sm" value="0">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-sm btn-primary fw-bold" id="btn-submit-quick-customer">
                                <i class="ki-outline ki-check fs-6 me-1"></i> Simpan &amp; Pilih Pelanggan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Modal Retur Penjualan Produk --}}
        <div class="modal fade" id="modal-pos-return" tabindex="-1" aria-labelledby="modal-pos-return-title" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-lg-down">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <div class="d-flex align-items-center gap-3">
                            <div class="symbol symbol-40px symbol-circle bg-light-danger text-danger flex-shrink-0">
                                <span class="symbol-label"><i class="ki-outline ki-arrows-circle fs-2 text-danger"></i></span>
                            </div>
                            <div>
                                <h3 class="modal-title fs-4 fw-bolder text-gray-900" id="modal-pos-return-title">Retur Penjualan Produk</h3>
                                <div class="text-muted fs-8">Proses pengembalian barang pelanggan, pembaruan stok, dan cetak ulang struk</div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-icon btn-light" data-bs-dismiss="modal" aria-label="Tutup"><i class="ki-outline ki-cross fs-2"></i></button>
                    </div>

                    <div class="modal-body p-6">
                        {{-- Step 1: Search Form --}}
                        <div id="return-step-search" class="mb-5">
                            <label class="form-label fw-bold fs-7 mb-2">Cari No. Struk / Faktur Transaksi Asal</label>
                            <div class="input-group input-group-solid mb-3">
                                <span class="input-group-text"><i class="ki-outline ki-magnifier fs-3"></i></span>
                                <input type="text" id="return-search-input" class="form-control form-control-solid" placeholder="Scan barcode struk atau ketik no. faktur (cth: POS/TKO-PUSAT/2026/00001)..." autocomplete="off">
                                <button type="button" id="btn-search-return" class="btn btn-primary fw-bold">
                                    <span class="indicator-label"><i class="ki-outline ki-search fs-4 me-1"></i>Cari Struk</span>
                                    <span class="indicator-progress d-none"><span class="spinner-border spinner-border-sm align-middle me-1"></span>Mencari...</span>
                                </button>
                            </div>
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="text-muted fs-8"><i class="ki-outline ki-information fs-7 me-1"></i>Tekan Enter untuk mencari langsung atau pilih dari 15 transaksi terakhir di bawah:</div>
                                <button type="button" id="btn-reload-recent-sales" class="btn btn-link btn-color-muted btn-active-color-primary p-0 fs-8"><i class="ki-outline ki-arrows-circle fs-8 me-1"></i>Muat Ulang Transaksi</button>
                            </div>
                            {{-- Recent sales cards/table --}}
                            <div id="return-recent-container" class="mt-3 table-responsive border rounded border-gray-200" style="max-height: 250px; overflow-y: auto;">
                                <div class="text-center py-5 text-muted fs-8"><span class="spinner-border spinner-border-sm me-2"></span>Memuat transaksi cabang...</div>
                            </div>
                        </div>

                        {{-- Step 2: Return Items Entry (Hidden until sale is selected) --}}
                        <div id="return-step-items" class="d-none">
                            <div class="p-4 rounded bg-light-subtle border border-gray-200 mb-4">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                    <div>
                                        <span class="text-muted fs-8 fw-semibold text-uppercase d-block">TRANSAKSI DIPILIH</span>
                                        <span class="fs-4 fw-bolder text-gray-900 font-monospace" id="return-sale-number">-</span>
                                        <span class="badge badge-light-primary ms-2 fs-8" id="return-sale-status">Selesai</span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-4 text-gray-700 fs-7">
                                        <div><span class="text-muted fs-8 d-block">TANGGAL:</span><strong id="return-sale-date">-</strong></div>
                                        <div><span class="text-muted fs-8 d-block">PELANGGAN:</span><strong id="return-sale-customer">-</strong></div>
                                        <div><span class="text-muted fs-8 d-block">KASIR:</span><strong id="return-sale-cashier">-</strong></div>
                                        <div><span class="text-muted fs-8 d-block">TOTAL TRANSAKSI:</span><strong class="text-primary" id="return-sale-total">Rp 0</strong></div>
                                    </div>
                                    <div>
                                        <button type="button" id="btn-change-sale" class="btn btn-sm btn-light-secondary"><i class="ki-outline ki-arrow-left fs-6 me-1"></i>Ganti Struk</button>
                                    </div>
                                </div>
                            </div>

                            <form id="form-submit-return" method="POST" data-no-spa onsubmit="return false;">
                                <input type="hidden" id="return-input-sale-id" name="pos_sale_id" value="">
                                
                                <div class="table-responsive border rounded border-gray-200 mb-4">
                                    <table class="table table-row-bordered table-row-gray-200 align-middle gs-4 gy-3 mb-0" id="return-items-table">
                                        <thead class="bg-light text-muted fw-bold fs-8 text-uppercase">
                                            <tr>
                                                <th class="ps-4" style="min-width: 180px;">Produk</th>
                                                <th class="text-center" style="width: 110px;">Qty Beli</th>
                                                <th class="text-center" style="width: 120px;">Sisa Bisa Retur</th>
                                                <th class="text-center" style="width: 130px;">Qty Diretur</th>
                                                <th class="text-center" style="width: 140px;">Kondisi Barang</th>
                                                <th class="text-end pe-4" style="width: 160px;">Subtotal Refund</th>
                                            </tr>
                                        </thead>
                                        <tbody class="fw-semibold text-gray-800 fs-7" id="return-items-tbody">
                                            {{-- Dynamic rows --}}
                                        </tbody>
                                    </table>
                                </div>

                                <div class="row g-4 mb-4">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold fs-7 required">Metode Pengembalian Dana</label>
                                        <select class="form-select form-select-solid" id="return-refund-method" name="refund_method" required>
                                            <option value="cash" selected>Kas / Tunai (Potong Laci Shift Aktif)</option>
                                            <option value="bank_transfer">Transfer Bank</option>
                                            <option value="qris">QRIS Refund</option>
                                            <option value="manual">Manual / Lainnya</option>
                                        </select>
                                        <div class="text-muted fs-8 mt-1">Uang tunai akan mengurangi Expected Cash shift saat closing.</div>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label fw-bold fs-7 required">Alasan Retur / Keterangan</label>
                                        <input type="text" class="form-control form-control-solid" id="return-reason" name="reason" placeholder="Contoh: Barang rusak saat dibuka / kemasan bocor / salah varian..." required>
                                    </div>
                                </div>

                                <div class="p-4 rounded bg-light-danger border border-danger-subtle d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                                    <div>
                                        <div class="fw-bold fs-6 text-gray-900">Total Dana yang Dikembalikan ke Pelanggan:</div>
                                        <div class="text-muted fs-8">Stok produk yang diretur dengan kondisi baik akan otomatis dikembalikan ke inventaris cabang.</div>
                                    </div>
                                    <div class="text-end">
                                        <span class="fs-2hx fw-bolder text-danger" id="return-total-refund-display">Rp 0</span>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" id="btn-submit-return-confirm" class="btn btn-danger fw-bold" disabled>
                                        <span class="indicator-label"><i class="ki-outline ki-check-circle fs-4 me-1"></i>Konfirmasi &amp; Proses Retur</span>
                                        <span class="indicator-progress d-none"><span class="spinner-border spinner-border-sm align-middle me-1"></span>Memproses Retur...</span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        {{-- Step 3: Success Screen with Reprint options --}}
                        <div id="return-step-success" class="d-none text-center py-6">
                            <div class="mb-4">
                                <i class="ki-outline ki-check-circle text-success fs-4tx"></i>
                            </div>
                            <h3 class="fs-2 fw-bolder text-gray-900 mb-1">Retur Barang Berhasil Diproses!</h3>
                            <div class="text-muted fs-7 mb-4">
                                Nomor Bukti Retur: <span class="fw-bold text-gray-900 font-monospace" id="return-success-number">-</span>
                            </div>

                            <div class="row justify-content-center mb-6">
                                <div class="col-md-8">
                                    <div class="p-4 rounded bg-light border border-gray-200 text-start">
                                        <div class="d-flex justify-content-between py-1 border-bottom border-gray-200">
                                            <span class="text-muted">Total Dana Dikembalikan:</span>
                                            <strong class="text-danger" id="return-success-amount">Rp 0</strong>
                                        </div>
                                        <div class="d-flex justify-content-between py-1 border-bottom border-gray-200">
                                            <span class="text-muted">Metode Refund:</span>
                                            <strong id="return-success-method">Tunai</strong>
                                        </div>
                                        <div class="d-flex justify-content-between py-1">
                                            <span class="text-muted">Status Stok:</span>
                                            <span class="badge badge-light-success">Stok Berhasil Dikembalikan</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-center gap-3">
                                <button type="button" id="btn-print-reprinted-receipt" class="btn btn-primary fw-bold py-3 px-6">
                                    <i class="ki-outline ki-printer fs-3 me-2"></i>Cetak Ulang Struk (Revisi)
                                </button>
                                <button type="button" id="btn-print-return-receipt" class="btn btn-light-danger fw-bold py-3 px-6">
                                    <i class="ki-outline ki-receipt fs-3 me-2"></i>Cetak Nota Retur Resmi
                                </button>
                                <button type="button" class="btn btn-light py-3 px-5" data-bs-dismiss="modal">
                                    Selesai / Lanjut POS
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    @endif
@endsection

@push('styles')
    <style>
        html.pos-page-root,
        body.pos-page {
            width: 100%;
            height: 100dvh;
            overflow: hidden;
            overscroll-behavior: none;
        }

        body.pos-page #kt_app_root,
        body.pos-page #kt_app_page,
        body.pos-page #kt_app_wrapper {
            max-width: 100%;
            min-height: 0;
            overflow: hidden;
        }

        body.pos-page #kt_app_toolbar,
        body.pos-page #kt_app_footer {
            display: none !important;
        }

        body.pos-page #kt_app_main {
            height: calc(100dvh - var(--gt-header-height, 44px));
            max-height: calc(100dvh - var(--gt-header-height, 44px));
            margin-top: 0 !important;
            overflow: hidden;
        }

        body.pos-page #kt_app_main>.d-flex,
        body.pos-page #kt_app_main_content {
            height: 100%;
            min-height: 0;
            margin-top: 0 !important;
            overflow: hidden;
        }

        body.pos-page #kt_app_content {
            height: 100%;
            min-height: 0;
            padding: 0 !important;
            overflow: hidden;
        }

        body.pos-page #kt_app_content_container {
            height: 100%;
            max-width: 100%;
            min-height: 0;
            margin-top: 0 !important;
            padding: 10px 16px !important;
            overflow: hidden;
        }

        .pos-shell {
            display: flex;
            flex-direction: column;
            width: 100%;
            height: 100%;
            min-width: 0;
            min-height: 0;
            overflow: hidden;
        }

        .pos-context-bar {
            flex: 0 0 auto;
        }

        .pos-workspace-row {
            display: flex;
            flex-direction: row;
            gap: 16px;
            flex: 1 1 auto;
            min-width: 0;
            min-height: 0;
            overflow: hidden;
            width: 100%;
            margin: 0 !important;
        }

        .pos-section-products {
            flex: 0 0 calc(50% - 8px);
            width: calc(50% - 8px);
            max-width: calc(50% - 8px);
            height: 100%;
            min-width: 0;
            min-height: 0;
        }

        .pos-section-cart {
            flex: 0 0 calc(50% - 8px);
            width: calc(50% - 8px);
            max-width: calc(50% - 8px);
            height: 100%;
            min-width: 0;
            min-height: 0;
        }

        .pos-panel {
            height: 100%;
            min-width: 0;
            min-height: 0;
            overflow: hidden;
        }

        .pos-product-results {
            min-height: 0;
            overflow-x: hidden;
            overflow-y: auto;
        }

        .pos-product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(155px, 1fr));
            gap: 8px;
        }

        .pos-product-card {
            border: 1px solid var(--bs-gray-200);
            border-radius: 8px;
            padding: 7px;
            min-width: 0;
            background: var(--bs-body-bg);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
            user-select: none;
            display: flex;
            flex-direction: column;
            position: relative;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .pos-product-card:hover {
            border-color: var(--bs-primary);
            box-shadow: 0 4px 14px rgba(62, 151, 255, 0.12), 0 1px 3px rgba(0, 0, 0, 0.04);
            transform: translateY(-2px);
        }

        .pos-product-card.is-out-of-stock {
            opacity: 0.7;
            background: rgba(var(--bs-light-rgb), 0.5);
            cursor: not-allowed;
        }

        .pos-product-card.is-out-of-stock:hover {
            border-color: var(--bs-gray-300);
            box-shadow: none;
            transform: none;
        }

        .pos-product-thumb {
            width: 100%;
            height: 85px;
            background: #f8fafc;
            border: 1px solid var(--bs-gray-200);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            margin-bottom: 5px;
        }

        [data-bs-theme="dark"] .pos-product-thumb {
            background: #1e1e2d;
            border-color: #2b2b40;
        }

        .pos-product-thumb img.pos-product-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border: none;
            background: transparent;
            padding: 4px;
            transition: transform 0.25s ease;
        }

        .pos-product-card:hover:not(.is-out-of-stock) .pos-product-thumb img.pos-product-image {
            transform: scale(1.05);
        }

        .pos-product-thumb .pos-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f1f3f8 0%, #e8ecf4 100%);
            color: var(--bs-gray-400);
        }

        [data-bs-theme="dark"] .pos-product-thumb .pos-image-placeholder {
            background: linear-gradient(135deg, #212529 0%, #1a1e21 100%);
            color: #495057;
        }

        .pos-product-name {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.25;
            min-height: 2.5em;
            overflow-wrap: anywhere;
            font-size: 12px;
            font-weight: 700;
            color: var(--bs-gray-900);
            transition: color 0.15s ease;
            margin-bottom: 2px;
        }

        .pos-product-card:hover .pos-product-name {
            color: var(--bs-primary);
        }

        .pos-product-meta {
            min-width: 0;
            overflow: hidden;
        }

        .pos-cart-scroll {
            min-height: 0;
            overflow-x: hidden;
            overflow-y: auto;
        }

        .pos-cart-item {
            border: 1px solid var(--bs-gray-200);
            border-left: 4px solid var(--bs-gray-300);
            border-radius: 8px;
            padding: 12px 14px;
            min-width: 0;
            background: var(--bs-body-bg);
            box-shadow: 0 1px 3px rgba(0, 0, 0, .03);
            transition: all .2s ease;
        }

        .pos-cart-item:hover {
            box-shadow: 0 3px 10px rgba(0, 0, 0, .06);
        }

        .pos-cart-item.pos-price-safe {
            border-left-color: var(--bs-success);
        }

        .pos-cart-item.pos-price-near {
            border-left-color: var(--bs-warning);
        }

        .pos-cart-item.pos-price-below,
        .pos-cart-item.pos-price-above,
        .pos-cart-item.pos-price-approval {
            border-left-color: var(--bs-danger);
            background: rgba(var(--bs-danger-rgb), 0.02);
        }

        .pos-cart-main {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-width: 0;
            padding-bottom: 10px;
            border-bottom: 1px dashed var(--bs-gray-200);
        }

        .pos-cart-product {
            flex: 1 1 auto;
            min-width: 0;
        }

        .pos-cart-product .cart-unit {
            width: 100px;
            max-width: 100%;
            height: 28px;
            padding: 2px 20px 2px 8px;
            font-size: 11px;
            font-weight: 600;
        }

        .pos-cart-image {
            width: 48px;
            height: 48px;
            object-fit: contain;
            background: #fff;
            border: 1px solid var(--bs-gray-200);
            border-radius: 6px;
            flex-shrink: 0;
            padding: 2px;
        }

        .pos-cart-image.pos-image-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bs-light);
            color: var(--bs-gray-400);
        }

        .pos-cart-action {
            flex: 0 0 auto;
        }

        .pos-cart-details {
            display: grid;
            grid-template-columns: minmax(105px, 1.1fr) minmax(130px, 1.4fr) minmax(85px, 0.9fr) minmax(110px, 1.1fr);
            gap: 8px 12px;
            align-items: end;
            margin-top: 10px;
            min-width: 0;
        }

        .pos-cart-qty {
            min-width: 0;
        }

        .pos-cart-price {
            min-width: 0;
        }

        .pos-cart-discount {
            min-width: 0;
        }

        .pos-cart-subtotal {
            min-width: 0;
            text-align: right;
            padding-bottom: 2px;
        }

        .pos-cart-guidance {
            grid-column: 1 / -1;
            min-width: 0;
            margin-top: 2px;
        }

        .pos-cart-guidance-pill {
            background: var(--bs-light);
            border: 1px solid var(--bs-gray-200);
            border-radius: 6px;
            padding: 6px 10px;
        }

        .pos-cart-product .cart-batch-select {
            width: auto;
            max-width: 190px;
            height: 28px;
            padding: 2px 22px 2px 8px;
            font-size: 11px;
            font-weight: 600;
            background-color: var(--bs-light);
            border-color: var(--bs-gray-300);
            border-radius: 6px;
        }

        .pos-cart-product .cart-batch-select:focus {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.15rem rgba(var(--bs-primary-rgb), 0.15);
        }

        .pos-cart-label {
            display: block;
            color: var(--bs-gray-600);
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .pos-qty-control {
            display: flex;
            align-items: center;
            border: 1px solid var(--bs-gray-300);
            border-radius: 6px;
            overflow: hidden;
            background: var(--bs-body-bg);
            transition: border-color .15s ease-in-out;
            height: 36px;
        }

        .pos-qty-control:focus-within {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.15rem rgba(var(--bs-primary-rgb), 0.15);
        }

        .pos-qty-control .btn {
            border: none;
            border-radius: 0;
            width: 32px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            background: var(--bs-light);
            color: var(--bs-gray-700);
            flex-shrink: 0;
            transition: background-color .15s;
        }

        .pos-qty-control .btn:hover {
            background: var(--bs-gray-300);
            color: var(--bs-primary);
        }

        .pos-qty-control input {
            border: none;
            border-left: 1px solid var(--bs-gray-200);
            border-right: 1px solid var(--bs-gray-200);
            border-radius: 0;
            height: 36px;
            padding: 2px 4px;
            font-size: 14px;
            font-weight: 700;
            color: var(--bs-gray-900);
            text-align: center;
            width: 100%;
            min-width: 0;
            background: transparent;
        }

        .pos-qty-control input:focus {
            outline: none;
            box-shadow: none;
        }

        .pos-cart-price-editor {
            position: relative;
        }

        .pos-cart-price-editor>i {
            position: absolute;
            left: 10px;
            top: 50%;
            z-index: 2;
            color: var(--bs-primary);
            transform: translateY(-50%);
            pointer-events: none;
            font-size: 14px !important;
        }

        .pos-price-input {
            width: 100%;
            height: 36px;
            padding-left: 28px;
            font-size: 14px;
            font-weight: 700;
            color: var(--bs-gray-900);
            border: 1px solid var(--bs-gray-300);
            border-radius: 6px;
        }

        .pos-price-input:focus {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.15rem rgba(var(--bs-primary-rgb), 0.15);
        }

        .pos-cart-discount .input-group {
            height: 36px;
        }

        .pos-cart-discount input {
            height: 36px;
            font-size: 13px;
            font-weight: 600;
            border-color: var(--bs-gray-300);
        }

        .pos-cart-discount .input-group-text {
            height: 36px;
            background: var(--bs-light);
            border-color: var(--bs-gray-300);
            color: var(--bs-gray-600);
            font-size: 11px;
            font-weight: 600;
        }

        .pos-cart-subtotal strong {
            color: var(--bs-primary);
            font-size: 16px;
            font-weight: 800;
            display: block;
            line-height: 1.2;
        }

        .pos-customer-wrap {
            width: 220px;
            max-width: 100%;
        }

        .pos-grand-total {
            font-size: 26px;
            line-height: 1.1;
            letter-spacing: 0;
            overflow-wrap: anywhere;
        }

        .pos-main-actions {
            grid-template-columns: 1fr 1.6fr;
            gap: 10px;
        }

        .pos-main-actions .btn {
            padding: 10px 12px;
            font-size: 14px;
        }

        .pos-category {
            white-space: nowrap;
        }



        .pos-payment-total {
            font-size: 42px;
            line-height: 1.1;
            letter-spacing: 0;
        }

        .pos-payment-change {
            font-size: 34px;
            line-height: 1.1;
            letter-spacing: 0;
            overflow-wrap: anywhere;
        }

        .pos-payment-row {
            background-color: #ffffff;
            transition: all 0.2s ease;
        }

        .pos-payment-due-date {
            background-color: #fff8dd;
            border-radius: 0.475rem;
            padding: 0.75rem 1rem;
            border: 1px dashed #f1bc00;
        }

        .payment-amount {
            font-size: 18px;
            font-weight: 700;
        }

        .quick-cash {
            min-height: 44px;
            padding-inline: 16px;
            font-weight: 600;
        }

        kbd {
            font-size: 10px;
            font-weight: 600;
            color: var(--bs-gray-700);
            background: var(--bs-gray-200);
            margin-left: 4px;
        }

        @media (min-width: 1600px) {
            .pos-cart-item {
                padding: 16px;
            }
        }

        @media (max-width: 1399.98px) {
            .pos-context-bar {
                padding: 10px 12px !important;
            }

            .pos-context-bar>div:first-child {
                gap: 14px !important;
            }

            .pos-product-image {
                width: 64px;
                height: 64px;
            }

            .pos-product-results {
                padding: 10px !important;
            }

            .pos-product-grid {
                gap: 8px;
            }
        }

        @media (max-width: 991.98px) {
            .pos-workspace-row {
                flex-direction: column;
                overflow-y: auto;
                gap: 16px;
            }

            .pos-section-products,
            .pos-section-cart {
                flex: 1 1 auto;
                width: 100%;
                max-width: 100%;
                height: auto;
                min-height: 520px;
            }

            .pos-product-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .pos-product-grid {
                grid-template-columns: 1fr;
            }

            .pos-main-actions {
                grid-template-columns: 1fr;
            }

            .pos-grand-total {
                font-size: 30px;
            }

            .pos-customer-wrap {
                width: 100%;
            }

            .pos-cart-details {
                grid-template-columns: 1fr 1fr;
            }

            .pos-cart-qty {
                grid-column: 1 / 2;
            }

            .pos-cart-price {
                grid-column: 2 / 3;
            }

            .pos-cart-discount {
                grid-column: 1 / 2;
            }

            .pos-cart-subtotal {
                grid-column: 2 / 3;
            }

            .pos-cart-guidance {
                grid-column: 1 / -1;
            }

            .pos-payment-total {
                font-size: 34px;
            }
        }

        @media (max-width: 575.98px) {
            .pos-cart-item {
                padding: 12px;
            }

            .pos-cart-image {
                width: 58px;
                height: 58px;
            }

            .pos-cart-details {
                grid-template-columns: 1fr;
                grid-template-areas: "price" "guidance" "discount" "subtotal";
            }

            .pos-cart-subtotal {
                padding-left: 0;
                border-left: 0;
                text-align: left;
            }
        }
    </style>
@endpush

@if ($activeShift && $branch)
    @push('scripts')
        <script>
            const initializeScannerFirstPos = () => {
                const workspace = document.getElementById('pos-workspace');
                if (!workspace) return;

                const endpoints = {
                    products: @json(route('retail.pos.products')),
                    quote: @json(route('retail.pos.quote')),
                    checkout: @json(route('retail.pos.store')),
                    hold: @json(route('retail.pos.holds.store')),
                    holds: @json(route('retail.pos.holds.data')),
                    salesLookup: @json(route('retail.pos.sales-lookup')),
                    processReturn: @json(route('retail.pos.returns.store')),
                };
                const branchId = @json($branch->id);
                const paymentMethods = @json($paymentMethods);
                const resumeCart = @json($resumeCart);
                let emergencyPurchaseId = @json($emergencyPurchase?->id);
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                const scanner = document.getElementById('pos-scanner');
                const productResults = document.getElementById('product-results');
                const cartBody = document.getElementById('cart-items');
                const customerSelect = document.getElementById('customer_id');
                const paymentModal = new bootstrap.Modal(document.getElementById('payment-modal'));
                const holdsModal = new bootstrap.Modal(document.getElementById('holds-modal'));
                const successModal = new bootstrap.Modal(document.getElementById('success-modal'));
                const returnModalEl = document.getElementById('modal-pos-return');
                const returnModal = returnModalEl ? new bootstrap.Modal(returnModalEl) : null;
                if (window.jQuery && typeof window.jQuery.fn.select2 === 'function' && !window.jQuery(customerSelect)
                    .hasClass('select2-hidden-accessible')) {
                    window.jQuery(customerSelect).select2({
                        placeholder: 'Pelanggan Umum',
                        allowClear: true,
                        width: '100%'
                    });
                }
                let cart = [];
                let payments = [];
                let productPage = 1;
                let hasMoreProducts = false;
                let selectedCategory = '';
                let searchTimer = null;
                let requestSequence = 0;
                let idempotencyKey = makeUuid();
                let receiptPrintUrl = null;
                const printerSettings = @json(\App\Models\SystemSetting::getPrinterSettings());

                const money = value => 'Rp ' + Number(value || 0).toLocaleString('id-ID', {
                    maximumFractionDigits: 0
                });
                const qty = value => Number(value || 0).toLocaleString('id-ID', {
                    maximumFractionDigits: 4
                });
                const qtyInput = value => {
                    const number = Number(value || 0);
                    return Number.isInteger(number) ? String(number) : number.toFixed(4).replace(/0+$/, '').replace(
                        /\.$/, '');
                };
                const rupiahValue = value => Number(String(value ?? '').replace(/[^0-9]/g, '')) || 0;
                const escapeHtml = value => {
                    const node = document.createElement('div');
                    node.textContent = value ?? '';
                    return node.innerHTML;
                };
                const customerName = () => customerSelect.options[customerSelect.selectedIndex]?.text || 'Pelanggan Umum';
                const formatDateIso = date => {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                };
                const todayIsoDate = () => formatDateIso(new Date());
                const addDaysToDate = days => {
                    const d = new Date();
                    d.setDate(d.getDate() + (parseInt(days, 10) || 0));
                    return formatDateIso(d);
                };
                const getSelectedCustomerData = () => {
                    if (!customerSelect || customerSelect.selectedIndex < 0) return null;
                    const option = customerSelect.options[customerSelect.selectedIndex];
                    if (!option || !option.value) return null;
                    return {
                        id: option.value,
                        name: option.text.trim(),
                        paymentTerms: parseInt(option.dataset.paymentTerms || '0', 10),
                        creditLimit: parseFloat(option.dataset.creditLimit || '0'),
                        receivableBalance: parseFloat(option.dataset.receivableBalance || '0'),
                        availableCredit: parseFloat(option.dataset.availableCredit || '0'),
                        minimumOrder: parseFloat(option.dataset.minimumOrder || '0'),
                    };
                };
                const focusScanner = (force = false) => window.setTimeout(() => {
                    const active = document.activeElement;
                    const isEditing = active?.matches?.('input, select, textarea, [contenteditable="true"]');
                    if (!force && (document.querySelector('.modal.show') || isEditing)) return;
                    scanner.focus();
                    scanner.select();
                }, 80);

                function makeUuid() {
                    return window.crypto?.randomUUID?.() || `pos-${Date.now()}-${Math.random().toString(16).slice(2)}`;
                }

                async function api(url, options = {}) {
                    const response = await fetch(url, {
                        ...options,
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(options.headers || {})
                        },
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] ||
                        'Permintaan tidak dapat diproses.');
                    return data;
                }

                function showScannerMessage(message, type = 'muted') {
                    const element = document.getElementById('scanner-message');
                    element.className = `fs-7 mt-2 text-${type}`;
                    element.textContent = message;
                }

                function showToast(message, icon = 'error') {
                    if (!window.Swal) return;
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon,
                        text: message,
                        timer: 1800,
                        showConfirmButton: false,
                        timerProgressBar: true
                    });
                }

                function imageMarkup(url, sizeClass, iconClass) {
                    return url ?
                        `<img src="${escapeHtml(url)}" class="${sizeClass}" alt="" loading="lazy">` :
                        `<div class="${sizeClass} pos-image-placeholder"><i class="ki-outline ki-package ${iconClass}"></i></div>`;
                }

                function bindImageFallback(root, sizeClass, iconClass) {
                    root.querySelectorAll(`img.${sizeClass}`).forEach(image => image.addEventListener('error', () => {
                        const fallback = document.createElement('div');
                        fallback.className = `${sizeClass} pos-image-placeholder`;
                        fallback.innerHTML = `<i class="ki-outline ki-package ${iconClass}"></i>`;
                        image.replaceWith(fallback);
                    }, {
                        once: true
                    }));
                }

                async function loadProducts(reset = true, immediateScan = false) {
                    if (reset) {
                        productPage = 1;
                        productResults.innerHTML =
                            '<div class="text-center text-muted py-10"><span class="spinner-border spinner-border-sm me-2"></span>Mencari produk...</div>';
                    }
                    const params = new URLSearchParams({
                        q: scanner.value.trim(),
                        page: productPage,
                        per_page: 24,
                        in_stock: document.getElementById('stock-filter').checked ? '1' : '0'
                    });
                    if (customerSelect.value) params.set('customer_id', customerSelect.value);
                    if (selectedCategory) params.set('category_id', selectedCategory);
                    if (document.getElementById('brand-filter').value) params.set('brand_id', document.getElementById(
                        'brand-filter').value);
                    const sequence = ++requestSequence;
                    try {
                        const data = await api(`${endpoints.products}?${params}`);
                        if (sequence !== requestSequence) return;
                        if (data.exact_match && data.results.length === 1) {
                            const item = data.results[0];
                            await addToCart(item);
                            scanner.value = '';
                            showScannerMessage(`${item.name} ditambahkan. Periksa kekurangan stok sebelum checkout.`, item.stock_sufficient ? 'success' : 'warning');
                            focusScanner();
                            return;
                        }
                        renderProducts(data.results || [], reset);
                        hasMoreProducts = data.pagination?.more === true;
                        document.getElementById('load-more-products').classList.toggle('d-none', !hasMoreProducts);
                        if (immediateScan && !(data.results || []).length) {
                            showScannerMessage('Produk dengan barcode tersebut tidak ditemukan.', 'danger');
                            showToast('Produk dengan barcode tersebut tidak ditemukan.');
                            focusScanner(true);
                        }
                    } catch (error) {
                        if (sequence !== requestSequence) return;
                        productResults.innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`;
                        showScannerMessage(error.message, 'danger');
                    }
                }

                function renderProducts(products, reset) {
                    if (reset) productResults.innerHTML = '';
                    if (!products.length && reset) {
                        productResults.innerHTML =
                            '<div class="text-center text-muted py-10"><i class="ki-outline ki-search fs-2x"></i><div class="mt-3">Produk tidak ditemukan.</div></div>';
                        return;
                    }
                    let grid = productResults.querySelector('.pos-product-grid');
                    if (!grid) {
                        grid = document.createElement('div');
                        grid.className = 'pos-product-grid';
                        productResults.appendChild(grid);
                    }
                    products.forEach(product => {
                        const image = imageMarkup(product.image_url, 'pos-product-image', 'fs-2x');
                        const stockClass = Number(product.stock) <= 0 ? 'danger' : (product.stock_low ? 'warning' : 'success');
                        const displayPrice = (product.pricing && (product.pricing.selected_price || product.pricing.recommended_price)) ? (product.pricing.selected_price || product.pricing.recommended_price) : 0;
                        const isOutOfStock = Number(product.stock) <= 0;
                        const card = document.createElement('article');
                        card.className = `pos-product-card ${isOutOfStock ? 'is-out-of-stock' : ''}`;
                        card.innerHTML = `
                            <div class="pos-product-thumb">
                                ${image}
                                <div class="position-absolute top-0 end-0 m-1">
                                    <span class="badge badge-light-${stockClass} text-${stockClass} fw-bold fs-9 shadow-2xs px-1.5 py-0.5 rounded-pill border border-${stockClass}-subtle">
                                        ${isOutOfStock ? 'Habis' : 'Stok ' + qty(product.stock)}
                                    </span>
                                </div>
                                ${product.unit ? `<div class="position-absolute bottom-0 start-0 m-1"><span class="badge badge-light-dark text-gray-700 fs-9 fw-semibold px-1.5 py-0.5 rounded-pill opacity-90 shadow-2xs">${escapeHtml(product.unit)}</span></div>` : ''}
                                ${Number(product.emergency_stock_base) > 0 ? `<div class="position-absolute top-0 start-0 m-1"><span class="badge badge-light-warning text-warning fs-9 fw-bold px-1.5 py-0.5 rounded-pill shadow-2xs" title="Stok Darurat">Drt ${qty(product.emergency_stock_base)}</span></div>` : ''}
                            </div>
                            <div class="d-flex flex-column flex-grow-1">
                                <div class="pos-product-name" title="${escapeHtml(product.name)}">${escapeHtml(product.name)}</div>
                                <div class="d-flex align-items-center justify-content-between text-muted fs-9 mb-1">
                                    <span class="font-monospace text-truncate pe-1 text-gray-500" title="${escapeHtml(product.sku)}">${escapeHtml(product.sku)}</span>
                                    ${product.category ? `<span class="badge badge-secondary fs-9 text-truncate max-w-80px py-0 px-1">${escapeHtml(product.category)}</span>` : ''}
                                </div>
                                <div class="mt-auto pt-1.5 border-top border-gray-200">
                                    <div class="d-flex align-items-baseline justify-content-between mb-1">
                                        <span class="fs-6 fw-bolder text-primary text-nowrap">${money(displayPrice)}</span>
                                    </div>
                                    <button type="button" class="btn btn-sm ${isOutOfStock ? 'btn-light-secondary text-muted' : 'btn-light-primary text-primary'} w-100 fw-bold py-1 px-2 fs-8 mt-1 add-product" ${isOutOfStock ? 'disabled' : ''}>
                                        <i class="ki-outline ${isOutOfStock ? 'ki-cross-circle' : 'ki-plus'} fs-7 me-1"></i> ${isOutOfStock ? 'Habis' : 'Tambah'}
                                    </button>
                                </div>
                            </div>`;
                        bindImageFallback(card, 'pos-product-image', 'fs-2x');
                        card.querySelector('.add-product').addEventListener('click', async (e) => {
                            e.stopPropagation();
                            if (isOutOfStock) return;
                            await addToCart(product);
                            focusScanner();
                        });
                        card.addEventListener('click', async () => {
                            if (isOutOfStock) return;
                            await addToCart(product);
                            focusScanner();
                        });
                        grid.appendChild(card);
                    });
                }

                async function addToCart(product) {
                    const existing = cart.find(item => item.product_id === product.product_id && item.unit_id ===
                        product.unit_id && Number(item.selected_price) === Number(product.pricing.selected_price) &&
                        Number(item.discount_percent || 0) === 0);
                    if (existing) {
                        existing.quantity = Number(existing.quantity) + 1;
                        await requote(existing);
                    } else {
                        cart.push({
                            ...product,
                            quantity: 1,
                            selected_price: product.pricing.selected_price,
                            discount_percent: 0,
                            manual_price: false,
                            line_total: product.pricing.discounted_price,
                            quote_version: 0
                        });
                    }
                    renderCart();
                }

                async function requote(item) {
                    const version = ++item.quote_version;
                    const manualBatchIds = item.batch_selection_mode === 'manual'
                        ? (item.selected_stock_batch_ids || []).map(Number)
                        : [];
                    item.loading = true;
                    renderCart();
                    try {
                        const data = await api(endpoints.quote, {
                            method: 'POST',
                            body: JSON.stringify({
                                product_id: item.product_id,
                                customer_id: customerSelect.value || null,
                                unit_id: item.unit_id,
                                quantity: item.quantity,
                                selected_price: item.manual_price ? item.selected_price : null,
                                discount_percent: item.discount_percent || 0
                            })
                        });
                        if (version !== item.quote_version) return;
                        const quote = data.item;
                        Object.assign(item, quote, {
                            selected_price: quote.pricing.selected_price,
                            line_total: Number(quote.quantity) * Number(quote.pricing.discounted_price),
                            loading: false,
                            quote_version: version
                        });
                        if (manualBatchIds.length) {
                            const availableIds = new Set((quote.stock_batches || []).map(batch => Number(batch.id)));
                            item.selected_stock_batch_ids = manualBatchIds.filter(id => availableIds.has(id));
                            item.batch_selection_mode = item.selected_stock_batch_ids.length ? 'manual' : 'auto';
                        }
                    } catch (error) {
                        if (version !== item.quote_version) return;
                        item.loading = false;
                        item.error = error.message;
                    }
                    renderCart();
                }

                function renderCart() {
                    const empty = cart.length === 0;
                    document.getElementById('cart-empty').classList.toggle('d-none', !empty);
                    document.getElementById('cart-table-wrap').classList.toggle('d-none', empty);
                    cartBody.innerHTML = '';
                    cart.forEach((item, index) => {
                        const image = imageMarkup(item.image_url, 'pos-cart-image', 'fs-2');
                        const pricing = item.pricing || {};
                        const statusClass = pricing.status || (pricing.approval_required ? 'approval' : 'safe');
                        const statusColorMap = {
                            safe: 'success',
                            near: 'warning',
                            below: 'danger',
                            above: 'danger',
                            approval: 'danger'
                        };
                        const statusIconMap = {
                            safe: 'ki-check-circle',
                            near: 'ki-information-5',
                            below: 'ki-cross-circle',
                            above: 'ki-cross-circle',
                            approval: 'ki-shield-cross'
                        };
                        const statusColor = statusColorMap[statusClass] || 'secondary';
                        const statusIcon = statusIconMap[statusClass] || 'ki-information-2';

                        const units = (item.units || [{
                            id: item.unit_id,
                            text: item.unit
                        }]).map(unit =>
                            `<option value="${unit.id}" ${Number(unit.id) === Number(item.unit_id) ? 'selected' : ''}>${escapeHtml(unit.text)}</option>`
                            ).join('');
                        const selectedBatchIds = (item.selected_stock_batch_ids || []).map(Number);
                        const hasMultiBatch = (item.stock_batches || []).length > 1;
                        let batchSelectMarkup = '';
                        if (hasMultiBatch) {
                            const isAuto = !item.batch_selection_mode || item.batch_selection_mode === 'auto' || selectedBatchIds.length === 0;
                            const batchOptions = (item.stock_batches || []).map(batch => {
                                const isSelected = !isAuto && selectedBatchIds.includes(Number(batch.id));
                                return `<option value="${batch.id}" ${isSelected ? 'selected' : ''}>Batch: ${escapeHtml(batch.batch_no)} · Stok ${qty(batch.available_quantity)}</option>`;
                            }).join('');
                            batchSelectMarkup = `
                                <select class="form-select form-select-sm cart-batch-select py-0 px-2 fs-9" data-searchable="false" title="Pilih Batch Fisik">
                                    <option value="auto" ${isAuto ? 'selected' : ''}>Otomatis (FIFO)</option>
                                    ${batchOptions}
                                </select>
                            `;
                        } else if ((item.stock_batches || []).length === 1) {
                            const singleBatch = item.stock_batches[0];
                            batchSelectMarkup = `<span class="badge badge-light-primary fs-9 font-monospace" title="Batch Fisik: ${escapeHtml(singleBatch.batch_no)}">Batch: ${escapeHtml(singleBatch.batch_no)}</span>`;
                        }
                        const row = document.createElement('article');
                        row.className = `pos-cart-item pos-price-${statusClass}`;
                        row.innerHTML = `
                <div class="pos-cart-main">
                    <div class="pos-cart-product d-flex align-items-center gap-3 min-w-0 flex-grow-1">
                        ${image}
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-bold fs-7 text-gray-900 pos-product-name mb-1" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="text-muted fs-9 font-monospace">SKU ${escapeHtml(item.sku)}</span>
                                <select class="form-select form-select-sm cart-unit py-0 px-2 fs-9" data-searchable="false">${units}</select>
                                ${batchSelectMarkup}
                                <span class="badge badge-light fs-9 ${item.stock_sufficient ? 'text-muted' : 'text-danger fw-bold'}">Stok ${qty(item.stock)}</span>
                            </div>
                        </div>
                    </div>
                    <div class="pos-cart-action">
                        <button type="button" class="btn btn-icon btn-sm btn-light-danger cart-remove" title="Hapus" aria-label="Hapus produk">
                            <i class="ki-outline ki-trash fs-4"></i>
                        </button>
                    </div>
                </div>
                <div class="pos-cart-details">
                    <div class="pos-cart-qty">
                        <span class="pos-cart-label">Qty</span>
                        <div class="pos-qty-control">
                            <button type="button" class="btn cart-minus" aria-label="Kurangi qty"><i class="ki-outline ki-minus fs-5"></i></button>
                            <input type="number" min="0" step="any" class="cart-qty" value="${qtyInput(item.quantity)}">
                            <button type="button" class="btn cart-plus" aria-label="Tambah qty"><i class="ki-outline ki-plus fs-5"></i></button>
                        </div>
                    </div>
                    <div class="pos-cart-price">
                        <span class="pos-cart-label">Harga</span>
                        <div class="pos-cart-price-editor">
                            <i class="ki-outline ki-tag"></i>
                            <input type="text" inputmode="numeric" class="form-control pos-price-input cart-price" value="${money(item.selected_price)}" aria-label="Harga jual ${escapeHtml(item.name)}">
                        </div>
                    </div>
                    <div class="pos-cart-discount">
                        <span class="pos-cart-label">Diskon</span>
                        <div class="input-group input-group-sm">
                            <input type="number" min="0" max="100" step="0.01" class="form-control cart-discount" value="${item.discount_percent || 0}">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="pos-cart-subtotal">
                        <span class="pos-cart-label">Subtotal</span>
                        <strong class="text-nowrap">${item.loading ? '<span class="spinner-border spinner-border-sm"></span>' : money(item.line_total)}</strong>
                    </div>
                    <div class="pos-cart-guidance">
                        <div class="pos-cart-guidance-pill d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-2 min-w-0">
                                <i class="ki-outline ${statusIcon} fs-5 text-${statusColor}"></i>
                                <span class="fs-8 fw-semibold text-gray-800"><i class="ki-outline ki-shield-check fs-7 text-primary me-1"></i>Min: <span class="text-gray-900 fw-bold">${money(pricing.minimum_price)}</span></span>
                            </div>
                            <span class="badge badge-light-${statusColor} fw-semibold fs-9">${escapeHtml(pricing.status_label || 'Memuat')}</span>
                        </div>
                        ${pricing.status_message ? `<div class="fs-9 ${statusClass === 'below' || pricing.approval_required ? 'text-danger fw-semibold' : 'text-muted'} mt-1 px-1 d-flex align-items-center gap-1"><i class="ki-outline ${statusClass === 'below' || pricing.approval_required ? 'ki-cross-circle text-danger' : 'ki-information-2 text-muted'} fs-7"></i><span>${escapeHtml(pricing.status_message)}</span></div>` : ''}
                        ${item.error ? `<div class="alert alert-danger py-1 px-2 fs-9 mt-1 mb-0">${escapeHtml(item.error)}</div>` : ''}
                    </div>
                </div>`;
                        bindImageFallback(row, 'pos-cart-image', 'fs-2');
                        row.querySelector('.cart-minus').addEventListener('click', () => updateQuantity(item, Math
                            .max(Number(item.quantity) - 1, 0)));
                        row.querySelector('.cart-plus').addEventListener('click', () => updateQuantity(item, Number(
                            item.quantity) + 1));
                        row.querySelector('.cart-qty').addEventListener('change', event => updateQuantity(item,
                            Number(event.target.value)));
                        row.querySelector('.cart-price').addEventListener('focus', event => event.target.select());
                        row.querySelector('.cart-price').addEventListener('input', event => event.target.value =
                            money(rupiahValue(event.target.value)));
                        row.querySelector('.cart-price').addEventListener('change', event => {
                            item.manual_price = true;
                            item.selected_price = rupiahValue(event.target.value);
                            requote(item);
                        });
                        row.querySelector('.cart-discount').addEventListener('change', event => {
                            item.discount_percent = Number(event.target.value);
                            requote(item);
                        });
                        row.querySelector('.cart-unit').addEventListener('change', event => {
                            item.unit_id = Number(event.target.value);
                            item.manual_price = false;
                            requote(item);
                        });
                        const batchSelect = row.querySelector('.cart-batch-select');
                        if (batchSelect) {
                            batchSelect.addEventListener('change', event => {
                                const val = event.target.value;
                                if (val === 'auto' || !val) {
                                    item.batch_selection_mode = 'auto';
                                    item.selected_stock_batch_ids = [];
                                } else {
                                    item.batch_selection_mode = 'manual';
                                    item.selected_stock_batch_ids = [Number(val)];
                                }
                                requote(item);
                            });
                        }
                        row.querySelector('.cart-remove').addEventListener('click', () => {
                            cart.splice(index, 1);
                            renderCart();
                            focusScanner();
                        });
                        cartBody.appendChild(row);
                    });
                    updateTotals();
                }

                function updateQuantity(item, value) {
                    if (value <= 0) {
                        cart = cart.filter(row => row !== item);
                        renderCart();
                        return;
                    }
                    item.quantity = value;
                    requote(item);
                }

                function totals() {
                    let subtotal = 0,
                        grand = 0,
                        totalQty = 0;
                    cart.forEach(item => {
                        const quantity = Number(item.quantity || 0);
                        subtotal += quantity * Number(item.selected_price || 0);
                        grand += Number(item.line_total || 0);
                        totalQty += quantity;
                    });
                    return {
                        subtotal,
                        discount: Math.max(subtotal - grand, 0),
                        grand,
                        totalQty
                    };
                }

                function blockingIssues() {
                    const issues = [];
                    cart.forEach(item => {
                        if (item.loading || item.error) issues.push(`${item.name}: harga belum siap`);
                        if (!item.stock_sufficient) issues.push(`${item.name}: gabungan stok reguler dan darurat tidak cukup`);
                        if (item.pricing?.approval_required) {
                            if (item.pricing?.approval_reasons?.includes('below_minimum') || (Number(item.pricing?.discounted_price || item.selected_price) < Number(item.pricing?.minimum_price))) {
                                issues.push(`${item.name}: harga (${money(item.pricing?.discounted_price || item.selected_price)}) di bawah harga minimum barang (${money(item.pricing?.minimum_price)})`);
                            } else {
                                issues.push(`${item.name}: membutuhkan approval harga / diskon`);
                            }
                        }
                    });
                    const requiredByProduct = new Map();
                    cart.forEach(item => requiredByProduct.set(item.product_id, (requiredByProduct.get(item.product_id) ||
                        0) + Number(item.quantity) * Number(item.unit_factor || 1)));
                    requiredByProduct.forEach((required, productId) => {
                        const sample = cart.find(item => item.product_id === productId);
                        if (sample && required > Number(sample.sellable_stock_base)) issues.push(
                            `${sample.name}: total qty melampaui stok cabang`);
                    });

                    // Validasi Minimum Order Pelanggan
                    const customer = getSelectedCustomerData();
                    if (customer && customer.minimumOrder > 0) {
                        const grand = totals().grand;
                        if (cart.length > 0 && grand < customer.minimumOrder) {
                            const shortage = customer.minimumOrder - grand;
                            issues.push(`Belum mencapai minimum order pelanggan (${money(customer.minimumOrder)}, kurang ${money(shortage)})`);
                        }
                    }

                    return [...new Set(issues)];
                }

                function updateCustomerInfoStrip() {
                    const customer = getSelectedCustomerData();
                    const strip = document.getElementById('customer-info-strip');
                    if (!strip) return;

                    if (!customer) {
                        strip.classList.add('d-none');
                        return;
                    }

                    strip.classList.remove('d-none');
                    const grand = totals().grand;

                    // Minimum Order Badge
                    const minOrderVal = document.getElementById('customer-min-order-val');
                    if (minOrderVal) {
                        if (customer.minimumOrder > 0) {
                            if (cart.length > 0 && grand >= customer.minimumOrder) {
                                minOrderVal.innerHTML = `<span class="badge badge-light-success fs-8 fw-bold">${money(customer.minimumOrder)} <i class="ki-outline ki-check fs-8 ms-1"></i></span>`;
                            } else {
                                const diff = cart.length > 0 ? (customer.minimumOrder - grand) : customer.minimumOrder;
                                minOrderVal.innerHTML = `<span class="badge badge-light-danger fs-8 fw-bold">${money(customer.minimumOrder)} <span class="fs-9">(Kurang ${money(diff)})</span></span>`;
                            }
                        } else {
                            minOrderVal.innerHTML = `<span class="badge badge-light-secondary text-muted fs-8">Bebas</span>`;
                        }
                    }

                    // Sisa Limit Kredit Badge
                    const creditLimitVal = document.getElementById('customer-credit-limit-val');
                    if (creditLimitVal) {
                        if (customer.creditLimit > 0) {
                            const avail = Math.max(0, customer.creditLimit - customer.receivableBalance);
                            const badgeClass = avail > 0 ? 'badge-light-primary' : 'badge-light-danger';
                            creditLimitVal.innerHTML = `<span class="badge ${badgeClass} fs-8 fw-bold">${money(avail)} <span class="text-muted fw-normal fs-9">/ ${money(customer.creditLimit)}</span></span>`;
                        } else {
                            creditLimitVal.innerHTML = `<span class="badge badge-light-secondary text-muted fs-8">Rp 0 (Non-Kredit)</span>`;
                        }
                    }
                }

                function updateTotals() {
                    const value = totals();
                    document.getElementById('cart-item-label').textContent =
                        `${cart.length} item / ${qty(value.totalQty)} qty`;
                    document.getElementById('cart-subtotal').textContent = money(value.subtotal);
                    document.getElementById('cart-discount').textContent = money(value.discount);
                    document.getElementById('cart-grand-total').textContent = money(value.grand);

                    updateCustomerInfoStrip();

                    const issues = blockingIssues();
                    const alert = document.getElementById('cart-alert');
                    alert.classList.toggle('d-none', !issues.length);
                    alert.textContent = issues.join(' | ');
                    document.getElementById('hold-cart').disabled = cart.length === 0;
                    document.getElementById('open-payment').disabled = cart.length === 0 || issues.length > 0;
                }

                async function requoteAll(forceResetManualPrice = false) {
                    await Promise.all(cart.map(item => {
                        if (forceResetManualPrice) {
                            item.manual_price = false;
                        }
                        return requote(item);
                    }));
                    renderCart();
                }

                function renderPaymentRows() {
                    const container = document.getElementById('payment-rows');
                    container.innerHTML = '';
                    const customer = getSelectedCustomerData();
                    const hasCustomer = Boolean(customer && customer.id);
                    const terms = customer ? customer.paymentTerms : 0;

                    // Filter metode pembayaran: 'credit' (Tempo / Piutang) hanya untuk pelanggan terdaftar
                    const availableMethods = Object.entries(paymentMethods).filter(([value]) => hasCustomer || value !== 'credit');

                    payments.forEach((payment, index) => {
                        // Jika pelanggan umum tapi metode credit terpilih, paksa ubah ke cash
                        if (!hasCustomer && payment.method === 'credit') {
                            payment.method = 'cash';
                        }

                        const isCredit = payment.method === 'credit';
                        if (isCredit && !payment.due_date) {
                            const daysToAdd = terms > 0 ? terms : 30;
                            payment.due_date = addDaysToDate(daysToAdd);
                        }

                        const row = document.createElement('div');
                        row.className = 'pos-payment-row border rounded-3 p-3 mb-3 bg-white shadow-none';
                        const options = availableMethods.map(([value, label]) =>
                            `<option value="${value}" ${payment.method === value ? 'selected' : ''}>${escapeHtml(label)}</option>`
                            ).join('');

                        row.innerHTML = `
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4 col-12">
                                    <label class="form-label fs-8 fw-semibold text-uppercase text-muted mb-1">Metode</label>
                                    <select class="form-select form-select-solid payment-method" data-searchable="false">${options}</select>
                                </div>
                                <div class="col-md-4 col-sm-6 col-12">
                                    <label class="form-label fs-8 fw-semibold text-uppercase text-muted mb-1">Jumlah Dibayarkan</label>
                                    <input type="text" inputmode="numeric" class="form-control form-control-solid fw-bold fs-6 payment-amount" value="${money(payment.amount)}" placeholder="Rp 0" autocomplete="off">
                                </div>
                                <div class="col-md-3 col-sm-4 col-9">
                                    <label class="form-label fs-8 fw-semibold text-uppercase text-muted mb-1">Referensi</label>
                                    <input type="text" class="form-control form-control-solid payment-reference" value="${escapeHtml(payment.reference_no || '')}" placeholder="Opsional">
                                </div>
                                <div class="col-md-1 col-sm-2 col-3 text-end">
                                    <button type="button" class="btn btn-icon btn-light-danger remove-payment w-100" aria-label="Hapus" title="Hapus metode pembayaran">
                                        <i class="ki-outline ki-trash fs-5"></i>
                                    </button>
                                </div>
                            </div>
                            ${isCredit ? `
                            <div class="pos-payment-due-date mt-3 pt-3 border-top">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                    <label class="form-label fs-7 fw-bold text-warning-emphasis mb-0 d-flex align-items-center gap-1">
                                        <i class="ki-outline ki-calendar text-warning fs-5"></i>
                                        Jatuh Tempo Piutang (Tempo)
                                    </label>
                                    <span class="badge badge-light-warning fs-8">
                                        Termin Standar: <strong>${terms > 0 ? terms + ' hari' : 'Default (30 hari)'}</strong>
                                    </span>
                                </div>
                                <div class="row g-2 align-items-center">
                                    <div class="col-sm-5">
                                        <input type="date" class="form-control form-control-sm bg-white payment-due-date" value="${payment.due_date || ''}" min="${todayIsoDate()}">
                                    </div>
                                    <div class="col-sm-7">
                                        <div class="text-muted fs-8">
                                            Bisa diubah jika ada kesepakatan jatuh tempo khusus dengan pelanggan.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            ` : ''}
                        `;

                        row.querySelector('.payment-method').addEventListener('change', event => {
                            payment.method = event.target.value;
                            if (payment.method === 'credit' && !payment.due_date) {
                                const daysToAdd = terms > 0 ? terms : 30;
                                payment.due_date = addDaysToDate(daysToAdd);
                            }
                            renderPaymentRows();
                            renderQuickCash();
                            updatePaymentSummary();
                        });
                        row.querySelector('.payment-amount').addEventListener('focus', event => event.target.select());
                        row.querySelector('.payment-amount').addEventListener('input', event => {
                            payment.amount = rupiahValue(event.target.value);
                            event.target.value = money(payment.amount);
                            updatePaymentSummary();
                        });
                        row.querySelector('.payment-reference').addEventListener('input', event => {
                            payment.reference_no = event.target.value;
                        });

                        if (isCredit) {
                            const dueInput = row.querySelector('.payment-due-date');
                            if (dueInput) {
                                dueInput.addEventListener('change', event => {
                                    payment.due_date = event.target.value;
                                });
                            }
                        }

                        row.querySelector('.remove-payment').addEventListener('click', () => {
                            if (payments.length > 1) payments.splice(index, 1);
                            renderPaymentRows();
                            updatePaymentSummary();
                        });
                        container.appendChild(row);
                    });
                    renderQuickCash();
                }

                function renderQuickCash() {
                    const container = document.getElementById('cash-quick-buttons');
                    if (!container) return;
                    const hasCash = payments.some(payment => payment.method === 'cash');
                    if (!hasCash) {
                        container.innerHTML = '';
                        container.classList.add('d-none');
                        return;
                    }
                    container.classList.remove('d-none');
                    const grand = totals().grand;
                    const values = [{
                            amount: grand,
                            label: 'Uang Pas'
                        }, ...[10000, 20000, 50000, 100000, 200000, 1000000]
                        .filter(amount => amount !== grand)
                        .map(amount => ({
                            amount,
                            label: money(amount)
                        }))
                    ];
                    container.innerHTML = values.map((entry, index) =>
                        `<button type="button" class="btn btn-light-primary quick-cash" data-amount="${entry.amount}" ${index > 0 && entry.amount < grand ? 'disabled' : ''}>${entry.label}</button>`
                        ).join('');
                    container.querySelectorAll('.quick-cash').forEach(button => button.addEventListener('click', () => {
                        let cash = payments.find(row => row.method === 'cash');
                        if (!cash) {
                            cash = {
                                method: 'cash',
                                amount: 0,
                                reference_no: ''
                            };
                            payments.push(cash);
                        }
                        cash.amount = Number(button.dataset.amount);
                        renderPaymentRows();
                        updatePaymentSummary();
                    }));
                }

                function updatePaymentSummary() {
                    const grand = totals().grand;
                    const paid = payments.reduce((sum, payment) => sum + Number(payment.amount || 0), 0);
                    const shortage = Math.max(grand - paid, 0);
                    const change = Math.max(paid - grand, 0);
                    document.getElementById('payment-paid').textContent = money(paid);
                    document.getElementById('payment-summary-total').textContent = money(grand);
                    document.getElementById('payment-shortage').textContent = money(shortage);
                    document.getElementById('payment-change').textContent = money(change);

                    const issues = [...blockingIssues()];
                    const customer = getSelectedCustomerData();

                    // Validasi Pembayaran Tempo vs Sisa Limit Kredit
                    const creditPayments = payments.filter(p => p.method === 'credit');
                    const totalCreditAmount = creditPayments.reduce((sum, p) => sum + Number(p.amount || 0), 0);
                    if (totalCreditAmount > 0) {
                        if (!customer) {
                            issues.push('Metode pembayaran tempo hanya dapat digunakan untuk pelanggan terdaftar.');
                        } else {
                            const availCredit = Math.max(0, customer.creditLimit - customer.receivableBalance);
                            if (customer.creditLimit <= 0) {
                                issues.push('Pelanggan ini tidak memiliki fasilitas plafon kredit tempo.');
                            } else if (totalCreditAmount > availCredit) {
                                issues.push(`Pembayaran tempo (${money(totalCreditAmount)}) melebihi sisa limit kredit pelanggan (${money(availCredit)}).`);
                            }
                        }
                    }

                    const warning = document.getElementById('payment-price-warning');
                    warning.classList.toggle('d-none', !issues.length);
                    warning.textContent = issues.join(' | ');
                    document.getElementById('confirm-payment').disabled = shortage > 0 || issues.length > 0 || payments
                        .some(payment => Number(payment.amount || 0) <= 0);
                }

                function openPayment() {
                    if (!cart.length) return;
                    const issues = blockingIssues();
                    if (issues.length) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Transaksi Belum Memenuhi Syarat',
                            text: issues.join(' | '),
                        });
                        return;
                    }
                    const value = totals();
                    payments = [{
                        method: 'cash',
                        amount: value.grand,
                        reference_no: ''
                    }];
                    document.getElementById('payment-grand-total').textContent = money(value.grand);
                    document.getElementById('payment-item-count').textContent =
                        `${cart.length} item / ${qty(value.totalQty)} qty`;
                    document.getElementById('payment-customer').textContent = customerName();
                    renderPaymentRows();
                    updatePaymentSummary();
                    paymentModal.show();
                }

                function salePayload() {
                    return {
                        branch_id: branchId,
                        customer_id: customerSelect.value || null,
                        idempotency_key: idempotencyKey,
                        emergency_purchase_id: emergencyPurchaseId,
                        items: cart.map(item => ({
                            product_id: item.product_id,
                            unit_id: item.unit_id,
                            warehouse_location_id: item.warehouse_location_id || null,
                            quantity: item.quantity,
                            selected_price: item.selected_price,
                            discount_percent: item.discount_percent || 0,
                            selected_stock_batch_ids: item.batch_selection_mode === 'manual' ? (item.selected_stock_batch_ids || []) : [],
                            batch_selection_mode: item.batch_selection_mode || 'auto'
                        })),
                        payments: payments.map(payment => ({
                            method: payment.method,
                            amount: payment.amount,
                            reference_no: payment.reference_no || null,
                            due_date: payment.method === 'credit' ? (payment.due_date || null) : null
                        })),
                        custom_due_date: payments.find(p => p.method === 'credit')?.due_date || null,
                    };
                }

                async function checkout() {
                    const button = document.getElementById('confirm-payment');
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
                    try {
                        const data = await api(endpoints.checkout, {
                            method: 'POST',
                            body: JSON.stringify(salePayload())
                        });
                        paymentModal.hide();
                        document.getElementById('success-number').textContent = data.sale.number;
                        document.getElementById('success-total').textContent = money(data.sale.grand_total);
                        document.getElementById('success-change').textContent = money(data.sale.change);
                        receiptPrintUrl = data.sale.print_url;
                        document.getElementById('success-detail').href = data.sale.show_url;

                        const btnThermal = document.getElementById('success-print');
                        if (btnThermal) btnThermal.innerHTML = '<i class="ki-outline ki-receipt-square fs-4 me-1"></i>Cetak Struk (Thermal 80mm)';
                        const btnDot = document.getElementById('success-print-dotmatrix');
                        if (btnDot) btnDot.innerHTML = '<i class="ki-outline ki-document fs-4 me-1"></i>Cetak Faktur (Epson LX-310)';

                        clearTransaction();
                        successModal.show();
                    } catch (error) {
                        window.Swal ? Swal.fire('Checkout gagal', error.message, 'error') : alert(error.message);
                        button.disabled = false;
                    } finally {
                        button.innerHTML = '<i class="ki-outline ki-check fs-4 me-2"></i>SELESAIKAN TRANSAKSI';
                        updatePaymentSummary();
                    }
                }

                async function holdCart() {
                    if (!cart.length) return;
                    const button = document.getElementById('hold-cart');
                    button.disabled = true;
                    try {
                        const data = await api(endpoints.hold, {
                            method: 'POST',
                            body: JSON.stringify({
                                branch_id: branchId,
                                customer_id: customerSelect.value || null,
                                items: salePayload().items
                            })
                        });
                        clearTransaction();
                        document.getElementById('hold-count').textContent = Number(document.getElementById('hold-count')
                            .textContent || 0) + 1;
                        window.Swal ? Swal.fire({
                            text: `${data.hold.number} berhasil ditahan.`,
                            icon: 'success',
                            timer: 1400,
                            showConfirmButton: false
                        }) : null;
                    } catch (error) {
                        window.Swal ? Swal.fire('Hold gagal', error.message, 'error') : alert(error.message);
                    } finally {
                        button.disabled = false;
                        focusScanner();
                    }
                }

                async function openHolds() {
                    const container = document.getElementById('holds-list');
                    container.innerHTML = '<div class="text-center py-8"><span class="spinner-border"></span></div>';
                    holdsModal.show();
                    try {
                        const data = await api(endpoints.holds);
                        document.getElementById('hold-count').textContent = data.count;
                        if (!data.results.length) {
                            container.innerHTML =
                                '<div class="text-center text-muted py-8">Belum ada transaksi hold aktif.</div>';
                            return;
                        }
                        container.innerHTML = data.results.map(hold =>
                            `<div class="border rounded p-3 mb-3 d-flex align-items-center justify-content-between gap-3"><div><strong>${escapeHtml(hold.number)}</strong><div class="text-muted fs-7">${escapeHtml(hold.time)} / ${escapeHtml(hold.customer)} / ${hold.item_count} item / ${money(hold.estimated_total)}</div></div><button type="button" class="btn btn-sm btn-primary resume-hold" data-url="${escapeHtml(hold.resume_url)}">Resume</button></div>`
                            ).join('');
                        container.querySelectorAll('.resume-hold').forEach(button => button.addEventListener('click',
                            async () => {
                                button.disabled = true;
                                try {
                                    const response = await api(button.dataset.url, {
                                        method: 'POST',
                                        body: '{}'
                                    });
                                    holdsModal.hide();
                                    await restoreSnapshot(response.cart);
                                    document.getElementById('hold-count').textContent = Math.max(Number(
                                        document.getElementById('hold-count').textContent) - 1, 0);
                                } catch (error) {
                                    window.Swal ? Swal.fire('Resume gagal', error.message, 'error') : alert(
                                        error.message);
                                }
                            }));
                    } catch (error) {
                        container.innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`;
                    }
                }

                async function restoreSnapshot(snapshot) {
                    customerSelect.value = snapshot?.customer_id || '';
                    if (window.jQuery) window.jQuery(customerSelect).trigger('change.select2');
                    cart = (snapshot?.items || (Array.isArray(snapshot) ? snapshot : [])).map(item => ({
                        ...item,
                        product_id: Number(item.product_id),
                        unit_id: Number(item.unit_id),
                        quantity: Number(item.quantity || item.qty || 1),
                        selected_price: Number(item.selected_price || 0),
                        discount_percent: Number(item.discount_percent || 0),
                        manual_price: true,
                        line_total: Number(item.line_total || 0),
                        quote_version: 0
                    }));
                    await requoteAll();
                    focusScanner();
                }

                function clearTransaction() {
                    cart = [];
                    emergencyPurchaseId = null;
                    payments = [];
                    idempotencyKey = makeUuid();
                    customerSelect.value = '';
                    scanner.value = '';
                    if (window.jQuery) window.jQuery(customerSelect).trigger('change.select2');
                    document.getElementById('temporary-number').textContent =
                        `BARU-${new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`;
                    renderCart();
                    loadProducts(true);
                    focusScanner();
                }

                function startNewTransaction() {
                    successModal.hide();
                    receiptPrintUrl = null;
                    focusScanner();
                }

                function printReceipt(format = 'thermal') {
                    if (!receiptPrintUrl) return;
                    const url = format === 'dotmatrix' 
                        ? (receiptPrintUrl + (receiptPrintUrl.includes('?') ? '&' : '?') + 'format=dotmatrix')
                        : receiptPrintUrl;
                    const width = format === 'dotmatrix' ? 850 : ((printerSettings && printerSettings.printer_thermal_width === '58mm') ? 320 : 420);
                    const height = 720;
                    const printWindow = window.open(url, 'pos-receipt-print', `popup=yes,width=${width},height=${height}`);
                    if (!printWindow) {
                        showToast('Popup cetak diblokir browser. Izinkan popup untuk mencetak.');
                        return;
                    }
                    if (format === 'thermal') {
                        const btn = document.getElementById('success-print');
                        if (btn) btn.innerHTML = '<i class="ki-outline ki-check fs-4 me-1"></i>Cetak Ulang Struk (Thermal)';
                    } else {
                        const btn = document.getElementById('success-print-dotmatrix');
                        if (btn) btn.innerHTML = '<i class="ki-outline ki-check fs-4 me-1"></i>Cetak Ulang Faktur (LX-310)';
                    }
                    document.getElementById('new-transaction')?.focus();
                }

                scanner.addEventListener('input', () => {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(() => loadProducts(true), 260);
                });
                scanner.addEventListener('keydown', event => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        clearTimeout(searchTimer);
                        loadProducts(true, true);
                    }
                });
                document.getElementById('brand-filter').addEventListener('change', () => loadProducts(true));
                document.getElementById('stock-filter').addEventListener('change', () => loadProducts(true));
                document.querySelectorAll('.pos-category').forEach(button => button.addEventListener('click', () => {
                    document.querySelectorAll('.pos-category').forEach(item => {
                        item.classList.remove('btn-primary', 'active');
                        item.classList.add('btn-light');
                    });
                    button.classList.remove('btn-light');
                    button.classList.add('btn-primary', 'active');
                    selectedCategory = button.dataset.category;
                    loadProducts(true);
                }));
                const onCustomerChange = () => {
                    if (!customerSelect.value) {
                        payments.forEach(p => {
                            if (p.method === 'credit') {
                                p.method = 'cash';
                                delete p.due_date;
                            }
                        });
                    }
                    updateCustomerInfoStrip();
                    renderPaymentRows();
                    requoteAll(true);
                    loadProducts(true);
                };
                customerSelect.addEventListener('change', onCustomerChange);
                if (window.jQuery) {
                    window.jQuery(customerSelect).on('change select2:select select2:clear', onCustomerChange);
                }

                // Handle Quick Customer Modal Form
                const qcForm = document.getElementById('form-quick-customer');
                if (qcForm) {
                    qcForm.addEventListener('submit', async function(e) {
                        e.preventDefault();
                        const submitBtn = document.getElementById('btn-submit-quick-customer');
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';

                        const payload = {
                            type: document.getElementById('qc-type').value,
                            name: document.getElementById('qc-name').value,
                            whatsapp_number: document.getElementById('qc-whatsapp').value,
                            minimum_order: document.getElementById('qc-min-order').value,
                            payment_term_days: document.getElementById('qc-payment-terms').value,
                            credit_limit: document.getElementById('qc-credit-limit').value,
                        };

                        try {
                            const resp = await fetch('{{ route('retail.pos.quick-customer') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify(payload)
                            });

                            const data = await resp.json();
                            if (!resp.ok) {
                                throw new Error(data.message || (data.errors ? Object.values(data.errors).flat().join('<br>') : 'Gagal menyimpan pelanggan.'));
                            }

                            const c = data.customer;
                            const opt = document.createElement('option');
                            opt.value = c.id;
                            opt.textContent = c.business_name;
                            opt.setAttribute('data-payment-terms', c.payment_terms);
                            opt.setAttribute('data-credit-limit', c.credit_limit);
                            opt.setAttribute('data-receivable-balance', c.receivable_balance);
                            opt.setAttribute('data-available-credit', c.available_credit);
                            opt.setAttribute('data-minimum-order', c.minimum_order);
                            opt.selected = true;

                            customerSelect.appendChild(opt);
                            customerSelect.value = c.id;

                            if (window.jQuery) {
                                window.jQuery(customerSelect).trigger('change');
                            } else {
                                onCustomerChange();
                            }

                            const qcModalEl = document.getElementById('modal-quick-customer');
                            const qcModal = bootstrap.Modal.getInstance(qcModalEl);
                            if (qcModal) qcModal.hide();
                            qcForm.reset();

                            if (window.Swal) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Pelanggan Berhasil Dibuat',
                                    text: `${c.business_name} telah dipilih sebagai pelanggan transaksi aktif.`,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            }
                        } catch (err) {
                            if (window.Swal) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Gagal Menambah Pelanggan',
                                    html: err.message,
                                    confirmButtonText: 'Tutup'
                                });
                            } else {
                                alert(err.message);
                            }
                        } finally {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="ki-outline ki-check fs-6 me-1"></i> Simpan &amp; Pilih Pelanggan';
                        }
                    });
                }
                document.getElementById('load-more-products').addEventListener('click', () => {
                    if (hasMoreProducts) {
                        productPage++;
                        loadProducts(false);
                    }
                });
                document.getElementById('open-payment').addEventListener('click', openPayment);
                document.getElementById('add-payment').addEventListener('click', () => {
                    payments.push({
                        method: 'qris',
                        amount: 0,
                        reference_no: ''
                    });
                    renderPaymentRows();
                    updatePaymentSummary();
                });
                document.getElementById('confirm-payment').addEventListener('click', checkout);
                document.getElementById('hold-cart').addEventListener('click', holdCart);
                document.getElementById('open-holds').addEventListener('click', openHolds);
                document.getElementById('new-transaction').addEventListener('click', startNewTransaction);
                document.getElementById('success-print')?.addEventListener('click', () => printReceipt('thermal'));
                document.getElementById('success-print-dotmatrix')?.addEventListener('click', () => printReceipt('dotmatrix'));
                document.getElementById('success-modal')?.addEventListener('shown.bs.modal', () => document.getElementById('new-transaction')?.focus());
                document.getElementById('payment-modal').addEventListener('shown.bs.modal', () => document.querySelector(
                    '.payment-amount')?.focus());
                document.querySelectorAll('.modal').forEach(modal => modal.addEventListener('hidden.bs.modal',
                    focusScanner));
                const posSidebarBtn = document.getElementById('pos-sidebar-toggle');
                const updatePosSidebarBtn = () => {
                    if (!posSidebarBtn) return;
                    const isMin = document.body.getAttribute('data-kt-app-sidebar-minimize') === 'on';
                    posSidebarBtn.setAttribute('title', isMin ? 'Perluas sidebar' : 'Kecilkan sidebar');
                    posSidebarBtn.setAttribute('aria-label', isMin ? 'Perluas sidebar' : 'Kecilkan sidebar');
                    posSidebarBtn.setAttribute('aria-expanded', isMin ? 'false' : 'true');
                    const icon = posSidebarBtn.querySelector('i');
                    if (icon) {
                        icon.className = `ki-outline ${isMin ? 'ki-arrow-right' : 'ki-arrow-left'} fs-3`;
                    }
                };

                if (posSidebarBtn) {
                    posSidebarBtn.addEventListener('click', event => {
                        event.preventDefault();
                        event.stopPropagation();
                        if (window.innerWidth >= 992) {
                            const pinBtn = document.getElementById('kt_app_sidebar_pin_btn');
                            if (pinBtn) {
                                pinBtn.click();
                            } else {
                                const isMin = document.body.getAttribute('data-kt-app-sidebar-minimize') === 'on';
                                if (isMin) {
                                    document.body.removeAttribute('data-kt-app-sidebar-minimize');
                                    localStorage.setItem('gudangtoko-sidebar-pinned', 'true');
                                } else {
                                    document.body.setAttribute('data-kt-app-sidebar-minimize', 'on');
                                    localStorage.setItem('gudangtoko-sidebar-pinned', 'false');
                                }
                            }
                        } else {
                            const mobileToggle = document.getElementById('kt_app_sidebar_mobile_toggle');
                            if (mobileToggle) {
                                mobileToggle.click();
                            } else {
                                const sidebar = document.getElementById('kt_app_sidebar');
                                if (window.KTDrawer && sidebar) {
                                    const drawer = window.KTDrawer.getInstance(sidebar) || new window.KTDrawer(sidebar);
                                    drawer.toggle();
                                }
                            }
                        }
                        updatePosSidebarBtn();
                    });

                    const sidebarObserver = new MutationObserver(() => updatePosSidebarBtn());
                    sidebarObserver.observe(document.body, {
                        attributes: true,
                        attributeFilter: ['data-kt-app-sidebar-minimize']
                    });
                    updatePosSidebarBtn();
                }
                // ==================== RETURN MODAL LOGIC ====================
                let currentReturnSale = null;
                let lastPrintedReceiptUrl = null;
                let lastPrintedReturnUrl = null;

                const openReturnModal = () => {
                    if (!returnModal) return;
                    resetReturnModal();
                    returnModal.show();
                    loadRecentSalesForReturn();
                };

                const resetReturnModal = () => {
                    currentReturnSale = null;
                    document.getElementById('return-step-search')?.classList.remove('d-none');
                    document.getElementById('return-step-items')?.classList.add('d-none');
                    document.getElementById('return-step-success')?.classList.add('d-none');
                    const searchInput = document.getElementById('return-search-input');
                    if (searchInput) searchInput.value = '';
                    const itemsTbody = document.getElementById('return-items-tbody');
                    if (itemsTbody) itemsTbody.innerHTML = '';
                    const totalDisplay = document.getElementById('return-total-refund-display');
                    if (totalDisplay) totalDisplay.textContent = 'Rp 0';
                    const confirmBtn = document.getElementById('btn-submit-return-confirm');
                    if (confirmBtn) confirmBtn.disabled = true;
                    setTimeout(() => searchInput?.focus(), 250);
                };

                const loadRecentSalesForReturn = async (query = '') => {
                    const container = document.getElementById('return-recent-container');
                    if (!container) return;
                    container.innerHTML = `<div class="text-center py-5 text-muted fs-8"><span class="spinner-border spinner-border-sm me-2"></span>Memuat transaksi cabang...</div>`;

                    try {
                        const url = new URL(endpoints.salesLookup, window.location.origin);
                        if (query) url.searchParams.set('query', query);

                        const response = await fetch(url.toString(), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const data = await response.json();

                        if (!data.success || !data.sales || data.sales.length === 0) {
                            container.innerHTML = `<div class="text-center py-5 text-muted fs-8"><i class="ki-outline ki-information fs-4 d-block mb-1"></i>Tidak ditemukan transaksi penjualan selesai dengan pencarian tersebut.</div>`;
                            return;
                        }

                        let html = `
                            <table class="table table-sm table-hover align-middle mb-0 gs-3 gy-2">
                                <thead class="bg-light fs-8 text-muted text-uppercase fw-bold">
                                    <tr>
                                        <th class="ps-3">No. Faktur</th>
                                        <th>Waktu</th>
                                        <th>Pelanggan</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-end pe-3">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-8">
                        `;
                        data.sales.forEach(sale => {
                            html += `
                                <tr>
                                    <td class="ps-3 fw-bold text-gray-900 font-monospace">${escapeHtml(sale.number)}</td>
                                    <td class="text-muted">${escapeHtml(sale.date)}</td>
                                    <td>${escapeHtml(sale.customer_name)}</td>
                                    <td class="text-end fw-bold">${money(sale.grand_total)}</td>
                                    <td class="text-center">
                                        <span class="badge badge-light-${sale.status === 'returned' ? 'warning' : 'success'} fs-9">${escapeHtml(sale.status_label)}</span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <button type="button" class="btn btn-sm btn-light-primary py-1 px-3 btn-select-sale" data-sale='${JSON.stringify(sale).replace(/'/g, "&apos;")}'>
                                            Pilih Struk
                                        </button>
                                    </td>
                                </tr>
                            `;
                        });
                        html += `</tbody></table>`;
                        container.innerHTML = html;

                        container.querySelectorAll('.btn-select-sale').forEach(btn => {
                            btn.addEventListener('click', () => {
                                const saleData = JSON.parse(btn.getAttribute('data-sale'));
                                selectSaleForReturn(saleData);
                            });
                        });
                    } catch (err) {
                        container.innerHTML = `<div class="text-center py-5 text-danger fs-8">Gagal memuat transaksi: ${escapeHtml(err.message)}</div>`;
                    }
                };

                const selectSaleForReturn = (sale) => {
                    currentReturnSale = sale;
                    document.getElementById('return-step-search')?.classList.add('d-none');
                    document.getElementById('return-step-items')?.classList.remove('d-none');
                    document.getElementById('return-step-success')?.classList.add('d-none');

                    document.getElementById('return-sale-number').textContent = sale.number;
                    document.getElementById('return-sale-status').textContent = sale.status_label;
                    document.getElementById('return-sale-status').className = `badge badge-light-${sale.status === 'returned' ? 'warning' : 'primary'} ms-2 fs-8`;
                    document.getElementById('return-sale-date').textContent = sale.date;
                    document.getElementById('return-sale-customer').textContent = sale.customer_name;
                    document.getElementById('return-sale-cashier').textContent = sale.cashier_name;
                    document.getElementById('return-sale-total').textContent = money(sale.grand_total);
                    document.getElementById('return-input-sale-id').value = sale.id;

                    const tbody = document.getElementById('return-items-tbody');
                    if (!tbody) return;
                    tbody.innerHTML = '';

                    sale.items.forEach((item, idx) => {
                        const tr = document.createElement('tr');
                        const canReturn = item.remaining_quantity > 0;
                        tr.innerHTML = `
                            <td class="ps-4">
                                <div class="fw-bold text-gray-900">${escapeHtml(item.product_name)}</div>
                                <div class="text-muted fs-8 font-monospace">${escapeHtml(item.sku || '-')} &bull; ${money(item.selected_price)} / ${escapeHtml(item.unit)}</div>
                                <input type="hidden" name="items[${idx}][pos_sale_item_id]" value="${item.id}">
                            </td>
                            <td class="text-center">${qty(item.quantity)} ${escapeHtml(item.unit)}</td>
                            <td class="text-center">
                                ${canReturn 
                                    ? `<span class="badge badge-light-success fw-bold">${qty(item.remaining_quantity)} ${escapeHtml(item.unit)}</span>` 
                                    : `<span class="badge badge-light-secondary fs-9">Sudah Habis</span>`}
                            </td>
                            <td class="text-center">
                                <input type="number" 
                                    class="form-control form-control-sm text-center return-item-qty" 
                                    name="items[${idx}][quantity]" 
                                    data-price="${item.selected_price}" 
                                    data-max="${item.remaining_quantity}" 
                                    step="any" 
                                    min="0" 
                                    max="${item.remaining_quantity}" 
                                    value="0" 
                                    ${!canReturn ? 'disabled' : ''}>
                            </td>
                            <td class="text-center">
                                <select class="form-select form-select-sm return-item-condition" name="items[${idx}][condition]" ${!canReturn ? 'disabled' : ''}>
                                    <option value="good" selected>Baik (Kembali ke Stok)</option>
                                    <option value="damaged">Rusak (Inventaris Rusak)</option>
                                </select>
                            </td>
                            <td class="text-end pe-4 fw-bolder return-item-subtotal text-danger">
                                Rp 0
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });

                    tbody.querySelectorAll('.return-item-qty').forEach(input => {
                        input.addEventListener('input', () => {
                            const max = parseFloat(input.getAttribute('data-max') || '0');
                            let val = parseFloat(input.value || '0');
                            if (val > max) {
                                input.value = max;
                                val = max;
                            }
                            if (val < 0 || isNaN(val)) {
                                input.value = 0;
                                val = 0;
                            }
                            const price = parseFloat(input.getAttribute('data-price') || '0');
                            const subtotal = val * price;
                            const row = input.closest('tr');
                            row.querySelector('.return-item-subtotal').textContent = money(subtotal);
                            recalculateReturnTotal();
                        });
                    });

                    recalculateReturnTotal();
                };

                const recalculateReturnTotal = () => {
                    let totalRefund = 0;
                    let hasAnyQty = false;

                    document.querySelectorAll('.return-item-qty').forEach(input => {
                        const val = parseFloat(input.value || '0');
                        const price = parseFloat(input.getAttribute('data-price') || '0');
                        if (val > 0) {
                            totalRefund += (val * price);
                            hasAnyQty = true;
                        }
                    });

                    const totalDisplay = document.getElementById('return-total-refund-display');
                    if (totalDisplay) totalDisplay.textContent = money(totalRefund);

                    const confirmBtn = document.getElementById('btn-submit-return-confirm');
                    if (confirmBtn) confirmBtn.disabled = !hasAnyQty;
                };

                document.getElementById('btn-search-return')?.addEventListener('click', () => {
                    const query = document.getElementById('return-search-input')?.value.trim();
                    loadRecentSalesForReturn(query);
                });

                document.getElementById('return-search-input')?.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const query = e.target.value.trim();
                        loadRecentSalesForReturn(query);
                    }
                });

                document.getElementById('btn-reload-recent-sales')?.addEventListener('click', () => {
                    loadRecentSalesForReturn('');
                });

                document.getElementById('btn-change-sale')?.addEventListener('click', () => {
                    resetReturnModal();
                });

                document.getElementById('open-return-modal')?.addEventListener('click', () => {
                    openReturnModal();
                });

                document.getElementById('form-submit-return')?.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const confirmBtn = document.getElementById('btn-submit-return-confirm');
                    if (!confirmBtn || confirmBtn.disabled) return;

                    const formData = new FormData(e.target);
                    const reason = formData.get('reason')?.toString().trim();
                    if (!reason) {
                        alert('Mohon isi alasan pengembalian/retur.');
                        return;
                    }

                    const payload = {
                        pos_sale_id: formData.get('pos_sale_id'),
                        refund_method: formData.get('refund_method'),
                        resolution: 'refund',
                        reason: reason,
                        items: [],
                    };

                    document.querySelectorAll('#return-items-tbody tr').forEach((tr) => {
                        const qtyInput = tr.querySelector('.return-item-qty');
                        const itemQty = parseFloat(qtyInput?.value || '0');
                        if (itemQty > 0) {
                            const itemId = tr.querySelector('input[type="hidden"]')?.value;
                            const condition = tr.querySelector('.return-item-condition')?.value || 'good';
                            payload.items.push({
                                pos_sale_item_id: parseInt(itemId),
                                quantity: itemQty,
                                condition: condition,
                            });
                        }
                    });

                    if (payload.items.length === 0) {
                        alert('Pilih minimal satu item dengan kuantitas lebih dari nol untuk diretur.');
                        return;
                    }

                    confirmBtn.disabled = true;
                    confirmBtn.querySelector('.indicator-label')?.classList.add('d-none');
                    confirmBtn.querySelector('.indicator-progress')?.classList.remove('d-none');

                    try {
                        const res = await fetch(endpoints.processReturn, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify(payload)
                        });

                        const result = await res.json();
                        if (!res.ok || !result.success) {
                            throw new Error(result.message || 'Terjadi kesalahan saat memproses retur.');
                        }

                        document.getElementById('return-step-items')?.classList.add('d-none');
                        document.getElementById('return-step-success')?.classList.remove('d-none');

                        document.getElementById('return-success-number').textContent = result.return.number;
                        document.getElementById('return-success-amount').textContent = money(result.return.refund_amount);
                        document.getElementById('return-success-method').textContent = result.return.refund_method === 'cash' ? 'Kas / Tunai' : result.return.refund_method.toUpperCase();

                        lastPrintedReceiptUrl = result.return.print_receipt_url;
                        lastPrintedReturnUrl = result.return.print_return_url;

                        setTimeout(() => {
                            document.getElementById('btn-print-reprinted-receipt')?.focus();
                        }, 60);

                    } catch (err) {
                        alert(err.message || 'Gagal memproses retur.');
                    } finally {
                        confirmBtn.disabled = false;
                        confirmBtn.querySelector('.indicator-label')?.classList.remove('d-none');
                        confirmBtn.querySelector('.indicator-progress')?.classList.add('d-none');
                    }
                });

                document.getElementById('btn-print-reprinted-receipt')?.addEventListener('click', () => {
                    if (lastPrintedReceiptUrl) {
                        window.open(lastPrintedReceiptUrl, '_blank', 'width=450,height=650,menubar=no,toolbar=no,location=no,status=no');
                    }
                });

                document.getElementById('btn-print-return-receipt')?.addEventListener('click', () => {
                    if (lastPrintedReturnUrl) {
                        window.open(lastPrintedReturnUrl, '_blank', 'width=450,height=650,menubar=no,toolbar=no,location=no,status=no');
                    }
                });

                returnModalEl?.addEventListener('hidden.bs.modal', () => {
                    loadProducts(true);
                    focusScanner(true);
                });

                window.addEventListener('focus', () => focusScanner());
                document.addEventListener('keydown', event => {
                    if (event.key === 'F2') {
                        event.preventDefault();
                        focusScanner();
                    }
                    if (event.key === 'F4') {
                        event.preventDefault();
                        if (window.jQuery && window.jQuery(customerSelect).hasClass('select2-hidden-accessible'))
                            window.jQuery(customerSelect).select2('open');
                        else customerSelect.focus();
                    }
                    if (event.key === 'F6') {
                        event.preventDefault();
                        cart.length ? holdCart() : openHolds();
                    }
                    if (event.key === 'F7') {
                        event.preventDefault();
                        openReturnModal();
                    }
                    if (event.key === 'F8') {
                        event.preventDefault();
                        openPayment();
                    }
                    if (event.key === 'Escape' && !document.querySelector('.modal.show')) {
                        scanner.value = '';
                        loadProducts(true);
                        focusScanner();
                    }
                    if (event.key === 'Enter' && document.getElementById('payment-modal').classList.contains(
                        'show') && !document.getElementById('confirm-payment').disabled) {
                        event.preventDefault();
                        checkout();
                    }
                    if (document.getElementById('success-modal')?.classList.contains('show')) {
                        if (event.key === 'Escape' || (event.key === 'Enter' && event.target.id !== 'success-print' && event.target.id !== 'success-print-dotmatrix')) {
                            event.preventDefault();
                            startNewTransaction();
                        } else if (event.key === 'F9') {
                            event.preventDefault();
                            printReceipt('thermal');
                        } else if (event.key === 'F10') {
                            event.preventDefault();
                            printReceipt('dotmatrix');
                        }
                    }
                });

                document.getElementById('temporary-number').textContent =
                    `BARU-${new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`;
                renderCart();
                loadProducts(true);
                focusScanner(true);
                if (resumeCart) restoreSnapshot(resumeCart);
            };

            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeScannerFirstPos, {
                once: true
            });
            else initializeScannerFirstPos();
        </script>
    @endpush
@endif
