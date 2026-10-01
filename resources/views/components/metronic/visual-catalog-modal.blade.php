@props([
    'id' => 'visualCatalogModal',
    'title' => 'Katalog Visual Produk',
    'description' => 'Pilih produk dari katalog dengan melihat foto dan detailnya.',
    'onSelect' => '', // JS function name to call when a product is clicked. Will receive (productData).
    'sourceSelector' => '', // CSS selector for <option> or elements to extract product data from.
    'dataUrl' => '', // Optional AJAX URL to fetch products if sourceSelector is empty.
])

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom py-4 px-6 bg-light-subtle">
                <div class="d-flex align-items-center gap-3">
                    <div class="symbol symbol-40px bg-light-primary rounded-3 p-2">
                        <i class="ki-outline ki-element-11 fs-2 text-primary"></i>
                    </div>
                    <div>
                        <h2 class="modal-title fs-4 fw-bold mb-0">{{ $title }}</h2>
                        <div class="text-muted fs-7">{{ $description }}</div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-light" data-bs-dismiss="modal">
                    <i class="ki-outline ki-cross fs-2"></i>
                </button>
            </div>
            <div class="modal-body p-6">
                <!-- Filter Toolbar -->
                <div class="card bg-light-subtle border border-dashed border-gray-300 p-4 mb-5">
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-6">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="ki-outline ki-magnifier fs-4 text-muted"></i></span>
                                <input type="search" id="vcm-search-{{ $id }}" class="form-control border-start-0 vcm-search" placeholder="Ketik nama produk atau SKU...">
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <select id="vcm-category-{{ $id }}" class="form-select vcm-category">
                                <option value="">Semua Kategori</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 pt-3 border-top border-gray-300 fs-7">
                        <div class="text-muted">
                            Menampilkan <strong id="vcm-count-{{ $id }}">0</strong> produk
                        </div>
                        <div id="vcm-multi-actions-{{ $id }}" class="d-none align-items-center gap-3">
                            <span class="badge badge-light-success px-2 py-1"><span id="vcm-selected-count-{{ $id }}" class="fw-bold me-1">0</span> Terpilih</span>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-light-primary" id="vcm-select-all-{{ $id }}">Pilih Semua</button>
                                <button type="button" class="btn btn-sm btn-light-danger" id="vcm-clear-all-{{ $id }}">Hapus Semua</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Products Grid -->
                <div id="vcm-grid-{{ $id }}" class="row g-4 vcm-grid">
                    <!-- Dinamis via JS -->
                </div>

                <!-- Empty State -->
                <div id="vcm-empty-{{ $id }}" class="text-center py-12 d-none">
                    <i class="ki-outline ki-file-deleted fs-3x text-muted mb-3"></i>
                    <h5 class="fw-bold text-gray-700">Tidak ada produk yang cocok</h5>
                </div>
            </div>
            <div class="modal-footer py-3 px-6 border-top bg-light-subtle d-flex justify-content-end">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@push('styles')
@once
<style>
.vcm-card { transition: transform 0.2s ease, box-shadow 0.2s ease; border-radius: 0.75rem; overflow: hidden; cursor: pointer; }
.vcm-card:hover { transform: translateY(-4px); box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.08); border-color: var(--bs-primary) !important; }
.vcm-card-img-wrap { height: 145px; background: #f8f9fa; position: relative; overflow: hidden; }
.vcm-card-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; }
.vcm-card:hover .vcm-card-img { transform: scale(1.06); }
.text-truncate-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.35; }
</style>
@endonce
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalId = '{{ $id }}';
    const sourceSelector = '{!! $sourceSelector !!}';
    const dataUrl = '{!! $dataUrl !!}';
    const onSelectFuncName = '{{ $onSelect }}';
    
    const modalEl = document.getElementById(modalId);
    if (!modalEl) return;
    
    const searchInput = document.getElementById('vcm-search-' + modalId);
    const categorySelect = document.getElementById('vcm-category-' + modalId);
    const grid = document.getElementById('vcm-grid-' + modalId);
    const emptyState = document.getElementById('vcm-empty-' + modalId);
    const countEl = document.getElementById('vcm-count-' + modalId);
    
    // Multi-select elements
    const multiActions = document.getElementById('vcm-multi-actions-' + modalId);
    const selectedCountEl = document.getElementById('vcm-selected-count-' + modalId);
    const selectAllBtn = document.getElementById('vcm-select-all-' + modalId);
    const clearAllBtn = document.getElementById('vcm-clear-all-' + modalId);
    
    let products = [];
    let categories = new Set();
    let isLoaded = false;
    let isMultiple = false;
    let sourceSelectEl = null;
    
    // Detect mode
    if (sourceSelector) {
        // e.g. "select#product_ids option" -> "select#product_ids"
        const selectQuery = sourceSelector.split(' ')[0];
        sourceSelectEl = document.querySelector(selectQuery);
        if (sourceSelectEl && sourceSelectEl.hasAttribute('multiple')) {
            isMultiple = true;
            multiActions.classList.remove('d-none');
            multiActions.classList.add('d-flex');
        }
    }
    
    // Listen to changes on the source select to sync UI if it's multiple
    if (isMultiple && sourceSelectEl) {
        sourceSelectEl.addEventListener('change', function() {
            updateSelectionUI();
        });
    }
    
    modalEl.addEventListener('show.bs.modal', function (e) {
        if (!isLoaded || dataUrl) {
            const button = e.relatedTarget;
            let dynamicUrl = dataUrl;
            if (button && button.dataset.urlParams) {
                const params = JSON.parse(button.dataset.urlParams);
                const urlObj = new URL(dynamicUrl, window.location.origin);
                for(const key in params) {
                    if (params[key]) urlObj.searchParams.set(key, params[key]);
                }
                dynamicUrl = urlObj.toString();
            }
            
            grid.innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';
            
            loadProducts(dynamicUrl).then(() => {
                renderGrid();
                if (isMultiple) updateSelectionUI();
                isLoaded = true;
            });
        } else {
            if (isMultiple) updateSelectionUI();
        }
    });
    
    function updateSelectionUI() {
        if (!isMultiple || !sourceSelectEl) return;
        
        const selectedValues = Array.from(sourceSelectEl.selectedOptions).map(opt => opt.value);
        selectedCountEl.textContent = selectedValues.length;
        
        grid.querySelectorAll('.vcm-card').forEach(card => {
            const id = card.dataset.id;
            const btn = card.querySelector('.btn-vcm-select');
            if (btn) {
                if (selectedValues.includes(id.toString())) {
                    btn.classList.remove('btn-light-primary');
                    btn.classList.add('btn-success');
                    btn.innerHTML = '<i class="ki-outline ki-check fs-6"></i> Terpilih';
                } else {
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-light-primary');
                    btn.innerHTML = '<i class="ki-outline ki-plus fs-6"></i> Pilih';
                }
            }
        });
    }
    
    async function loadProducts(url = '') {
        products = [];
        categories = new Set();
        
        if (url) {
            try {
                const response = await fetch(url);
                const data = await response.json();
                const results = data.results || data.data || [];
                
                results.forEach(item => {
                    const id = item.id;
                    const text = item.text || item.name || '';
                    const parts = text.split(' — ');
                    const sku = item.sku || (parts.length > 1 ? parts[0] : '');
                    const name = item.name || (parts.length > 1 ? parts[1] : text);
                    const unit = item.unit || '';
                    const category = item.category || 'Tanpa Kategori';
                    const image = item.image_url || item.main_image_url || item.image || '/assets/media/products/default-product.png';
                    
                    categories.add(category);
                    products.push({ id, sku, name, unit, category, image });
                });
                
                populateCategorySelect();
            } catch (error) {
                console.error("Visual Catalog Modal failed to fetch data:", error);
                grid.innerHTML = '<div class="alert alert-danger">Gagal memuat produk dari server.</div>';
            }
        } else if (sourceSelector) {
            const elements = document.querySelectorAll(sourceSelector);
            const seenIds = new Set();
            elements.forEach(el => {
                const id = el.value || el.dataset.id;
                if (!id || seenIds.has(id)) return;
                
                if (el.tagName.toLowerCase() === 'option' && !el.value) return;
                
                seenIds.add(id);
                
                const text = el.textContent || '';
                let sku = el.dataset.sku || '';
                let name = el.dataset.name || text;
                const unit = el.dataset.unit || '';
                const category = el.dataset.category || 'Tanpa Kategori';
                const image = el.dataset.image || '/assets/media/products/default-product.png';
                
                categories.add(category);
                
                products.push({ id: id, sku: sku, name: name, unit: unit, category: category, image: image });
            });
            
            populateCategorySelect();
        }
    }
    
    function populateCategorySelect() {
        categorySelect.innerHTML = '<option value="">Semua Kategori</option>';
        Array.from(categories).sort().forEach(cat => {
            const opt = document.createElement('option');
            opt.value = cat;
            opt.textContent = cat;
            categorySelect.appendChild(opt);
        });
    }
    
    function renderGrid() {
        const query = searchInput.value.toLowerCase().trim();
        const cat = categorySelect.value;
        
        let html = '';
        let count = 0;
        
        products.forEach(p => {
            const matchQuery = !query || p.sku.toLowerCase().includes(query) || p.name.toLowerCase().includes(query);
            const matchCat = !cat || p.category === cat;
            
            if (matchQuery && matchCat) {
                count++;
                html += `
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="card h-100 border border-gray-300 catalog-card vcm-card" data-id="${p.id}">
                        <div class="vcm-card-img-wrap">
                            <img src="${p.image}" class="vcm-card-img" alt="${p.name}" loading="lazy" onerror="this.onerror=null; this.src='/assets/media/products/default-product.png'">
                        </div>
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge badge-light-primary fw-bold px-2 py-1 fs-9">${p.category}</span>
                            </div>
                            <div class="fw-bold text-gray-800 fs-6 text-truncate-2 mb-1" title="${p.name}">${p.name}</div>
                            <div class="text-muted fs-8 mb-3 d-flex align-items-center gap-1">
                                <span class="fw-semibold text-gray-600">${p.sku}</span>
                            </div>
                            <div class="mt-auto">
                                <button type="button" class="btn btn-sm btn-light-primary w-100 btn-vcm-select" data-id="${p.id}">
                                    <i class="ki-outline ki-plus fs-6"></i> Pilih
                                </button>
                            </div>
                        </div>
                    </div>
                </div>`;
            }
        });
        
        grid.innerHTML = html;
        countEl.textContent = count;
        
        if (count === 0) {
            emptyState.classList.remove('d-none');
        } else {
            emptyState.classList.add('d-none');
        }
        
        if (isMultiple) {
            updateSelectionUI();
        }
        
        // Attach events
        grid.querySelectorAll('.vcm-card').forEach(card => {
            card.addEventListener('click', function(e) {
                const id = this.dataset.id;
                const product = products.find(p => p.id == id);
                if (product && onSelectFuncName && typeof window[onSelectFuncName] === 'function') {
                    window[onSelectFuncName](product);
                    
                    if (isMultiple) {
                        // For multi-select, rely on the change event of the underlying select
                        // However, we can also manually trigger an update if the external script doesn't.
                        // The external script handleCatalogSelect will toggle the option and dispatch 'change'.
                        // Our 'change' listener will call updateSelectionUI().
                    } else {
                        // Visual feedback for single select
                        const btn = this.querySelector('.btn-vcm-select');
                        if (btn) {
                            const originalHtml = btn.innerHTML;
                            btn.classList.remove('btn-light-primary');
                            btn.classList.add('btn-success');
                            btn.innerHTML = '<i class="ki-outline ki-check fs-6"></i> Terpilih';
                            setTimeout(() => {
                                btn.classList.remove('btn-success');
                                btn.classList.add('btn-light-primary');
                                btn.innerHTML = originalHtml;
                            }, 1500);
                        }
                    }
                }
            });
        });
    }
    
    searchInput.addEventListener('input', renderGrid);
    categorySelect.addEventListener('change', renderGrid);
    
    // Select All
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            if (!isMultiple || !sourceSelectEl || !onSelectFuncName) return;
            
            const query = searchInput.value.toLowerCase().trim();
            const cat = categorySelect.value;
            let changesMade = false;
            
            // Collect IDs that should be selected based on current filters
            const toSelectIds = [];
            products.forEach(p => {
                const matchQuery = !query || p.sku.toLowerCase().includes(query) || p.name.toLowerCase().includes(query);
                const matchCat = !cat || p.category === cat;
                if (matchQuery && matchCat) {
                    toSelectIds.push(p.id.toString());
                }
            });
            
            // Bulk update underlying select
            toSelectIds.forEach(id => {
                const opt = sourceSelectEl.querySelector('option[value="' + id + '"]');
                if (opt && !opt.selected) {
                    opt.selected = true;
                    changesMade = true;
                }
            });
            
            if (changesMade) {
                sourceSelectEl.dispatchEvent(new Event('change'));
                toastr && toastr.success(toSelectIds.length + ' produk ditambahkan.');
            }
        });
    }
    
    // Clear All
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function() {
            if (!isMultiple || !sourceSelectEl) return;
            let changesMade = false;
            
            Array.from(sourceSelectEl.options).forEach(opt => {
                if (opt.selected && opt.value) {
                    opt.selected = false;
                    changesMade = true;
                }
            });
            
            if (changesMade) {
                sourceSelectEl.dispatchEvent(new Event('change'));
                toastr && toastr.warning('Semua pilihan dihapus.');
            }
        });
    }
});
</script>
@endpush
