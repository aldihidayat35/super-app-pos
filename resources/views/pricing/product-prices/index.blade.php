@extends('layouts.metronic.app')

@section('title', 'Harga Produk per Ring/Cabang - ' . config('app.name'))
@section('page_title', 'Harga Produk per Ring/Cabang')

@section('toolbar_actions')
    <a href="{{ route('pricing.product-prices.export', request()->query()) }}" class="btn btn-light-success"><i class="ki-outline ki-file-down"></i> Export Harga</a>
@endsection

@push('styles')
<style>
    /* ── Modern Product Picker (Multi-Select Dropdown with Search) ── */

    :root {
        --pp-primary-rgb: var(--bs-primary-rgb, 13, 110, 253);
    }

    /* Container wrapper */
    .pp-picker-container { position: relative; width: 100%; z-index: 1050; }

    /* Trigger bar (visible element) */
    .pp-trigger {
        position: relative; width: 100%; min-height: 48px;
        background: var(--bs-gray-100); border: 1px solid var(--bs-gray-300);
        border-radius: .5rem; padding: .35rem .75rem;
        display: flex; flex-wrap: wrap; align-items: center; gap: .3rem;
        cursor: text; transition: border-color .2s, box-shadow .2s, background .15s;
    }
    .pp-trigger:hover { border-color: var(--bs-gray-400); background: #fff; }
    .pp-trigger:focus-within,
    .pp-trigger.open { border-color: var(--bs-primary); background: #fff;
        box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .15); }

    /* Arrow toggle icon */
    .pp-arrow {
        position: absolute; right: .75rem; top: 50%; transform: translateY(-50%);
        color: var(--bs-gray-500); transition: transform .2s, color .2s;
        pointer-events: none; z-index: 2;
    }
    .pp-trigger.open .pp-arrow { transform: translateY(-50%) rotate(180deg); color: var(--bs-primary); }

    /* Selected chip */
    .pp-chip {
        display: inline-flex; align-items: center; gap: .25rem;
        background: rgba(var(--bs-primary-rgb), .12); color: var(--bs-primary);
        border: 1px solid rgba(var(--bs-primary-rgb), .25);
        border-radius: .375rem; padding: .2rem .4rem .2rem .6rem;
        font-size: .8rem; font-weight: 500; white-space: nowrap;
        line-height: 1.4; animation: ppChipIn .2s ease;
    }
    @keyframes ppChipIn { from { opacity: 0; transform: scale(.9); } to { opacity: 1; transform: scale(1); } }
    .pp-chip-remove {
        background: none; border: none; color: inherit; cursor: pointer;
        font-size: .9rem; line-height: 1; padding: 0 1px; opacity: .6;
        transition: opacity .12s;
    }
    .pp-chip-remove:hover { opacity: 1; color: var(--bs-danger); }

    /* Native select (hidden but form-bound) */
    .pp-native-select { position: absolute; clip: rect(0,0,0,0); pointer-events: none; }

    /* Placeholder */
    .pp-placeholder { color: var(--bs-gray-400); font-size: .88rem; pointer-events: none; user-select: none; flex: 1; }

    /* Dropdown panel */
    .pp-dropdown {
        position: absolute; top: calc(100% + 6px); left: 0; right: 0;
        background: #fff; border: 1px solid var(--bs-gray-300);
        border-radius: .5rem; box-shadow: 0 .5rem 1rem rgba(0,0,0,.12);
        z-index: 1060; display: none; overflow: hidden;
    }
    .pp-dropdown.show { display: block; animation: ppDropIn .15s ease; }
    @keyframes ppDropIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }

    /* Header with search */
    .pp-dropdown-header { padding: .6rem .75rem; border-bottom: 1px solid var(--bs-gray-200); position: sticky; top: 0; background: #fff; z-index: 1; }
    .pp-dropdown-search {
        width: 100%; border: 1px solid var(--bs-gray-300); border-radius: .4rem;
        padding: .5rem .75rem; font-size: .88rem; outline: none;
        transition: border-color .2s, box-shadow .2s;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%236c757d'%3e%3cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85zm-5.242.656a5 5 0 1 1 0-10 5 5 0 0 1 0 10z'/%3e%3c/svg%3e");
        background-repeat: no-repeat; background-position: right .65rem center; background-size: 1rem;
        padding-right: 2rem;
    }
    .pp-dropdown-search:focus { border-color: var(--bs-primary); box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .15); }

    /* List container */
    .pp-list-wrap { max-height: 340px; overflow-y: auto; overscroll-behavior: contain; }
    .pp-list-wrap::-webkit-scrollbar { width: 6px; }
    .pp-list-wrap::-webkit-scrollbar-thumb { background: var(--bs-gray-300); border-radius: 3px; }
    .pp-list-wrap::-webkit-scrollbar-track { background: var(--bs-gray-100); }

    /* Individual item */
    .pp-item {
        display: flex; align-items: center; gap: .6rem;
        padding: .6rem .75rem; cursor: pointer; transition: background .12s;
        border-bottom: 1px solid var(--bs-gray-100);
    }
    .pp-item:last-child { border-bottom: none; }
    .pp-item:hover { background: var(--bs-gray-50); }
    .pp-item.selected { background: rgba(var(--bs-primary-rgb), .06); }
    .pp-item.selected:hover { background: rgba(var(--bs-primary-rgb), .1); }
    .pp-item input[type="checkbox"] {
        accent-color: var(--bs-primary); width: 18px; height: 18px; flex-shrink: 0;
        cursor: pointer;
    }
    .pp-item-info { flex: 1; min-width: 0; }
    .pp-item-sku { font-size: .72rem; color: var(--bs-gray-500); font-family: 'SF Mono', 'Consolas', monospace; letter-spacing: .02em; }
    .pp-item-name { font-size: .88rem; font-weight: 500; color: var(--bs-body-color); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pp-item-cat { font-size: .7rem; color: var(--bs-gray-400); }
    .pp-item.selected .pp-item-name { color: var(--bs-primary); font-weight: 600; }
    .pp-item mark { background: rgba(var(--bs-primary-rgb), .2); color: inherit; padding: 0 1px; border-radius: 2px; }
    .pp-item-check { color: var(--bs-primary); font-size: 1.2rem; font-weight: 700; opacity: 0; flex-shrink: 0; transition: opacity .12s; }
    .pp-item.selected .pp-item-check { opacity: 1; }

    /* No results */
    .pp-no-results { padding: 2rem 1rem; text-align: center; color: var(--bs-gray-400); }
    .pp-no-results i { display: block; font-size: 2.5rem; margin-bottom: .5rem; color: var(--bs-gray-300); }
    .pp-no-results small { font-size: .82rem; display: block; }

    /* Loading state */
    .pp-loading { padding: 1rem; text-align: center; color: var(--bs-gray-500); }
    .pp-loading .spinner-border { width: 1.2rem; height: 1.2rem; }

    /* Footer */
    .pp-footer {
        padding: .5rem .75rem; border-top: 1px solid var(--bs-gray-200);
        display: flex; align-items: center; justify-content: space-between;
        font-size: .78rem; color: var(--bs-gray-500); background: var(--bs-gray-50);
    }
    .pp-footer-count { font-weight: 500; }
    .pp-footer-count strong { color: var(--bs-primary); font-weight: 700; }
    .pp-footer-actions { display: flex; gap: .35rem; }
    .pp-footer-btn {
        background: none; border: 1px solid var(--bs-gray-300); border-radius: .35rem;
        padding: .2rem .55rem; font-size: .72rem; cursor: pointer;
        color: var(--bs-gray-600); transition: all .15s; line-height: 1;
    }
    .pp-footer-btn:hover:not(:disabled) { border-color: var(--bs-danger); color: var(--bs-danger); background: rgba(220,53,69,.05); }
    .pp-footer-btn:disabled { opacity: .4; cursor: not-allowed; }
    .pp-footer-btn.btn-select-all { border-color: var(--bs-primary); color: var(--bs-primary); }
    .pp-footer-btn.btn-select-all:hover:not(:disabled) { background: rgba(var(--bs-primary-rgb), .08); }

    /* Counter below trigger */
    .pp-counter {
        display: inline-flex; align-items: center; gap: .4rem;
        border: 1px solid var(--bs-gray-200); border-radius: .5rem;
        background: var(--bs-gray-100); padding: .3rem .75rem; font-size: .78rem;
        color: var(--bs-gray-500); margin-top: .5rem; transition: all .2s ease;
    }
    .pp-counter.has-items {
        background: rgba(var(--bs-primary-rgb), .08); border-color: rgba(var(--bs-primary-rgb), .2);
        color: var(--bs-primary);
    }
    .pp-counter-dot {
        background: var(--bs-gray-300); border-radius: 1rem;
        color: #fff; font-size: .7rem; font-weight: 700;
        min-width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;
    }
    .pp-counter.has-items .pp-counter-dot { background: var(--bs-primary); }

    /* Responsive */
    @media (max-width: 575.98px) {
        .pp-trigger { padding: .3rem .5rem; min-height: 44px; }
        .pp-dropdown { right: -1rem; left: -1rem; border-radius: .6rem; }
        .pp-item { padding: .5rem .6rem; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const wrapper = document.querySelector('[data-product-picker="true"]');
        if (!wrapper) return;

        const native  = wrapper.querySelector('.pp-native-select');
        const trigger = wrapper.querySelector('.pp-trigger');
        const drop    = wrapper.querySelector('.pp-dropdown');
        const dropSearch = drop?.querySelector('.pp-dropdown-search');
        const listWrap = drop?.querySelector('.pp-list-wrap');
        const listEl   = listWrap?.querySelector('.pp-list-inner');
        const footer   = drop?.querySelector('.pp-footer');
        const counter  = wrapper?.querySelector('.pp-counter');

        if (!native || !trigger || !drop) return;

        let allProducts = [];
        let displayedProducts = [];
        let isLoading = false;
        let debounceTimer = null;

        // ── Load all products initially ──
        loadAllProducts();

        function loadAllProducts() {
            native.querySelectorAll('option').forEach(opt => {
                allProducts.push({
                    id: opt.value,
                    sku: opt.dataset.sku || '',
                    name: opt.dataset.name || opt.textContent.replace(opt.dataset.sku, '').replace(/^[\s—]+/,''),
                    category: opt.dataset.category || '',
                });
            });

            // Build list from allProducts
            buildProductList(allProducts);
        }

        function buildProductList(products) {
            if (!listEl) return;
            displayedProducts = products;
            let html = '';
            products.forEach(p => {
                const isSelected = native.querySelector(`option[value="${p.id}"]`)?.selected;
                html += `<div class="pp-item${isSelected ? ' selected' : ''}" data-id="${p.id}">
                    <input type="checkbox" ${isSelected ? 'checked' : ''} value="${p.id}" aria-label="${p.sku}">
                    <div class="pp-item-info">
                        <div class="pp-item-sku">${escapeHtml(p.sku)}</div>
                        <div class="pp-item-name">${escapeHtml(p.name)}${p.category ? ` <span class="pp-item-cat">(${escapeHtml(p.category)})</span>` : ''}</div>
                    </div>
                    <span class="pp-item-check">✓</span>
                </div>`;
            });
            listEl.innerHTML = html;

            // Attach click to each item
            listEl.querySelectorAll('.pp-item').forEach(item => {
                item.addEventListener('click', function (e) {
                    if (e.target.type === 'checkbox') return;
                    const cb = item.querySelector('input[type="checkbox"]');
                    cb.checked = !cb.checked;
                    cb.dispatchEvent(new Event('change'));
                });
            });
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }

        function highlightMatch(text, query) {
            if (!query || !text) return escapeHtml(text);
            const escaped = escapeHtml(text);
            const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
            return escaped.replace(regex, '<mark>$1</mark>');
        }

        // ── Filter & render list based on search ──
        function filterAndRender(query) {
            if (!listEl) return;
            const q = (query || '').trim().toLowerCase();
            let filtered = allProducts;
            
            if (q) {
                filtered = allProducts.filter(p => {
                    return p.name.toLowerCase().includes(q) || 
                           p.sku.toLowerCase().includes(q) ||
                           (p.category && p.category.toLowerCase().includes(q));
                });
            }

            let html = '';
            filtered.forEach(p => {
                const isSelected = native.querySelector(`option[value="${p.id}"]`)?.selected;
                const displayName = q ? highlightMatch(p.name, q) : escapeHtml(p.name);
                const displaySku = q ? highlightMatch(p.sku, q) : escapeHtml(p.sku);
                html += `<div class="pp-item${isSelected ? ' selected' : ''}" data-id="${p.id}">
                    <input type="checkbox" ${isSelected ? 'checked' : ''} value="${p.id}" aria-label="${displaySku}">
                    <div class="pp-item-info">
                        <div class="pp-item-sku">${displaySku}</div>
                        <div class="pp-item-name">${displayName}${p.category ? ` <span class="pp-item-cat">(${escapeHtml(p.category)})</span>` : ''}</div>
                    </div>
                    <span class="pp-item-check">✓</span>
                </div>`;
            });

            if (filtered.length === 0) {
                html = `<div class="pp-no-results"><i class="ki-outline ki-not-found"></i><small>Tidak ada produk ditemukan</small></div>`;
            }

            listEl.innerHTML = html;

            // Attach click handlers
            listEl.querySelectorAll('.pp-item').forEach(item => {
                item.addEventListener('click', function (e) {
                    if (e.target.type === 'checkbox') return;
                    const cb = item.querySelector('input[type="checkbox"]');
                    cb.checked = !cb.checked;
                    cb.dispatchEvent(new Event('change'));
                });
            });
        }

        // ── Render chips ──
        function renderChips() {
            trigger.querySelectorAll('.pp-chip').forEach(c => c.remove());
            const selected = getSelectedValues();
            if (selected.length === 0) return;
            selected.forEach(val => {
                const p = allProducts.find(p => p.id == val);
                if (!p) return;
                const chip = document.createElement('span');
                chip.className = 'pp-chip';
                chip.innerHTML = `<small class="pp-item-sku">${escapeHtml(p.sku)}</small>${escapeHtml(p.name)}<button type="button" class="pp-chip-remove" data-val="${val}" title="Hapus">&times;</button>`;
                trigger.insertBefore(chip, trigger.querySelector('.pp-placeholder') || null);
            });
            // Remove placeholder
            const ph = trigger.querySelector('.pp-placeholder');
            if (ph && selected.length > 0) ph.remove();

            // Attach remove handlers
            trigger.querySelectorAll('.pp-chip-remove').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    const v = this.dataset.val;
                    const opt = native.querySelector(`option[value="${v}"]`);
                    if (opt) opt.selected = false;
                    native.dispatchEvent(new Event('change'));
                });
            });
        }

        function getSelectedValues() {
            return Array.from(native.selectedOptions).map(o => o.value);
        }

        // ── Render footer ──
        function renderFooter() {
            if (!footer) return;
            const sel = getSelectedValues().length;
            const total = allProducts.length;
            footer.innerHTML = `
                <span class="pp-footer-count"><strong>${sel}</strong> / ${total} produk terpilih</span>
                <div class="pp-footer-actions">
                    <button type="button" class="pp-footer-btn btn-select-all" ${sel >= total ? 'disabled' : ''}>Pilih Semua</button>
                    <button type="button" class="pp-footer-btn pp-btn-deselect-all" ${sel===0?'disabled':''}>Hapus Semua</button>
                    <button type="button" class="pp-footer-btn pp-btn-reset">Reset</button>
                </div>
            `;
            footer.querySelector('.pp-btn-deselect-all')?.addEventListener('click', () => {
                native.querySelectorAll('option').forEach(o => o.selected = false);
                native.dispatchEvent(new Event('change'));
            });
            footer.querySelector('.pp-btn-reset')?.addEventListener('click', () => {
                native.querySelectorAll('option').forEach(o => {
                    o.selected = o.defaultSelected;
                });
                native.dispatchEvent(new Event('change'));
            });
            footer.querySelector('.btn-select-all')?.addEventListener('click', () => {
                native.querySelectorAll('option').forEach(o => o.selected = true);
                native.dispatchEvent(new Event('change'));
            });
        }

        // ── Update counter badge ──
        function updateCounter() {
            if (!counter) return;
            const sel = getSelectedValues().length;
            counter.classList.toggle('has-items', sel > 0);
            counter.innerHTML = `<span class="pp-counter-dot">${sel}</span><span>${sel === 0 ? 'Belum ada produk dipilih' : sel + ' produk terpilih'}</span>`;
        }

        // ── Open / Close ──
        function openDropdown() {
            drop.classList.add('show');
            trigger.classList.add('open');
            dropSearch.value = '';
            filterAndRender('');
            setTimeout(() => dropSearch?.focus(), 50);
        }
        function closeDropdown() {
            drop.classList.remove('show');
            trigger.classList.remove('open');
        }

        // ── Events ──
        trigger.addEventListener('click', function (e) {
            if (e.target.closest('.pp-chip-remove')) return;
            if (drop.classList.contains('show')) {
                closeDropdown();
            } else {
                openDropdown();
            }
        });

        native.addEventListener('change', function () {
            renderChips();
            renderFooter();
            updateCounter();
            // Update checkbox states in list
            const selected = getSelectedValues();
            listEl?.querySelectorAll('.pp-item').forEach(item => {
                const id = item.dataset.id;
                const cb = item.querySelector('input[type="checkbox"]');
                const isSelected = selected.includes(id);
                item.classList.toggle('selected', isSelected);
                if (cb) cb.checked = isSelected;
            });
        });

        listWrap?.addEventListener('change', function (e) {
            if (e.target.type === 'checkbox') {
                const val = e.target.value;
                const opt = native.querySelector(`option[value="${val}"]`);
                if (opt) opt.selected = e.target.checked;
                native.dispatchEvent(new Event('change'));
                // Stay open
                if (drop.classList.contains('show')) dropSearch?.focus();
            }
        });

        dropSearch?.addEventListener('input', function () {
            filterAndRender(this.value);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && drop.classList.contains('show')) {
                closeDropdown();
            }
        });

        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) closeDropdown();
        });

        // ── Init ──
        renderChips();
        renderFooter();
        updateCounter();
    });
})();
</script>
@endpush

@section('content')
    @php($canSensitive = auth()->user()?->can('margins.view_sensitive'))
    <x-metronic.card title="Input Ring Harga">
        <form method="POST" action="{{ route('pricing.product-prices.store') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-4">
                <x-metronic.form-group name="product_ids" label="Produk" required help="Klik untuk membuka dropdown, cari, dan pilih beberapa produk.">
                    <div class="pp-picker-container" data-product-picker="true">
                        <!-- Hidden native select (form-bound) -->
                        <select id="product_ids" name="product_ids[]"
                            class="pp-native-select @error('product_ids') is-invalid @enderror"
                            multiple required>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" 
                                    data-sku="{{ $product->sku }}" 
                                    data-name="{{ $product->name }}"
                                    data-category="{{ $product->category?->name ?? '' }}"
                                    @selected(in_array((string) $product->id, array_map('strval', old('product_ids', [])), true))>
                                    {{ $product->sku }} — {{ $product->name }}
                                </option>
                            @endforeach
                        </select>

                        <!-- Visible trigger bar -->
                        <div class="pp-trigger">
                            <span class="pp-placeholder">Cari dan pilih produk...</span>
                            <span class="pp-arrow"><i class="ki-outline ki-square-down fs-6"></i></span>
                        </div>

                        <!-- Dropdown panel -->
                        <div class="pp-dropdown">
                            <div class="pp-dropdown-header">
                                <input type="text" class="pp-dropdown-search" placeholder="Cari berdasarkan SKU, nama, atau kategori...">
                            </div>
                            <div class="pp-list-wrap">
                                <div class="pp-list-inner"></div>
                            </div>
                            <div class="pp-footer"></div>
                        </div>

                        <!-- Counter badge -->
                        <div class="pp-counter">
                            <span class="pp-counter-dot">0</span>
                            <span>Belum ada produk dipilih</span>
                        </div>
                    </div>
                </x-metronic.form-group>
            </div>
            <div class="col-md-2"><x-metronic.form-group name="channel" label="Channel" required><select name="channel" class="form-select"><option value="retail">Retail</option><option value="b2b">B2B</option><option value="pos">POS</option><option value="all">Semua</option></select></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="price_ring" label="Ring" required><input name="price_ring" value="ring_1" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-4"><x-metronic.form-group name="branch_id" label="Cabang"><select name="branch_id" class="form-select"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="customer_category" label="Kategori"><input name="customer_category" class="form-control" placeholder="grosir"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="min_price" label="Harga Min"><input type="number" step="0.01" min="0" name="min_price" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="recommended_price" label="Harga Rekomendasi" required><input type="number" step="0.01" min="0" name="recommended_price" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="max_price" label="Harga Maks"><input type="number" step="0.01" min="0" name="max_price" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="minimum_qty" label="Min Qty"><input type="number" step="1" min="0" name="minimum_qty" value="1" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="priority" label="Prioritas"><input type="number" min="1" name="priority" value="100" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="starts_at" label="Mulai"><input type="date" name="starts_at" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="ends_at" label="Selesai"><input type="date" name="ends_at" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-12"><x-metronic.form-group name="notes" label="Alasan/Catatan"><textarea name="notes" rows="2" class="form-control"></textarea></x-metronic.form-group></div>
            <div class="col-md-12"><button class="btn btn-primary" @cannot('prices.update') disabled @endcannot>Simpan Harga</button></div>
        </form>
    </x-metronic.card>

    <x-metronic.card title="Daftar Harga Produk" class="mt-6">
        <form method="GET" class="row g-3 mb-6">
            <div class="col-md-5"><select name="product_id" class="form-select"><option value="">Semua produk</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(($filters['product_id'] ?? '') == $product->id)>{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="channel" class="form-select"><option value="">Semua channel</option><option value="retail" @selected(($filters['channel'] ?? '') === 'retail')>Retail</option><option value="b2b" @selected(($filters['channel'] ?? '') === 'b2b')>B2B</option><option value="pos" @selected(($filters['channel'] ?? '') === 'pos')>POS</option></select></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th>@if($canSensitive)<th>HPP</th>@endif<th>Scope</th><th>Ring</th><th>Min / Rekomendasi / Maks</th><th>Periode</th><th>Status</th><th>Warning</th></tr></thead>
                <tbody>
                @forelse($prices as $price)
                    @php($resolved = $resolver->resolve($price->product, branch: $price->branch, channel: $price->channel === 'all' ? 'retail' : $price->channel, user: auth()->user(), requestedPrice: $price->recommended_price))
                    <tr>
                        <td>{{ $price->product?->sku }}<div class="text-muted">{{ $price->product?->name }}</div></td>
                        @if($canSensitive)<td>Rp {{ number_format((float) $resolved['hpp_base'], 0, ',', '.') }}</td>@endif
                        <td>{{ strtoupper($price->channel) }}<div class="text-muted">{{ $price->branch?->name ?? 'Semua cabang' }} · {{ $price->customer_category ?: 'Semua kategori' }}</div></td>
                        <td>{{ $price->price_ring }}<div class="text-muted">Prioritas {{ $price->priority }}</div></td>
                        <td>Rp {{ number_format((float) $price->min_price, 0, ',', '.') }} / <strong>Rp {{ number_format((float) $price->recommended_price, 0, ',', '.') }}</strong> / Rp {{ number_format((float) $price->max_price, 0, ',', '.') }}</td>
                        <td>{{ $price->starts_at?->format('d/m/Y') ?? 'Sekarang' }} - {{ $price->ends_at?->format('d/m/Y') ?? 'Tanpa batas' }}</td>
                        <td><x-metronic.status-badge :status="$price->status" /></td>
                        <td>@if($resolved['approval_required'])<span class="badge badge-light-danger">{{ implode(', ', $resolved['approval_reasons']) }}</span>@else<span class="badge badge-light-success">Aman</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $canSensitive ? 8 : 7 }}"><x-metronic.empty-state title="Belum ada harga produk" description="Tambahkan ring harga untuk retail, POS, atau B2B." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $prices->links() }}
    </x-metronic.card>
@endsection
