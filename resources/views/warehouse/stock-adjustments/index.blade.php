@extends('layouts.metronic.app')

@section('title', 'Koreksi Stok - ' . config('app.name'))
@section('page_title', 'Koreksi Stok Langsung')

@push('styles')
<style>
    .product-card-label { transition: all 0.2s; }
    .product-card-label.selected { border-color: var(--bs-primary) !important; background-color: rgba(var(--bs-primary-rgb), 0.05) !important; box-shadow: 0 0 0 1px var(--bs-primary); }
</style>
@endpush

@section('page_guide')
    <x-metronic.page-guide id="stock-direct-adjustment" title="Panduan Koreksi Stok Langsung">
        <x-slot:function><p>Mencatat stok fisik akhir per produk secara resmi tanpa mengubah HPP.</p></x-slot:function>
        <x-slot:workflow><p>Kepala lokasi membuat draft, mengajukan, lalu kepala lokasi lain atau Super Admin menyetujui.</p></x-slot:workflow>
        <x-slot:warnings><div class="alert alert-warning mb-0">Pembuat tidak dapat menyetujui sendiri. Stok baru berubah setelah persetujuan.</div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    @error('adjustment')<div class="alert alert-danger">{{ $message }}</div>@enderror
    
    <x-metronic.card title="Status Akses Lokasi" class="mb-6">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Lokasi</label>
                <select name="work_location_id" class="form-select" onchange="this.form.submit()">
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected($selectedLocationId === $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Status dokumen</label>
                <select name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach(['draft'=>'Draft','pending_approval'=>'Menunggu Persetujuan','completed'=>'Selesai','rejected'=>'Ditolak','cancelled'=>'Dibatalkan'] as $value=>$label)
                        <option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-light-primary">Terapkan</button> 
                <a href="{{ route('warehouse.stock-adjustments.export', request()->query()) }}" class="btn btn-light-success">CSV</a>
            </div>
        </form>
        <div class="alert {{ $access?->isCurrentlyActive() ? 'alert-success' : 'alert-warning' }} mt-4 mb-0">
            @if($access?->isCurrentlyActive()) 
                Akses aktif sampai {{ $access->active_until?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB. 
            @else 
                Fitur terkunci. Super Admin harus mengaktifkan lokasi ini terlebih dahulu. 
            @endif
        </div>
    </x-metronic.card>

    @if(auth()->user()->hasRole('kepala_gudang') || auth()->user()->hasRole('kepala_toko') || auth()->user()->hasRole('super_admin'))
        @if($access?->isCurrentlyActive() && $stocks->isNotEmpty())
            <x-metronic.card title="Buat Draft Koreksi" class="mb-6">
                <form method="POST" action="{{ route('warehouse.stock-adjustments.store') }}" id="adjustment-form">
                    @csrf
                    <input type="hidden" name="work_location_id" value="{{ $selectedLocationId }}">
                    
                    <div class="d-flex align-items-center mb-5 bg-light p-5 rounded border border-dashed border-gray-300">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productCatalogModal">
                            <i class="ki-outline ki-category fs-2"></i> Buka Katalog Produk
                        </button>
                        <div class="ms-5">
                            <span class="fs-5 fw-bold text-gray-800" id="selected-count-label">0 produk terpilih</span>
                            <span class="d-block text-muted fs-7">Klik tombol di samping untuk mencari dan memilih stok yang ingin dikoreksi.</span>
                        </div>
                    </div>

                    <!-- Dynamic container for selected stock rows -->
                    <div id="item-rows"></div>

                    <div class="mt-6">
                        <label class="form-label fw-bold">Catatan Umum Dokumen</label>
                        <textarea name="notes" class="form-control form-control-solid" rows="2" placeholder="Tambahkan alasan umum kolektif, jika ada"></textarea>
                    </div>
                    <button class="btn btn-primary mt-6 btn-lg w-100 w-md-auto" id="save-adjustment-btn" disabled>
                        <i class="ki-outline ki-save-2"></i> Simpan Ke Draft
                    </button>
                </form>
            </x-metronic.card>
        @endif
    @endif

    <x-metronic.card title="Riwayat Koreksi">
        <div class="table-responsive">
            <table class="table table-row-bordered align-middle">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Lokasi</th>
                        <th>Status</th>
                        <th>Pembuat</th>
                        <th>Item</th>
                        <th>Dibuat</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($adjustments as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row->number }}</td>
                            <td>{{ $row->workLocation?->name }}</td>
                            <td><span class="badge badge-light-primary">{{ str_replace('_',' ',ucfirst($row->status)) }}</span></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-35px symbol-circle me-3">
                                        <img src="{{ $row->creator?->avatar_url }}" alt="Pic" onerror="this.outerHTML='<span class=\'symbol-label fw-bold text-primary\'>{{ substr($row->creator?->name ?? 'U', 0, 1) }}</span>'"/>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <span class="fw-bold text-gray-800">{{ $row->creator?->name }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $row->items->count() }}</td>
                            <td>{{ $row->created_at?->format('d/m/Y H:i') }}</td>
                            <td><a href="{{ route('warehouse.stock-adjustments.show', $row) }}" class="btn btn-sm btn-light-primary">Detail</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-10">Belum ada dokumen koreksi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $adjustments->links() }}
    </x-metronic.card>

    <!-- Modal Catalog (UI matching visual-catalog-modal from pricing/product-prices with stock data) -->
    @if(auth()->user()->hasRole('kepala_gudang') || auth()->user()->hasRole('kepala_toko') || auth()->user()->hasRole('super_admin'))
        @if($access?->isCurrentlyActive() && $stocks->isNotEmpty())
        <div class="modal fade" id="productCatalogModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg">
                    <!-- Modal Header -->
                    <div class="modal-header border-bottom py-4 px-6 bg-light-subtle">
                        <div class="d-flex align-items-center gap-3">
                            <div class="symbol symbol-40px bg-light-primary rounded-3 p-2">
                                <i class="ki-outline ki-category fs-2 text-primary"></i>
                            </div>
                            <div>
                                <h2 class="modal-title fs-4 fw-bold mb-0">Katalog Produk & Stok Gudang</h2>
                                <div class="text-muted fs-7">Pilih stok produk dari katalog untuk dibuatkan draft koreksi stok fisik.</div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-icon btn-light" data-bs-dismiss="modal">
                            <i class="ki-outline ki-cross fs-2"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="modal-body p-6">
                        <!-- Filter Toolbar (same design as pricing/product-prices visual-catalog-modal) -->
                        <div class="card bg-light-subtle border border-dashed border-gray-300 p-4 mb-5">
                            <div class="row g-3 align-items-center">
                                <div class="col-lg-6">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0"><i class="ki-outline ki-magnifier fs-4 text-muted"></i></span>
                                        <input type="search" id="catalog-search" class="form-control border-start-0" placeholder="Ketik nama produk atau SKU...">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <select id="catalog-category-filter" class="form-select">
                                        <option value="">Semua Kategori</option>
                                        @php
                                            $categories = $stocks->map(fn($s) => $s->product?->category?->name)->filter()->unique()->sort();
                                        @endphp
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat }}">{{ $cat }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 pt-3 border-top border-gray-300 fs-7">
                                <div class="text-muted">
                                    Menampilkan <strong id="catalog-count">{{ $stocks->count() }}</strong> stok produk
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="badge badge-light-success px-2 py-1"><span id="catalog-selected-count" class="fw-bold me-1">0</span> Terpilih</span>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-light-primary" id="catalog-select-all">Pilih Semua</button>
                                        <button type="button" class="btn btn-sm btn-light-danger" id="catalog-clear-all">Hapus Semua</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Products Grid -->
                        <div class="row g-4" id="catalog-grid">
                            @foreach($stocks as $stock)
                                <div class="col-sm-6 col-md-4 col-lg-3 catalog-item" 
                                     data-name="{{ strtolower($stock->product?->name ?? '') }}"
                                     data-sku="{{ strtolower($stock->product?->sku ?? '') }}"
                                     data-category="{{ $stock->product?->category?->name ?? 'Tanpa Kategori' }}">
                                    
                                    <div class="card h-100 border border-gray-300 catalog-card vcm-card" data-stock-id="{{ $stock->id }}">
                                        <!-- Selected Badge Floating on Top Right -->
                                        <div class="position-absolute top-0 end-0 m-3 vcm-selected-badge d-none" style="z-index: 2;">
                                            <span class="badge badge-circle badge-success shadow-sm" style="width: 26px; height: 26px;">
                                                <i class="ki-outline ki-check text-white fs-6"></i>
                                            </span>
                                        </div>

                                        <!-- Image Banner -->
                                        <div class="vcm-card-img-wrap">
                                            <img src="{{ $stock->product?->main_image_url ?? \App\Models\Product::defaultImageUrl() }}" 
                                                 class="vcm-card-img" 
                                                 alt="{{ $stock->product?->name }}" 
                                                 loading="lazy" 
                                                 onerror="this.src='/assets/media/products/default-product.png'" />
                                        </div>

                                        <!-- Card Body -->
                                        <div class="card-body p-4 d-flex flex-column">
                                            <!-- Category Badge -->
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <span class="badge badge-light-primary fw-bold px-2 py-1 fs-9">
                                                    {{ $stock->product?->category?->name ?? 'Tanpa Kategori' }}
                                                </span>
                                            </div>

                                            <!-- Product Name -->
                                            <div class="fw-bold text-gray-800 fs-6 text-truncate-2 mb-1" title="{{ $stock->product?->name }}">
                                                {{ $stock->product?->name }}
                                            </div>

                                            <!-- SKU -->
                                            <div class="text-muted fs-8 mb-3 d-flex align-items-center gap-1">
                                                <span class="fw-semibold text-gray-600">{{ $stock->product?->sku }}</span>
                                            </div>

                                            <!-- Stock Data Block (Highlight) -->
                                            <div class="bg-light-subtle rounded p-2 mb-3 border border-dashed border-gray-300">
                                                <div class="d-flex align-items-center justify-content-between mb-1">
                                                    <span class="fs-8 text-muted d-flex align-items-center gap-1">
                                                        <i class="ki-outline ki-cube-2 fs-7 text-success"></i> Stok Sistem:
                                                    </span>
                                                    <span class="badge badge-light-success fw-bolder fs-7 px-2 py-1">
                                                        {{ qty($stock->quantity_on_hand) }} {{ $stock->product?->baseUnit?->symbol ?? 'unit' }}
                                                    </span>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-between fs-8">
                                                    <span class="text-muted d-flex align-items-center gap-1">
                                                        <i class="ki-outline ki-geolocation fs-8 text-primary"></i> Lokasi Rak:
                                                    </span>
                                                    <span class="fw-bold text-gray-700">
                                                        {{ $stock->warehouseLocation?->full_code ?? 'Lokasi Utama' }}
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Card Bottom Action Button -->
                                            <div class="mt-auto">
                                                <button type="button" class="btn btn-sm btn-light-primary w-100 btn-select-stock"
                                                        data-id="{{ $stock->id }}"
                                                        data-name="{{ $stock->product?->name }}"
                                                        data-sku="{{ $stock->product?->sku }}"
                                                        data-stock="{{ qty($stock->quantity_on_hand) }}"
                                                        data-unit="{{ $stock->product?->baseUnit?->symbol ?? 'unit' }}"
                                                        data-location="{{ $stock->warehouseLocation?->full_code ?? 'Lokasi Utama' }}">
                                                    <i class="ki-outline ki-plus fs-6"></i> Pilih Stok
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            <!-- Empty State -->
                            <div class="col-12 text-center text-muted py-12 d-none" id="no-results-msg">
                                <i class="ki-outline ki-file-deleted fs-3x text-muted mb-3 d-block"></i>
                                <h5 class="fw-bold text-gray-700">Tidak ada produk stok yang cocok</h5>
                                <div class="text-muted fs-7">Coba ubah kata kunci pencarian atau pilih kategori lain.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="modal-footer py-3 px-6 border-top bg-light-subtle d-flex justify-content-between align-items-center">
                        <div class="text-muted fs-7">
                            <span id="footer-selected-count" class="fw-bold text-primary">0</span> produk stok dipilih
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal" id="apply-catalog-selection">
                                <i class="ki-outline ki-check-circle fs-5 me-1"></i> Terapkan ke Draft
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    @endif
@endsection

@push('styles')
<style>
.vcm-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    border-radius: 0.75rem;
    overflow: hidden;
    cursor: pointer;
    position: relative;
}
.vcm-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.08);
    border-color: var(--bs-primary) !important;
}
.vcm-card.is-selected {
    border-color: var(--bs-primary) !important;
    background-color: #f1faff !important;
    box-shadow: 0 0.25rem 1rem rgba(0, 158, 247, 0.18) !important;
}
.vcm-card-img-wrap {
    height: 145px;
    background: #f8f9fa;
    position: relative;
    overflow: hidden;
}
.vcm-card-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}
.vcm-card:hover .vcm-card-img {
    transform: scale(1.06);
}
.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.35;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('catalog-search');
    const categoryFilter = document.getElementById('catalog-category-filter');
    const catalogItems = document.querySelectorAll('.catalog-item');
    const noResultsMsg = document.getElementById('no-results-msg');
    const applyBtn = document.getElementById('apply-catalog-selection');
    const rowsContainer = document.getElementById('item-rows');
    const countLabel = document.getElementById('selected-count-label');
    const submitBtn = document.getElementById('save-adjustment-btn');
    const countEl = document.getElementById('catalog-count');
    const selectedCountEl = document.getElementById('catalog-selected-count');
    const footerSelectedCountEl = document.getElementById('footer-selected-count');
    const selectAllBtn = document.getElementById('catalog-select-all');
    const clearAllBtn = document.getElementById('catalog-clear-all');
    const modalEl = document.getElementById('productCatalogModal');

    // State of selected stocks: id => { id, name, sku, stock, unit, location }
    const selectedStocks = {};

    function updateCardUI(card, isSelected) {
        if (!card) return;
        const badge = card.querySelector('.vcm-selected-badge');
        const btn = card.querySelector('.btn-select-stock');
        
        if (isSelected) {
            card.classList.add('is-selected');
            if (badge) badge.classList.remove('d-none');
            if (btn) {
                btn.classList.remove('btn-light-primary');
                btn.classList.add('btn-success');
                btn.innerHTML = '<i class="ki-outline ki-check fs-6"></i> Terpilih';
            }
        } else {
            card.classList.remove('is-selected');
            if (badge) badge.classList.add('d-none');
            if (btn) {
                btn.classList.remove('btn-success');
                btn.classList.add('btn-light-primary');
                btn.innerHTML = '<i class="ki-outline ki-plus fs-6"></i> Pilih Stok';
            }
        }
    }

    function updateSelectionCounters() {
        const count = Object.keys(selectedStocks).length;
        if (selectedCountEl) selectedCountEl.textContent = count;
        if (footerSelectedCountEl) footerSelectedCountEl.textContent = count;
    }

    function toggleStock(card) {
        const id = card.dataset.stockId;
        const btn = card.querySelector('.btn-select-stock');
        if (!id || !btn) return;

        if (selectedStocks[id]) {
            delete selectedStocks[id];
            updateCardUI(card, false);
        } else {
            selectedStocks[id] = {
                id: id,
                name: btn.dataset.name,
                sku: btn.dataset.sku,
                stock: btn.dataset.stock,
                unit: btn.dataset.unit,
                location: btn.dataset.location
            };
            updateCardUI(card, true);
        }
        updateSelectionCounters();
    }

    // Click handler on card
    document.querySelectorAll('.vcm-card').forEach(card => {
        card.addEventListener('click', function(e) {
            // Prevent double toggle if button clicked
            toggleStock(this);
        });
    });

    // Filter functionality (Search + Category)
    function applyFilter() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const cat = categoryFilter ? categoryFilter.value : '';
        let visibleCount = 0;

        catalogItems.forEach(item => {
            const name = item.dataset.name || '';
            const sku = item.dataset.sku || '';
            const category = item.dataset.category || '';

            const matchQuery = !query || name.includes(query) || sku.includes(query);
            const matchCat = !cat || category === cat;

            if (matchQuery && matchCat) {
                item.style.display = 'block';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        if (countEl) countEl.textContent = visibleCount;
        if (noResultsMsg) {
            if (visibleCount === 0) noResultsMsg.classList.remove('d-none');
            else noResultsMsg.classList.add('d-none');
        }
    }

    if (searchInput) searchInput.addEventListener('input', applyFilter);
    if (categoryFilter) categoryFilter.addEventListener('change', applyFilter);

    // Select All visible
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            catalogItems.forEach(item => {
                if (item.style.display !== 'none') {
                    const card = item.querySelector('.vcm-card');
                    if (card) {
                        const id = card.dataset.stockId;
                        const btn = card.querySelector('.btn-select-stock');
                        if (id && btn && !selectedStocks[id]) {
                            selectedStocks[id] = {
                                id: id,
                                name: btn.dataset.name,
                                sku: btn.dataset.sku,
                                stock: btn.dataset.stock,
                                unit: btn.dataset.unit,
                                location: btn.dataset.location
                            };
                            updateCardUI(card, true);
                        }
                    }
                }
            });
            updateSelectionCounters();
        });
    }

    // Clear All
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function() {
            Object.keys(selectedStocks).forEach(id => {
                delete selectedStocks[id];
            });
            document.querySelectorAll('.vcm-card').forEach(card => {
                updateCardUI(card, false);
            });
            updateSelectionCounters();
        });
    }

    // Apply selection to form
    if (applyBtn) {
        applyBtn.addEventListener('click', function() {
            // Preserve typed input data
            const preservedData = {};
            if (rowsContainer) {
                rowsContainer.querySelectorAll('.adjustment-row').forEach(row => {
                    const id = row.dataset.id;
                    const qtyEl = row.querySelector('.qty-input');
                    const noteEl = row.querySelector('.note-input');
                    preservedData[id] = {
                        qty: qtyEl ? qtyEl.value : '',
                        note: noteEl ? noteEl.value : ''
                    };
                });

                rowsContainer.innerHTML = '';
            }

            const itemsArray = Object.values(selectedStocks);
            const count = itemsArray.length;

            if (countLabel) {
                countLabel.innerHTML = count > 0 
                    ? `<span class="text-primary fw-bold">${count} produk terpilih</span>` 
                    : '0 produk terpilih';
            }

            if (submitBtn) {
                submitBtn.disabled = count === 0;
            }

            if (count > 0 && rowsContainer) {
                rowsContainer.innerHTML = '<h5 class="mb-5 d-flex align-items-center text-gray-800"><i class="ki-outline ki-edit text-primary fs-2 me-2"></i> Input Stok Fisik Final untuk Produk Terpilih:</h5>';

                itemsArray.forEach((item, index) => {
                    const preserved = preservedData[item.id] || { qty: '', note: '' };
                    const rowHtml = `
                        <div class="row g-4 mb-4 align-items-center adjustment-row p-4 border border-dashed rounded bg-light-primary" data-id="${item.id}">
                            <input type="hidden" name="items[${index}][stock_id]" value="${item.id}">
                            <div class="col-md-5">
                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-gray-800 fs-6">${item.name}</span>
                                    <span class="text-muted fs-7 mt-1">SKU: ${item.sku} &bull; Lokasi: ${item.location}</span>
                                    <div class="mt-2"><span class="badge badge-light-dark fw-bold fs-7">Stok Sistem: ${item.stock} ${item.unit}</span></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-gray-800">Fisik Final <span class="text-danger">*</span></label>
                                <input type="number" step="0.0001" min="0" name="items[${index}][physical_qty]" class="form-control bg-white qty-input border-gray-300" value="${preserved.qty}" placeholder="Contoh: 10" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-gray-800">Catatan Item</label>
                                <input name="items[${index}][note]" class="form-control bg-white note-input border-gray-300" value="${preserved.note}" placeholder="Opsional (misal: Rusak)">
                            </div>
                            <div class="col-md-1 text-end">
                                <button type="button" class="btn btn-icon btn-sm btn-light-danger btn-remove-row" title="Hapus dari daftar">
                                    <i class="ki-outline ki-trash fs-5"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    rowsContainer.insertAdjacentHTML('beforeend', rowHtml);
                });
            }
        });
    }

    // Remove row from list
    if (rowsContainer) {
        rowsContainer.addEventListener('click', function(e) {
            const removeBtn = e.target.closest('.btn-remove-row');
            if (removeBtn) {
                const row = removeBtn.closest('.adjustment-row');
                const id = row.dataset.id;
                delete selectedStocks[id];
                row.remove();

                const card = document.querySelector(`.vcm-card[data-stock-id="${id}"]`);
                if (card) updateCardUI(card, false);
                updateSelectionCounters();

                const count = Object.keys(selectedStocks).length;
                if (count === 0) {
                    rowsContainer.innerHTML = '';
                    if (countLabel) countLabel.innerHTML = '0 produk terpilih';
                    if (submitBtn) submitBtn.disabled = true;
                } else {
                    if (countLabel) countLabel.innerHTML = `<span class="text-primary fw-bold">${count} produk terpilih</span>`;
                }
            }
        });
    }

    // Modal show sync
    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', function() {
            // Ensure cards reflect current selectedStocks
            document.querySelectorAll('.vcm-card').forEach(card => {
                const id = card.dataset.stockId;
                updateCardUI(card, !!selectedStocks[id]);
            });
            updateSelectionCounters();
        });
    }
});
</script>
@endpush
