@extends('layouts.metronic.app')

@section('title', 'Kasir POS - ' . config('app.name'))
@section('page_title', 'Kasir POS')
@section('body_attributes', 'data-kt-app-sidebar-collapse=on')

@section('content')
    @if(!$activeShift || !$branch)
        <div class="pos-shift-required bg-body border rounded p-10 text-center">
            <i class="ki-outline ki-warning fs-3x text-warning"></i>
            <h1 class="fs-2 fw-bold mt-5">Shift kasir belum dibuka</h1>
            <p class="text-muted mb-6">Cabang POS ditentukan otomatis dari shift aktif. Buka shift sebelum memindai produk.</p>
            <a href="{{ route('retail.shifts.open') }}" class="btn btn-primary btn-lg"><i class="ki-outline ki-wallet fs-4 me-2"></i>Buka Shift</a>
        </div>
    @else
        @if($errors->any())
            <div class="alert alert-danger d-flex align-items-center mb-4"><i class="ki-outline ki-information-5 fs-2 me-3"></i><div>{{ $errors->first() }}</div></div>
        @endif

        <div class="pos-shell" id="pos-workspace">
            <header class="pos-context-bar bg-body border rounded px-4 py-3 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex flex-wrap align-items-center gap-5">
                    <div><span class="text-muted fs-8 d-block">CABANG</span><strong><i class="ki-outline ki-shop me-1"></i>{{ $branch->name }}</strong></div>
                    <div><span class="text-muted fs-8 d-block">KASIR</span><strong>{{ auth()->user()->name }}</strong></div>
                    <div><span class="text-muted fs-8 d-block">SHIFT</span><strong>{{ $activeShift->number }}</strong></div>
                    <div><span class="text-muted fs-8 d-block">TRANSAKSI</span><strong id="temporary-number">BARU</strong></div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" id="pos-sidebar-toggle" class="btn btn-icon btn-light" title="Buka/tutup sidebar" aria-label="Buka atau tutup sidebar" aria-expanded="false"><i class="ki-outline ki-arrow-right fs-3"></i></button>
                    <button type="button" id="open-holds" class="btn btn-light-warning"><i class="ki-outline ki-time fs-5 me-1"></i>Hold (<span id="hold-count">{{ $holdCount }}</span>) <kbd>F6</kbd></button>
                    <a href="{{ route('retail.shifts.current') }}" class="btn btn-icon btn-light" title="Detail shift" aria-label="Detail shift"><i class="ki-outline ki-information-2 fs-5"></i></a>
                </div>
            </header>

            <div class="row g-4 align-items-stretch">
                <section class="col-xl-5" aria-labelledby="product-finder-title">
                    <div class="pos-panel bg-body border rounded h-100 d-flex flex-column">
                        <div class="p-4 border-bottom">
                            <h2 id="product-finder-title" class="fs-4 fw-bold mb-3">Cari Produk</h2>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light"><i class="ki-outline ki-search fs-2"></i></span>
                                <input id="pos-scanner" type="search" class="form-control" autocomplete="off" autofocus placeholder="Scan barcode / Cari produk" aria-label="Scan barcode atau cari produk">
                                <span class="input-group-text bg-light"><kbd>F2</kbd></span>
                            </div>
                            <div id="scanner-message" class="fs-7 mt-2 text-muted" aria-live="polite">Scan barcode lalu Enter, atau ketik nama/SKU untuk mencari.</div>
                            <div class="row g-2 mt-2">
                                <div class="col-6">
                                    <select id="brand-filter" class="form-select form-select-sm" data-placeholder="Semua merek" data-allow-clear="true">
                                        <option value="">Semua merek</option>@foreach($brands as $brand)<option value="{{ $brand->id }}">{{ $brand->name }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-6 d-flex align-items-center">
                                    <label class="form-check form-switch mb-0"><input id="stock-filter" class="form-check-input" type="checkbox" checked><span class="form-check-label fs-7">Hanya stok tersedia</span></label>
                                </div>
                            </div>
                            <div id="category-filter" class="d-flex gap-2 overflow-auto pt-3 pb-1">
                                <button type="button" class="btn btn-sm btn-primary pos-category active" data-category="">Semua</button>
                                @foreach($categories as $category)<button type="button" class="btn btn-sm btn-light pos-category flex-shrink-0" data-category="{{ $category->id }}">{{ $category->name }}</button>@endforeach
                            </div>
                        </div>
                        <div id="product-results" class="pos-product-results p-4 flex-grow-1">
                            <div class="text-center text-muted py-10"><span class="spinner-border spinner-border-sm me-2"></span>Memuat produk...</div>
                        </div>
                        <div class="px-4 pb-4"><button type="button" id="load-more-products" class="btn btn-sm btn-light w-100 d-none">Muat lebih banyak</button></div>
                    </div>
                </section>

                <section class="col-xl-7" aria-labelledby="cart-title">
                    <div class="pos-panel bg-body border rounded h-100 d-flex flex-column">
                        <div class="p-4 border-bottom d-flex align-items-center justify-content-between gap-3">
                            <div><h2 id="cart-title" class="fs-4 fw-bold mb-0">Keranjang</h2><span id="cart-item-label" class="text-muted fs-7">0 item</span></div>
                            <div class="pos-customer-wrap">
                                <label for="customer_id" class="text-muted fs-8 d-block">PELANGGAN <kbd>F4</kbd></label>
                                <select id="customer_id" class="form-select form-select-sm" data-placeholder="Pelanggan Umum" data-allow-clear="true">
                                    <option value="">Pelanggan Umum</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->business_name }}</option>@endforeach
                                </select>
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
                                    <div class="d-flex justify-content-between fs-7 mb-1"><span class="text-muted">Subtotal</span><strong id="cart-subtotal">Rp 0</strong></div>
                                    <div class="d-flex justify-content-between fs-7"><span class="text-muted">Diskon</span><strong id="cart-discount">Rp 0</strong></div>
                                </div>
                                <div class="col-sm-7 text-sm-end">
                                    <div class="text-muted fs-8">GRAND TOTAL</div>
                                    <div id="cart-grand-total" class="pos-grand-total fw-bold text-primary">Rp 0</div>
                                </div>
                            </div>
                            <div class="d-grid gap-3 mt-4 pos-main-actions">
                                <button type="button" id="hold-cart" class="btn btn-light-warning btn-lg"><i class="ki-outline ki-time fs-4 me-2"></i>HOLD <kbd>F6</kbd></button>
                                <button type="button" id="open-payment" class="btn btn-primary btn-lg"><i class="ki-outline ki-wallet fs-3 me-2"></i>BAYAR <kbd>F8</kbd></button>
                            </div>
                        </footer>
                    </div>
                </section>
            </div>
        </div>

        <div class="modal fade" id="payment-modal" tabindex="-1" aria-labelledby="payment-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-sm-down"><div class="modal-content">
                <div class="modal-header"><div><h2 id="payment-title" class="modal-title">Pembayaran</h2><div class="text-muted fs-7">Periksa ringkasan sebelum menyelesaikan transaksi.</div></div><button type="button" class="btn btn-sm btn-icon btn-light" data-bs-dismiss="modal" aria-label="Tutup"><i class="ki-outline ki-cross fs-3"></i></button></div>
                <div class="modal-body">
                    <div class="text-center border-bottom pb-5 mb-5"><div class="text-muted fs-7">TOTAL BELANJA</div><div id="payment-grand-total" class="pos-payment-total fw-bold text-primary">Rp 0</div></div>
                    <div id="payment-price-warning" class="alert alert-danger d-none"></div>
                    <div class="row g-5">
                        <div class="col-lg-8">
                            <div class="d-flex align-items-center justify-content-between mb-3"><h3 class="fs-5 mb-0">Metode Pembayaran</h3><button type="button" id="add-payment" class="btn btn-sm btn-light-primary"><i class="ki-outline ki-plus fs-5"></i> Split</button></div>
                            <div id="payment-rows" class="d-grid gap-3"></div>
                            <div id="cash-quick-buttons" class="d-flex flex-wrap gap-2 mt-4"></div>
                        </div>
                        <div class="col-lg-4">
                            <div class="bg-light rounded p-5 pos-payment-summary">
                                <div class="d-flex justify-content-between mb-3"><span>Total Belanja</span><strong id="payment-summary-total">Rp 0</strong></div>
                                <div class="d-flex justify-content-between mb-3"><span>Dibayar</span><strong id="payment-paid">Rp 0</strong></div>
                                <div class="d-flex justify-content-between mb-2"><span>Kekurangan</span><strong id="payment-shortage" class="text-danger">Rp 0</strong></div>
                                <div class="separator my-3"></div>
                                <div class="text-muted fs-8">KEMBALIAN</div><div id="payment-change" class="pos-payment-change fw-bold text-success">Rp 0</div>
                            </div>
                            <div class="mt-4 fs-7">
                                <div><strong id="payment-item-count">0 item</strong></div>
                                <div id="payment-customer" class="text-muted">Pelanggan Umum</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="button" id="confirm-payment" class="btn btn-primary btn-lg"><i class="ki-outline ki-check fs-4 me-2"></i>SELESAIKAN TRANSAKSI</button></div>
            </div></div>
        </div>

        <div class="modal fade" id="holds-modal" tabindex="-1" aria-labelledby="holds-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
                <div class="modal-header"><h2 id="holds-title" class="modal-title">Transaksi Hold</h2><button type="button" class="btn btn-sm btn-icon btn-light" data-bs-dismiss="modal" aria-label="Tutup"><i class="ki-outline ki-cross fs-3"></i></button></div>
                <div class="modal-body" id="holds-list"></div>
                <div class="modal-footer"><a href="{{ route('retail.pos.holds') }}" class="btn btn-light">Lihat Semua</a><button type="button" class="btn btn-primary" data-bs-dismiss="modal">Tutup</button></div>
            </div></div>
        </div>

        <div class="modal fade" id="success-modal" tabindex="-1" aria-labelledby="success-title" data-bs-backdrop="static" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content text-center">
                <div class="modal-body p-8"><i class="ki-outline ki-check-circle text-success fs-3x"></i><h2 id="success-title" class="mt-4">Transaksi Berhasil</h2><div id="success-number" class="text-muted fw-semibold"></div><div class="mt-5 text-muted">Total</div><div id="success-total" class="fs-2x fw-bold"></div><div class="mt-3 text-muted">Kembalian</div><div id="success-change" class="fs-2 fw-bold text-success"></div><div class="d-grid gap-3 mt-7"><button type="button" id="success-print" class="btn btn-primary"><i class="ki-outline ki-printer fs-4 me-2"></i>Cetak Struk</button><button type="button" id="new-transaction" class="btn btn-light-primary">Transaksi Baru</button><a id="success-detail" class="btn btn-light">Lihat Detail</a></div></div>
            </div></div>
        </div>
    @endif
@endsection

@push('styles')
<style>
    .pos-shell { min-height: calc(100vh - 175px); min-width: 0; }
    .pos-panel { min-height: calc(100vh - 270px); overflow: hidden; min-width: 0; }
    .pos-product-results { overflow-y: auto; max-height: calc(100vh - 465px); }
    .pos-product-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .pos-product-card { border: 1px solid var(--bs-gray-300); border-radius: 6px; padding: 10px; min-width: 0; background: var(--bs-body-bg); }
    .pos-product-card:hover { border-color: var(--bs-primary); box-shadow: 0 2px 8px rgba(0,0,0,.06); }
    .pos-product-image, .pos-cart-image { object-fit: contain; background: #fff; border: 1px solid var(--bs-gray-200); border-radius: 5px; flex-shrink: 0; }
    .pos-product-image { width: 76px; height: 76px; }
    .pos-cart-image { width: 48px; height: 48px; }
    .pos-image-placeholder { display: flex; align-items: center; justify-content: center; color: var(--bs-gray-500); }
    .pos-product-name { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.35; min-height: 2.7em; overflow-wrap: anywhere; }
    .pos-product-meta { min-width: 0; overflow: hidden; }
    .pos-ring-list { display: flex; flex-wrap: wrap; gap: 4px 8px; }
    .pos-ring { font-size: 11px; white-space: nowrap; color: var(--bs-gray-700); }
    .pos-ring.is-selected { color: var(--bs-primary); font-weight: 700; }
    .pos-cart-scroll { overflow-y: auto; overflow-x: hidden; max-height: calc(100vh - 495px); min-height: 260px; }
    .pos-cart-item { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; grid-template-areas: "product qty action" "price discount subtotal"; align-items: center; gap: 10px 14px; border: 1px solid var(--bs-gray-300); border-left-width: 3px; border-radius: 6px; padding: 10px; min-width: 0; }
    .pos-cart-product { grid-area: product; min-width: 0; }
    .pos-cart-product .cart-unit { width: 110px; max-width: 100%; }
    .pos-cart-qty { grid-area: qty; }
    .pos-cart-price { grid-area: price; min-width: 0; }
    .pos-cart-discount { grid-area: discount; width: 96px; }
    .pos-cart-subtotal { grid-area: subtotal; min-width: 105px; text-align: right; }
    .pos-cart-action { grid-area: action; }
    .pos-cart-label { display: block; color: var(--bs-gray-600); font-size: 10px; font-weight: 600; margin-bottom: 3px; text-transform: uppercase; }
    .pos-qty-control { display: grid; grid-template-columns: 34px minmax(52px, 68px) 34px; }
    .pos-qty-control .btn, .pos-qty-control input { border-radius: 0; min-height: 38px; padding: 4px; }
    .pos-price-input { width: min(170px, 100%); }
    .pos-customer-wrap { width: min(270px, 52vw); }
    .pos-grand-total { font-size: 38px; line-height: 1.05; letter-spacing: 0; overflow-wrap: anywhere; }
    .pos-main-actions { grid-template-columns: 1fr 2fr; }
    .pos-category { white-space: nowrap; }
    .pos-price-safe { border-left: 3px solid var(--bs-success); }
    .pos-price-near { border-left: 3px solid var(--bs-warning); }
    .pos-price-below, .pos-price-above, .pos-price-approval { border-left: 3px solid var(--bs-danger); background: var(--bs-danger-light); }
    .pos-payment-total { font-size: 42px; line-height: 1.1; letter-spacing: 0; }
    .pos-payment-change { font-size: 34px; line-height: 1.1; letter-spacing: 0; overflow-wrap: anywhere; }
    .pos-payment-row { display: grid; grid-template-columns: minmax(150px, .8fr) minmax(190px, 1.2fr) minmax(130px, 1fr) 42px; gap: 10px; align-items: end; }
    .payment-amount { font-size: 18px; font-weight: 700; }
    .quick-cash { min-height: 44px; padding-inline: 16px; font-weight: 600; }
    kbd { font-size: 10px; font-weight: 600; color: var(--bs-gray-700); background: var(--bs-gray-200); margin-left: 4px; }
    @media (min-width: 1600px) {
        .pos-cart-item { grid-template-columns: minmax(210px, 1.6fr) auto minmax(145px, .9fr) 90px minmax(110px, .7fr) 38px; grid-template-areas: "product qty price discount subtotal action"; }
    }
    @media (max-width: 1399.98px) {
        .pos-context-bar { padding: 10px 12px !important; }
        .pos-context-bar > div:first-child { gap: 14px !important; }
        .pos-product-image { width: 64px; height: 64px; }
        .pos-product-results { padding: 10px !important; }
        .pos-product-grid { gap: 8px; }
    }
    @media (max-width: 1199.98px) {
        .pos-panel { min-height: auto; }
        .pos-product-results, .pos-cart-scroll { max-height: 520px; }
        .pos-product-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 991.98px) {
        .pos-product-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .pos-payment-row { grid-template-columns: 1fr 1.2fr 42px; }
        .pos-payment-reference { grid-column: 1 / -1; }
    }
    @media (max-width: 767.98px) {
        .pos-product-grid { grid-template-columns: 1fr; }
        .pos-main-actions { grid-template-columns: 1fr; }
        .pos-grand-total { font-size: 30px; }
        .pos-customer-wrap { width: 100%; }
        .pos-cart-item { grid-template-columns: minmax(0, 1fr) auto; grid-template-areas: "product action" "qty subtotal" "price price" "discount discount"; }
        .pos-cart-discount { width: 100%; }
        .pos-price-input { width: 100%; }
        .pos-payment-row { grid-template-columns: 1fr 42px; }
        .pos-payment-method, .pos-payment-amount-wrap, .pos-payment-reference { grid-column: 1 / -1; }
        .pos-payment-remove { grid-column: 2; grid-row: 1; }
        .pos-payment-total { font-size: 34px; }
    }
</style>
@endpush

@if($activeShift && $branch)
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
    };
    const branchId = @json($branch->id);
    const paymentMethods = @json($paymentMethods);
    const resumeCart = @json($resumeCart);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const scanner = document.getElementById('pos-scanner');
    const productResults = document.getElementById('product-results');
    const cartBody = document.getElementById('cart-items');
    const customerSelect = document.getElementById('customer_id');
    const paymentModal = new bootstrap.Modal(document.getElementById('payment-modal'));
    const holdsModal = new bootstrap.Modal(document.getElementById('holds-modal'));
    const successModal = new bootstrap.Modal(document.getElementById('success-modal'));
    if (window.jQuery && typeof window.jQuery.fn.select2 === 'function' && !window.jQuery(customerSelect).hasClass('select2-hidden-accessible')) {
        window.jQuery(customerSelect).select2({ placeholder: 'Pelanggan Umum', allowClear: true, width: '100%' });
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

    const money = value => 'Rp ' + Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
    const qty = value => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 4 });
    const rupiahValue = value => Number(String(value ?? '').replace(/[^0-9]/g, '')) || 0;
    const escapeHtml = value => { const node = document.createElement('div'); node.textContent = value ?? ''; return node.innerHTML; };
    const customerName = () => customerSelect.options[customerSelect.selectedIndex]?.text || 'Pelanggan Umum';
    const focusScanner = (force = false) => window.setTimeout(() => {
        const active = document.activeElement;
        const isEditing = active?.matches?.('input, select, textarea, [contenteditable="true"]');
        if (!force && (document.querySelector('.modal.show') || isEditing)) return;
        scanner.focus(); scanner.select();
    }, 80);

    function makeUuid() {
        return window.crypto?.randomUUID?.() || `pos-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    }

    async function api(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Permintaan tidak dapat diproses.');
        return data;
    }

    function showScannerMessage(message, type = 'muted') {
        const element = document.getElementById('scanner-message');
        element.className = `fs-7 mt-2 text-${type}`;
        element.textContent = message;
    }

    function showToast(message, icon = 'error') {
        if (!window.Swal) return;
        Swal.fire({ toast: true, position: 'top-end', icon, text: message, timer: 1800, showConfirmButton: false, timerProgressBar: true });
    }

    function imageMarkup(url, sizeClass, iconClass) {
        return url
            ? `<img src="${escapeHtml(url)}" class="${sizeClass}" alt="" loading="lazy">`
            : `<div class="${sizeClass} pos-image-placeholder"><i class="ki-outline ki-package ${iconClass}"></i></div>`;
    }

    function bindImageFallback(root, sizeClass, iconClass) {
        root.querySelectorAll(`img.${sizeClass}`).forEach(image => image.addEventListener('error', () => {
            const fallback = document.createElement('div');
            fallback.className = `${sizeClass} pos-image-placeholder`;
            fallback.innerHTML = `<i class="ki-outline ki-package ${iconClass}"></i>`;
            image.replaceWith(fallback);
        }, { once: true }));
    }

    async function loadProducts(reset = true, immediateScan = false) {
        if (reset) { productPage = 1; productResults.innerHTML = '<div class="text-center text-muted py-10"><span class="spinner-border spinner-border-sm me-2"></span>Mencari produk...</div>'; }
        const params = new URLSearchParams({ q: scanner.value.trim(), page: productPage, per_page: 24, in_stock: document.getElementById('stock-filter').checked ? '1' : '0' });
        if (customerSelect.value) params.set('customer_id', customerSelect.value);
        if (selectedCategory) params.set('category_id', selectedCategory);
        if (document.getElementById('brand-filter').value) params.set('brand_id', document.getElementById('brand-filter').value);
        const sequence = ++requestSequence;
        try {
            const data = await api(`${endpoints.products}?${params}`);
            if (sequence !== requestSequence) return;
            if (data.exact_match && data.results.length === 1) {
                const item = data.results[0];
                if (!item.stock_sufficient || Number(item.stock) <= 0) {
                    showScannerMessage(`${item.name}: stok tidak tersedia.`, 'danger');
                } else {
                    await addToCart(item);
                    scanner.value = '';
                    showScannerMessage(`${item.name} ditambahkan ke keranjang.`, 'success');
                }
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
            productResults.innerHTML = '<div class="text-center text-muted py-10"><i class="ki-outline ki-search fs-2x"></i><div class="mt-3">Produk tidak ditemukan.</div></div>';
            return;
        }
        let grid = productResults.querySelector('.pos-product-grid');
        if (!grid) { grid = document.createElement('div'); grid.className = 'pos-product-grid'; productResults.appendChild(grid); }
        products.forEach(product => {
            const image = imageMarkup(product.image_url, 'pos-product-image', 'fs-2x');
            const stockClass = Number(product.stock) <= 0 ? 'danger' : (product.stock_low ? 'warning' : 'success');
            const rings = (product.pricing.rings || [{ label: product.pricing.ring || 'Harga POS', price: product.pricing.recommended_price, selected: true }])
                .map(ring => `<span class="pos-ring ${ring.selected ? 'is-selected' : ''}">${escapeHtml(ring.label)} <strong>${money(ring.price)}</strong></span>`).join('');
            const card = document.createElement('article');
            card.className = 'pos-product-card';
            card.innerHTML = `<div class="d-flex gap-3">${image}<div class="pos-product-meta flex-grow-1"><div class="fw-bold pos-product-name" title="${escapeHtml(product.name)}">${escapeHtml(product.name)}</div><div class="text-muted fs-8 text-truncate mt-1">${escapeHtml(product.sku)} / ${escapeHtml(product.unit)}</div><div class="text-${stockClass} fs-8 mt-1">Stok ${qty(product.stock)}${product.stock_low ? ' / menipis' : ''}</div></div></div><div class="pos-ring-list mt-3">${rings}</div><button type="button" class="btn btn-sm btn-primary add-product w-100 mt-3" ${Number(product.stock) <= 0 ? 'disabled' : ''}><i class="ki-outline ki-plus fs-5"></i> Tambah</button>`;
            bindImageFallback(card, 'pos-product-image', 'fs-2x');
            card.querySelector('.add-product').addEventListener('click', async () => { await addToCart(product); focusScanner(); });
            grid.appendChild(card);
        });
    }

    async function addToCart(product) {
        const existing = cart.find(item => item.product_id === product.product_id && item.unit_id === product.unit_id && Number(item.selected_price) === Number(product.pricing.selected_price) && Number(item.discount_percent || 0) === 0);
        if (existing) {
            existing.quantity = Number(existing.quantity) + 1;
            await requote(existing);
        } else {
            cart.push({ ...product, quantity: 1, selected_price: product.pricing.selected_price, discount_percent: 0, manual_price: false, line_total: product.pricing.discounted_price, quote_version: 0 });
        }
        renderCart();
    }

    async function requote(item) {
        const version = ++item.quote_version;
        item.loading = true;
        renderCart();
        try {
            const data = await api(endpoints.quote, { method: 'POST', body: JSON.stringify({ product_id: item.product_id, customer_id: customerSelect.value || null, unit_id: item.unit_id, quantity: item.quantity, selected_price: item.manual_price ? item.selected_price : null, discount_percent: item.discount_percent || 0 }) });
            if (version !== item.quote_version) return;
            const quote = data.item;
            Object.assign(item, quote, { selected_price: quote.pricing.selected_price, line_total: Number(quote.quantity) * Number(quote.pricing.discounted_price), loading: false, quote_version: version });
        } catch (error) {
            if (version !== item.quote_version) return;
            item.loading = false; item.error = error.message;
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
            const units = (item.units || [{ id: item.unit_id, text: item.unit }]).map(unit => `<option value="${unit.id}" ${Number(unit.id) === Number(item.unit_id) ? 'selected' : ''}>${escapeHtml(unit.text)}</option>`).join('');
            const row = document.createElement('article');
            row.className = `pos-cart-item pos-price-${statusClass}`;
            row.innerHTML = `<div class="pos-cart-product d-flex gap-2">${image}<div class="min-w-0"><div class="fw-bold pos-product-name" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div><div class="text-muted fs-8 text-truncate">SKU ${escapeHtml(item.sku)}</div><div class="d-flex flex-wrap align-items-center gap-2 mt-1"><select class="form-select form-select-sm cart-unit" data-searchable="false">${units}</select><span class="fs-8 ${item.stock_sufficient ? 'text-muted' : 'text-danger fw-bold'}">Stok ${qty(item.stock)}</span></div></div></div><div class="pos-cart-qty"><span class="pos-cart-label">Qty</span><div class="pos-qty-control"><button type="button" class="btn btn-light cart-minus" aria-label="Kurangi qty"><i class="ki-outline ki-minus"></i></button><input type="number" min="0.0001" step="0.0001" class="form-control text-center cart-qty" value="${item.quantity}"><button type="button" class="btn btn-light cart-plus" aria-label="Tambah qty"><i class="ki-outline ki-plus"></i></button></div></div><div class="pos-cart-price"><span class="pos-cart-label">Harga</span><input type="text" inputmode="numeric" class="form-control form-control-sm pos-price-input cart-price" value="${money(item.selected_price)}"><div class="fs-8 mt-1"><strong>${escapeHtml(pricing.ring || '-')}</strong> / ${escapeHtml(pricing.status_label || 'Memuat')}</div><div class="text-muted fs-8">Batas ${money(pricing.minimum_price)} - ${money(pricing.maximum_price)}</div>${pricing.status_message ? `<div class="fs-8 ${pricing.approval_required ? 'text-danger fw-semibold' : 'text-muted'}">${escapeHtml(pricing.status_message)}</div>` : ''}${item.error ? `<div class="text-danger fs-8">${escapeHtml(item.error)}</div>` : ''}</div><div class="pos-cart-discount"><span class="pos-cart-label">Diskon</span><div class="input-group input-group-sm"><input type="number" min="0" max="100" step="0.01" class="form-control cart-discount" value="${item.discount_percent || 0}"><span class="input-group-text">%</span></div></div><div class="pos-cart-subtotal"><span class="pos-cart-label">Subtotal</span><strong class="text-nowrap">${item.loading ? '<span class="spinner-border spinner-border-sm"></span>' : money(item.line_total)}</strong></div><div class="pos-cart-action"><button type="button" class="btn btn-sm btn-icon btn-light-danger cart-remove" title="Hapus" aria-label="Hapus produk"><i class="ki-outline ki-trash"></i></button></div>`;
            bindImageFallback(row, 'pos-cart-image', 'fs-2');
            row.querySelector('.cart-minus').addEventListener('click', () => updateQuantity(item, Math.max(Number(item.quantity) - 1, 0)));
            row.querySelector('.cart-plus').addEventListener('click', () => updateQuantity(item, Number(item.quantity) + 1));
            row.querySelector('.cart-qty').addEventListener('change', event => updateQuantity(item, Number(event.target.value)));
            row.querySelector('.cart-price').addEventListener('focus', event => event.target.select());
            row.querySelector('.cart-price').addEventListener('input', event => event.target.value = money(rupiahValue(event.target.value)));
            row.querySelector('.cart-price').addEventListener('change', event => { item.manual_price = true; item.selected_price = rupiahValue(event.target.value); requote(item); });
            row.querySelector('.cart-discount').addEventListener('change', event => { item.discount_percent = Number(event.target.value); requote(item); });
            row.querySelector('.cart-unit').addEventListener('change', event => { item.unit_id = Number(event.target.value); item.manual_price = false; requote(item); });
            row.querySelector('.cart-remove').addEventListener('click', () => { cart.splice(index, 1); renderCart(); focusScanner(); });
            cartBody.appendChild(row);
        });
        updateTotals();
    }

    function updateQuantity(item, value) {
        if (value <= 0) { cart = cart.filter(row => row !== item); renderCart(); return; }
        item.quantity = value; requote(item);
    }

    function totals() {
        let subtotal = 0, grand = 0, totalQty = 0;
        cart.forEach(item => { const quantity = Number(item.quantity || 0); subtotal += quantity * Number(item.selected_price || 0); grand += Number(item.line_total || 0); totalQty += quantity; });
        return { subtotal, discount: Math.max(subtotal - grand, 0), grand, totalQty };
    }

    function blockingIssues() {
        const issues = [];
        cart.forEach(item => {
            if (item.loading || item.error) issues.push(`${item.name}: harga belum siap`);
            if (!item.stock_sufficient) issues.push(`${item.name}: stok tidak cukup`);
            if (item.pricing?.approval_required) issues.push(`${item.name}: membutuhkan approval harga`);
        });
        const requiredByProduct = new Map();
        cart.forEach(item => requiredByProduct.set(item.product_id, (requiredByProduct.get(item.product_id) || 0) + Number(item.quantity) * Number(item.unit_factor || 1)));
        requiredByProduct.forEach((required, productId) => { const sample = cart.find(item => item.product_id === productId); if (sample && required > Number(sample.stock_base)) issues.push(`${sample.name}: total qty melampaui stok cabang`); });
        return [...new Set(issues)];
    }

    function updateTotals() {
        const value = totals();
        document.getElementById('cart-item-label').textContent = `${cart.length} item / ${qty(value.totalQty)} qty`;
        document.getElementById('cart-subtotal').textContent = money(value.subtotal);
        document.getElementById('cart-discount').textContent = money(value.discount);
        document.getElementById('cart-grand-total').textContent = money(value.grand);
        const issues = blockingIssues();
        const alert = document.getElementById('cart-alert');
        alert.classList.toggle('d-none', !issues.length);
        alert.textContent = issues.join(' | ');
        document.getElementById('hold-cart').disabled = cart.length === 0;
        document.getElementById('open-payment').disabled = cart.length === 0;
    }

    async function requoteAll() {
        await Promise.all(cart.map(item => { item.manual_price = item.manual_price === true; return requote(item); }));
        renderCart();
    }

    function renderPaymentRows() {
        const container = document.getElementById('payment-rows'); container.innerHTML = '';
        payments.forEach((payment, index) => {
            const row = document.createElement('div'); row.className = 'pos-payment-row border rounded p-3';
            const options = Object.entries(paymentMethods).map(([value, label]) => `<option value="${value}" ${payment.method === value ? 'selected' : ''}>${escapeHtml(label)}</option>`).join('');
            row.innerHTML = `<div class="pos-payment-method"><label class="pos-cart-label">Metode</label><select class="form-select payment-method" data-searchable="false">${options}</select></div><div class="pos-payment-amount-wrap"><label class="pos-cart-label">Jumlah Dibayarkan</label><input type="text" inputmode="numeric" class="form-control payment-amount" value="${money(payment.amount)}" placeholder="Rp 0" autocomplete="off"></div><div class="pos-payment-reference"><label class="pos-cart-label">Referensi</label><input class="form-control payment-reference" value="${escapeHtml(payment.reference_no || '')}" placeholder="Opsional"></div><div class="pos-payment-remove"><button type="button" class="btn btn-icon btn-light-danger remove-payment" aria-label="Hapus"><i class="ki-outline ki-trash"></i></button></div>`;
            row.querySelector('.payment-method').addEventListener('change', event => { payment.method = event.target.value; renderQuickCash(); updatePaymentSummary(); });
            row.querySelector('.payment-amount').addEventListener('focus', event => event.target.select());
            row.querySelector('.payment-amount').addEventListener('input', event => { payment.amount = rupiahValue(event.target.value); event.target.value = money(payment.amount); updatePaymentSummary(); });
            row.querySelector('.payment-reference').addEventListener('input', event => payment.reference_no = event.target.value);
            row.querySelector('.remove-payment').addEventListener('click', () => { if (payments.length > 1) payments.splice(index, 1); renderPaymentRows(); updatePaymentSummary(); });
            container.appendChild(row);
        });
        renderQuickCash();
    }

    function renderQuickCash() {
        const grand = totals().grand;
        const container = document.getElementById('cash-quick-buttons');
        if (!payments.some(payment => payment.method === 'cash')) { container.innerHTML = ''; return; }
        const values = [{ amount: grand, label: 'Uang Pas' }, ...[10000, 20000, 50000, 100000, 200000, 1000000]
            .filter(amount => amount !== grand)
            .map(amount => ({ amount, label: money(amount) }))];
        container.innerHTML = values.map((entry, index) => `<button type="button" class="btn btn-light-primary quick-cash" data-amount="${entry.amount}" ${index > 0 && entry.amount < grand ? 'disabled' : ''}>${entry.label}</button>`).join('');
        document.querySelectorAll('.quick-cash').forEach(button => button.addEventListener('click', () => {
            let cash = payments.find(row => row.method === 'cash');
            if (!cash) { cash = { method: 'cash', amount: 0, reference_no: '' }; payments.push(cash); }
            cash.amount = Number(button.dataset.amount); renderPaymentRows(); updatePaymentSummary();
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
        const issues = blockingIssues();
        const warning = document.getElementById('payment-price-warning');
        warning.classList.toggle('d-none', !issues.length); warning.textContent = issues.join(' | ');
        document.getElementById('confirm-payment').disabled = shortage > 0 || issues.length > 0 || payments.some(payment => Number(payment.amount || 0) <= 0);
    }

    function openPayment() {
        if (!cart.length) return;
        const value = totals();
        payments = [{ method: 'cash', amount: value.grand, reference_no: '' }];
        document.getElementById('payment-grand-total').textContent = money(value.grand);
        document.getElementById('payment-item-count').textContent = `${cart.length} item / ${qty(value.totalQty)} qty`;
        document.getElementById('payment-customer').textContent = customerName();
        renderPaymentRows(); updatePaymentSummary(); paymentModal.show();
    }

    function salePayload() {
        return {
            branch_id: branchId,
            customer_id: customerSelect.value || null,
            idempotency_key: idempotencyKey,
            items: cart.map(item => ({ product_id: item.product_id, unit_id: item.unit_id, warehouse_location_id: item.warehouse_location_id || null, quantity: item.quantity, selected_price: item.selected_price, discount_percent: item.discount_percent || 0 })),
            payments: payments.map(payment => ({ method: payment.method, amount: payment.amount, reference_no: payment.reference_no || null })),
        };
    }

    async function checkout() {
        const button = document.getElementById('confirm-payment'); button.disabled = true; button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
        try {
            const data = await api(endpoints.checkout, { method: 'POST', body: JSON.stringify(salePayload()) });
            paymentModal.hide();
            document.getElementById('success-number').textContent = data.sale.number;
            document.getElementById('success-total').textContent = money(data.sale.grand_total);
            document.getElementById('success-change').textContent = money(data.sale.change);
            receiptPrintUrl = data.sale.print_url;
            document.getElementById('success-detail').href = data.sale.show_url;
            successModal.show();
        } catch (error) {
            window.Swal ? Swal.fire('Checkout gagal', error.message, 'error') : alert(error.message);
            button.disabled = false;
        } finally { button.innerHTML = '<i class="ki-outline ki-check fs-4 me-2"></i>SELESAIKAN TRANSAKSI'; updatePaymentSummary(); }
    }

    async function holdCart() {
        if (!cart.length) return;
        const button = document.getElementById('hold-cart'); button.disabled = true;
        try {
            const data = await api(endpoints.hold, { method: 'POST', body: JSON.stringify({ branch_id: branchId, customer_id: customerSelect.value || null, items: salePayload().items }) });
            clearTransaction();
            document.getElementById('hold-count').textContent = Number(document.getElementById('hold-count').textContent || 0) + 1;
            window.Swal ? Swal.fire({ text: `${data.hold.number} berhasil ditahan.`, icon: 'success', timer: 1400, showConfirmButton: false }) : null;
        } catch (error) { window.Swal ? Swal.fire('Hold gagal', error.message, 'error') : alert(error.message); }
        finally { button.disabled = false; focusScanner(); }
    }

    async function openHolds() {
        const container = document.getElementById('holds-list'); container.innerHTML = '<div class="text-center py-8"><span class="spinner-border"></span></div>'; holdsModal.show();
        try {
            const data = await api(endpoints.holds);
            document.getElementById('hold-count').textContent = data.count;
            if (!data.results.length) { container.innerHTML = '<div class="text-center text-muted py-8">Belum ada transaksi hold aktif.</div>'; return; }
            container.innerHTML = data.results.map(hold => `<div class="border rounded p-3 mb-3 d-flex align-items-center justify-content-between gap-3"><div><strong>${escapeHtml(hold.number)}</strong><div class="text-muted fs-7">${escapeHtml(hold.time)} / ${escapeHtml(hold.customer)} / ${hold.item_count} item / ${money(hold.estimated_total)}</div></div><button type="button" class="btn btn-sm btn-primary resume-hold" data-url="${escapeHtml(hold.resume_url)}">Resume</button></div>`).join('');
            container.querySelectorAll('.resume-hold').forEach(button => button.addEventListener('click', async () => {
                button.disabled = true;
                try { const response = await api(button.dataset.url, { method: 'POST', body: '{}' }); holdsModal.hide(); await restoreSnapshot(response.cart); document.getElementById('hold-count').textContent = Math.max(Number(document.getElementById('hold-count').textContent) - 1, 0); }
                catch (error) { window.Swal ? Swal.fire('Resume gagal', error.message, 'error') : alert(error.message); }
            }));
        } catch (error) { container.innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`; }
    }

    async function restoreSnapshot(snapshot) {
        customerSelect.value = snapshot?.customer_id || '';
        if (window.jQuery) window.jQuery(customerSelect).trigger('change.select2');
        cart = (snapshot?.items || (Array.isArray(snapshot) ? snapshot : [])).map(item => ({ ...item, product_id: Number(item.product_id), unit_id: Number(item.unit_id), quantity: Number(item.quantity || item.qty || 1), selected_price: Number(item.selected_price || 0), discount_percent: Number(item.discount_percent || 0), manual_price: true, line_total: Number(item.line_total || 0), quote_version: 0 }));
        await requoteAll(); focusScanner();
    }

    function clearTransaction() {
        cart = []; payments = []; idempotencyKey = makeUuid(); customerSelect.value = ''; scanner.value = '';
        if (window.jQuery) window.jQuery(customerSelect).trigger('change.select2');
        document.getElementById('temporary-number').textContent = `BARU-${new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`;
        renderCart(); loadProducts(true); focusScanner();
    }

    function startNewTransaction() {
        successModal.hide();
        receiptPrintUrl = null;
        clearTransaction();
    }

    function printReceipt() {
        if (!receiptPrintUrl) return;
        const printWindow = window.open(receiptPrintUrl, 'pos-receipt-print', 'popup=yes,width=420,height=720');
        if (!printWindow) {
            showToast('Popup cetak diblokir browser. Izinkan popup untuk mencetak struk.');
            return;
        }
        startNewTransaction();
    }

    scanner.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => loadProducts(true), 260); });
    scanner.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); clearTimeout(searchTimer); loadProducts(true, true); } });
    document.getElementById('brand-filter').addEventListener('change', () => loadProducts(true));
    document.getElementById('stock-filter').addEventListener('change', () => loadProducts(true));
    document.querySelectorAll('.pos-category').forEach(button => button.addEventListener('click', () => { document.querySelectorAll('.pos-category').forEach(item => { item.classList.remove('btn-primary', 'active'); item.classList.add('btn-light'); }); button.classList.remove('btn-light'); button.classList.add('btn-primary', 'active'); selectedCategory = button.dataset.category; loadProducts(true); }));
    customerSelect.addEventListener('change', () => { requoteAll(); loadProducts(true); });
    document.getElementById('load-more-products').addEventListener('click', () => { if (hasMoreProducts) { productPage++; loadProducts(false); } });
    document.getElementById('open-payment').addEventListener('click', openPayment);
    document.getElementById('add-payment').addEventListener('click', () => { payments.push({ method: 'qris', amount: 0, reference_no: '' }); renderPaymentRows(); updatePaymentSummary(); });
    document.getElementById('confirm-payment').addEventListener('click', checkout);
    document.getElementById('hold-cart').addEventListener('click', holdCart);
    document.getElementById('open-holds').addEventListener('click', openHolds);
    document.getElementById('new-transaction').addEventListener('click', startNewTransaction);
    document.getElementById('success-print').addEventListener('click', printReceipt);
    document.getElementById('payment-modal').addEventListener('shown.bs.modal', () => document.querySelector('.payment-amount')?.focus());
    document.querySelectorAll('.modal').forEach(modal => modal.addEventListener('hidden.bs.modal', focusScanner));
    document.getElementById('pos-sidebar-toggle').addEventListener('click', event => {
        const collapsed = document.body.getAttribute('data-kt-app-sidebar-collapse') === 'on';
        if (collapsed) document.body.removeAttribute('data-kt-app-sidebar-collapse');
        else document.body.setAttribute('data-kt-app-sidebar-collapse', 'on');
        event.currentTarget.setAttribute('aria-expanded', collapsed ? 'true' : 'false');
        event.currentTarget.querySelector('i').className = `ki-outline ${collapsed ? 'ki-arrow-left' : 'ki-arrow-right'} fs-3`;
    });
    window.addEventListener('focus', () => focusScanner());
    document.addEventListener('keydown', event => {
        if (event.key === 'F2') { event.preventDefault(); focusScanner(); }
        if (event.key === 'F4') { event.preventDefault(); if (window.jQuery && window.jQuery(customerSelect).hasClass('select2-hidden-accessible')) window.jQuery(customerSelect).select2('open'); else customerSelect.focus(); }
        if (event.key === 'F6') { event.preventDefault(); cart.length ? holdCart() : openHolds(); }
        if (event.key === 'F8') { event.preventDefault(); openPayment(); }
        if (event.key === 'Escape' && !document.querySelector('.modal.show')) { scanner.value = ''; loadProducts(true); focusScanner(); }
        if (event.key === 'Enter' && document.getElementById('payment-modal').classList.contains('show') && !document.getElementById('confirm-payment').disabled) { event.preventDefault(); checkout(); }
    });

    document.getElementById('temporary-number').textContent = `BARU-${new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`;
    renderCart(); loadProducts(true); focusScanner(true);
    if (resumeCart) restoreSnapshot(resumeCart);
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeScannerFirstPos, { once: true });
else initializeScannerFirstPos();
</script>
@endpush
@endif
