@extends('layouts.metronic.app')

@section('title', 'Permintaan Restok - '.config('app.name'))
@section('page_title', 'Permintaan Restok')

@section('toolbar_actions')
    @can('stock_transfers.receive')
        <a href="{{ route('retail.stock-transfers.receiving') }}" class="btn btn-light-primary">
            <i class="ki-outline ki-delivery-2 fs-5 me-1"></i>Terima Transfer Masuk
        </a>
    @endcan
@endsection

@section('content')
    <div class="alert alert-primary d-flex align-items-start gap-3">
        <i class="ki-outline ki-information-2 fs-2"></i>
        <div><strong>Permintaan restok tidak langsung memindahkan stok.</strong><div>Setelah disetujui, permintaan dapat diproses menjadi transfer stok.</div></div>
    </div>

    @can('create', \App\Models\RestockRequest::class)
        <x-metronic.card title="Ajukan Permintaan Restok" class="mb-6">
            <form method="POST" action="{{ route('retail.restock-requests.store') }}" id="restock-form">
                @csrf
                <div class="row g-4 mb-6">
                    <div class="col-md-3">
                        <x-metronic.form-group name="branch_id" label="Cabang Pemohon" required>
                            @if($branches->count() === 1)
                                <input type="hidden" name="branch_id" id="branch_id" value="{{ $branches->first()->id }}">
                                <select class="form-select form-select-solid" data-searchable="false" disabled aria-readonly="true"><option value="{{ $branches->first()->id }}" selected>{{ $branches->first()->name }}</option></select>
                            @else
                                <select name="branch_id" id="branch_id" class="form-select form-select-solid" required>
                                    <option value="">Pilih cabang</option>
                                    @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int) old('branch_id', $filters['branch_id'] ?? 0) === $branch->id)>{{ $branch->name }}</option>@endforeach
                                </select>
                            @endif
                        </x-metronic.form-group>
                    </div>
                    <div class="col-md-3"><x-metronic.form-group name="requester" label="Requester"><input class="form-control form-control-solid" value="{{ auth()->user()->name }}" readonly></x-metronic.form-group></div>
                    <div class="col-md-2"><x-metronic.form-group name="request_date" label="Tanggal"><input class="form-control form-control-solid" value="{{ now()->format('d/m/Y H:i') }}" readonly></x-metronic.form-group></div>
                    <div class="col-md-2"><x-metronic.form-group name="priority" label="Prioritas"><select name="priority" class="form-select form-select-solid"><option value="normal">Normal</option><option value="high">Tinggi</option><option value="urgent">Mendesak</option><option value="low">Rendah</option></select></x-metronic.form-group></div>
                    <div class="col-md-2"><x-metronic.form-group name="needed_at" label="Dibutuhkan"><input type="date" name="needed_at" value="{{ old('needed_at', now()->addDay()->toDateString()) }}" class="form-control form-control-solid"></x-metronic.form-group></div>
                </div>

                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                    <div>
                        <h3 class="fs-5 mb-1">Item Permintaan Restok</h3>
                        <div class="text-muted fs-7">Rekomendasi dihitung otomatis dari target stok (minimum + safety stock) dikurangi stok cabang saat ini.</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" id="btn-open-catalog-modal" class="btn btn-sm btn-primary">
                            <i class="ki-outline ki-element-11 fs-5 me-1"></i>Pilih dari Katalog Visual
                        </button>
                        <button type="button" id="add-restock-item" class="btn btn-sm btn-light-primary">
                            <i class="ki-outline ki-plus fs-5 me-1"></i>Tambah Baris
                        </button>
                    </div>
                </div>
                <div class="table-responsive mb-5">
                    <table class="table table-row-bordered align-middle restock-item-table">
                        <thead>
                            <tr class="text-muted fw-bold text-uppercase fs-8">
                                <th>Produk</th>
                                <th>SKU / Unit</th>
                                <th>Stok Sekarang</th>
                                <th>Stok Minimum</th>
                                <th>Target Stok</th>
                                <th>Rekomendasi</th>
                                <th>Qty Diminta</th>
                                <th>Prioritas</th>
                                <th>Alasan</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="restock-items"></tbody>
                    </table>
                </div>
                <template id="restock-item-template">
                    <tr class="restock-item-row" data-index="__INDEX__">
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="product-thumb-container symbol symbol-45px flex-shrink-0 bg-light rounded-3 overflow-hidden border border-gray-300 position-relative shadow-xs" title="Klik untuk perbesar foto">
                                    <img src="{{ \App\Models\Product::defaultImageUrl() }}" alt="Foto Produk" class="metric-image w-100 h-100 object-fit-cover preview-product-modal" role="button">
                                </div>
                                <div class="d-flex flex-column flex-grow-1 min-w-240px">
                                    <select name="items[__INDEX__][product_id]" class="form-select form-select-sm restock-product mb-1" data-placeholder="Pilih produk..." required>
                                        <option value="">Pilih produk...</option>
                                        @foreach($products as $product)
                                            <option value="{{ $product->id }}" data-image="{{ $product->main_image_url }}" data-category="{{ $product->category?->name ?? 'Tanpa kategori' }}">{{ $product->sku }} - {{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge badge-light-primary fs-9 metric-badge-category py-0 px-2">-</span>
                                        <span class="text-muted fs-8 metric-badge-sku">-</span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong class="metric-sku">-</strong>
                            <div class="text-muted metric-unit">-</div>
                        </td>
                        <td class="metric-stock fw-bold text-gray-800">0</td>
                        <td class="metric-minimum text-muted">0</td>
                        <td class="metric-target text-gray-800">0</td>
                        <td>
                            <span class="metric-recommendation badge badge-light-primary fw-bold fs-7">0</span>
                        </td>
                        <td>
                            <input name="items[__INDEX__][quantity_requested]" type="number" min="0.0001" step="0.0001" class="form-control form-control-sm restock-qty" placeholder="0" required>
                        </td>
                        <td>
                            <select name="items[__INDEX__][priority]" class="form-select form-select-sm" data-searchable="false">
                                <option value="normal">Normal</option>
                                <option value="high">Tinggi</option>
                                <option value="urgent">Mendesak</option>
                                <option value="low">Rendah</option>
                            </select>
                        </td>
                        <td>
                            <input name="items[__INDEX__][notes]" class="form-control form-control-sm" placeholder="Alasan kebutuhan">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-icon btn-light-danger remove-restock-item" aria-label="Hapus item" title="Hapus baris">
                                <i class="ki-outline ki-trash fs-5"></i>
                            </button>
                        </td>
                    </tr>
                </template>
                <x-metronic.form-group name="notes" label="Catatan Permintaan"><textarea name="notes" class="form-control form-control-solid" rows="2">{{ old('notes') }}</textarea></x-metronic.form-group>
                <div class="d-flex flex-wrap justify-content-end gap-2"><button name="action" value="draft" class="btn btn-light">Simpan Draft</button><button name="action" value="submit" class="btn btn-primary"><i class="ki-outline ki-send fs-5 me-1"></i>Ajukan Permintaan</button></div>
            </form>
        </x-metronic.card>
    @endcan

    <div class="d-flex flex-wrap align-items-center gap-2 mb-4 fs-8">
        @foreach(\App\Enums\RestockRequestStatus::cases() as $status)<span class="badge badge-light-primary">{{ $status->label() }}</span>@if(!$loop->last)<i class="ki-outline ki-arrow-right text-muted"></i>@endif @endforeach
    </div>
    <x-metronic.card title="Daftar Permintaan Restok">
        <form method="GET" class="row g-3 mb-5">
            <div class="col-md-4"><select name="branch_id" class="form-select form-select-solid"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') == $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold fs-8 text-uppercase"><th>Permintaan</th><th>Cabang / Requester</th><th>Gudang Sumber</th><th>Item / Qty</th><th>Prioritas</th><th>Status</th><th>Transfer Terkait</th><th>Aksi</th></tr></thead><tbody>
        @forelse($requests as $restock)
            <tr><td><a href="{{ route('retail.restock-requests.show', $restock) }}" class="fw-bold">{{ $restock->number }}</a><div class="text-muted fs-8">{{ $restock->created_at?->format('d/m/Y H:i') }}</div></td><td>{{ $restock->branch?->name }}<div class="text-muted fs-8">{{ $restock->requester?->name }}</div></td><td>{{ $restock->sourceWarehouse?->name ?: '-' }}</td><td>{{ $restock->items->count() }} item / {{ qty($restock->requestedQuantity()) }}</td><td>{{ ucfirst($restock->priority) }}</td><td><x-metronic.status-badge :status="$restock->status" /></td><td>@if($transfer = $restock->stockTransfers->first())<a href="{{ route('warehouse.stock-transfers.show', $transfer) }}" class="fw-bold">{{ $transfer->number }}</a>@else<span class="text-muted">-</span>@endif</td><td><div class="d-flex flex-wrap gap-2">
                @can('approve', $restock)
                    @if($restock->status === \App\Enums\RestockRequestStatus::PENDING_APPROVAL)
                        <form method="POST" action="{{ route('retail.restock-requests.approve', $restock) }}">@csrf @foreach($restock->items as $item)<input type="hidden" name="items[{{ $item->id }}][quantity_approved]" value="{{ qty_input($item->quantity_requested) }}">@endforeach<button class="btn btn-sm btn-light-success">Setujui</button></form>
                        <form method="POST" action="{{ route('retail.restock-requests.reject', $restock) }}">@csrf<input type="hidden" name="reason" value="Ditolak dari daftar"><button class="btn btn-sm btn-light-danger" data-confirm="Tolak permintaan restok ini?">Tolak</button></form>
                    @elseif($restock->status === \App\Enums\RestockRequestStatus::APPROVED)
                        <a href="{{ route('retail.restock-requests.convert-form', $restock) }}" class="btn btn-sm btn-primary">Buat Transfer Stok</a>
                    @endif
                @endcan
            </div></td></tr>
        @empty<tr><td colspan="8"><x-metronic.empty-state title="Belum ada permintaan restok" description="Permintaan kebutuhan stok cabang akan tampil di sini." /></td></tr>@endforelse
        </tbody></table></div>{{ $requests->links() }}
    </x-metronic.card>

    {{-- Modal Katalog Visual Produk --}}
    <div class="modal fade" id="catalogProductModal" tabindex="-1" aria-labelledby="catalogProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-bottom py-4 px-6 bg-light-subtle">
                    <div class="d-flex align-items-center gap-3">
                        <div class="symbol symbol-40px bg-light-primary rounded-3 p-2">
                            <i class="ki-outline ki-element-11 fs-2 text-primary"></i>
                        </div>
                        <div>
                            <h2 class="modal-title fs-4 fw-bold mb-0" id="catalogProductModalLabel">
                                Katalog Visual Produk Restok
                            </h2>
                            <div class="text-muted fs-7">
                                Pilih produk dengan melihat foto, stok cabang saat ini, dan rekomendasi kebutuhan untuk <span id="modal-branch-label" class="fw-bold text-primary">Cabang</span>.
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-icon btn-light" data-bs-dismiss="modal" aria-label="Tutup">
                        <i class="ki-outline ki-cross fs-2"></i>
                    </button>
                </div>
                <div class="modal-body p-6">
                    <!-- Filter Toolbar -->
                    <div class="card bg-light-subtle border border-dashed border-gray-300 p-4 mb-5">
                        <div class="row g-3 align-items-center">
                            <div class="col-lg-5">
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i class="ki-outline ki-magnifier fs-4 text-muted"></i></span>
                                    <input type="search" id="catalog-search" class="form-control border-start-0" placeholder="Ketik nama produk atau SKU...">
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <select id="catalog-category-filter" class="form-select">
                                    <option value="">Semua Kategori ({{ $categories->count() }})</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <select id="catalog-stock-filter" class="form-select">
                                    <option value="all">Semua Status Kebutuhan</option>
                                    <option value="needed">⚠️ Perlu Restok (Rekomendasi > 0)</option>
                                    <option value="critical">🚨 Kritis (Stok &le; Minimum)</option>
                                    <option value="safe">✅ Stok Cukup</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 pt-3 border-top border-gray-300 fs-7">
                            <div class="text-muted" id="catalog-count-info">
                                Menampilkan <strong id="catalog-visible-count">0</strong> produk
                            </div>
                            <div id="catalog-selected-badge" class="badge badge-light-primary fw-semibold px-3 py-2">
                                <i class="ki-outline ki-check-circle me-1"></i> <span id="catalog-selected-count">0</span> produk sudah di daftar restok
                            </div>
                        </div>
                    </div>

                    <!-- Products Grid -->
                    <div id="catalog-products-grid" class="row g-4">
                        <!-- Kartu produk dirender secara dinamis oleh JavaScript -->
                    </div>

                    <!-- Empty State -->
                    <div id="catalog-empty-state" class="text-center py-12 d-none">
                        <i class="ki-outline ki-file-deleted fs-3x text-muted mb-3"></i>
                        <h5 class="fw-bold text-gray-700">Tidak ada produk yang cocok</h5>
                        <p class="text-muted fs-7">Coba ubah kata kunci pencarian atau ganti filter status kebutuhan.</p>
                    </div>
                </div>
                <div class="modal-footer py-3 px-6 border-top bg-light-subtle d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="text-muted fs-8">
                        <i class="ki-outline ki-information-2 me-1"></i> Klik <strong>+ Tambah ke Restok</strong> pada kartu untuk menambahkan item langsung ke form.
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                        <i class="ki-outline ki-check fs-5 me-1"></i>Selesai Memilih
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Preview Foto Produk Resolusi Penuh --}}
    <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-3 px-4">
                    <h5 class="modal-title fw-bold fs-6" id="imagePreviewTitle">Pratinjau Foto Produk</h5>
                    <button type="button" class="btn btn-sm btn-icon btn-light" data-bs-dismiss="modal"><i class="ki-outline ki-cross fs-2"></i></button>
                </div>
                <div class="modal-body text-center p-4">
                    <img id="imagePreviewImg" src="" alt="Pratinjau Foto" class="img-fluid rounded shadow-sm max-h-400px object-fit-contain">
                    <div id="imagePreviewCaption" class="mt-3 text-muted fs-7 fw-semibold"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
.restock-item-table { min-width: 1220px; }
.restock-item-table .restock-product { min-width: 200px; }
.restock-item-table input, .restock-item-table select { min-width: 85px; }
.product-thumb-container { width: 48px; height: 48px; cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease; }
.product-thumb-container:hover { transform: scale(1.08); box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
.product-thumb-container img { transition: opacity 0.2s ease; }
.catalog-card { transition: transform 0.2s ease, box-shadow 0.2s ease; border-radius: 0.75rem; overflow: hidden; }
.catalog-card:hover { transform: translateY(-4px); box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08); border-color: var(--bs-primary) !important; }
.catalog-card-img-wrap { height: 145px; background: #f8f9fa; position: relative; overflow: hidden; cursor: pointer; }
.catalog-card-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; }
.catalog-card:hover .catalog-card-img { transform: scale(1.06); }
.text-truncate-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.35; }
.select2-container--bootstrap5 .select2-selection { border-radius: 0.475rem; }
</style>
@endpush

@push('scripts')
<script>
(() => {
    const form = document.getElementById('restock-form');
    if (!form) return;

    const metrics = @json($productMetrics);
    const defaultImageUrl = @json(\App\Models\Product::defaultImageUrl());
    const body = document.getElementById('restock-items');
    const template = document.getElementById('restock-item-template').innerHTML;
    const branch = document.getElementById('branch_id');
    const catalogModalEl = document.getElementById('catalogProductModal');
    const catalogModal = catalogModalEl ? new bootstrap.Modal(catalogModalEl) : null;
    const previewModalEl = document.getElementById('imagePreviewModal');
    const previewModal = previewModalEl ? new bootstrap.Modal(previewModalEl) : null;

    let index = 0;
    const number = value => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 4 });

    const getCurrentBranchId = () => {
        return branch ? branch.value : '';
    };

    const getCurrentBranchName = () => {
        if (!branch) return 'Cabang';
        if (branch.tagName === 'SELECT') {
            return branch.options[branch.selectedIndex]?.text || 'Cabang';
        }
        return branch.closest('.col-md-3')?.querySelector('input[readonly], select[disabled]')?.value || 'Cabang Terpilih';
    };

    // Fungsi update kalkulasi dan elemen visual baris tabel
    const update = row => {
        const productSelect = row.querySelector('.restock-product');
        const productId = productSelect.value;
        const data = metrics[productId] || {};
        const branchId = getCurrentBranchId();
        const current = Number(data.stocks?.[branchId] || 0);
        const target = Number(data.target || 0);
        const recommendation = Math.max(target - current, 0);

        // Update gambar & badge baris
        const img = row.querySelector('.metric-image');
        if (img) {
            img.src = data.image_url || defaultImageUrl;
            img.alt = data.name || 'Produk';
            img.dataset.name = data.name || '';
            img.dataset.sku = data.sku || '';
        }

        const badgeCategory = row.querySelector('.metric-badge-category');
        if (badgeCategory) {
            badgeCategory.textContent = data.category || '-';
        }

        const badgeSku = row.querySelector('.metric-badge-sku');
        if (badgeSku) {
            badgeSku.textContent = data.sku ? `SKU: ${data.sku}` : '-';
        }

        row.querySelector('.metric-sku').textContent = data.sku || '-';
        row.querySelector('.metric-unit').textContent = data.unit || '-';
        row.querySelector('.metric-stock').textContent = number(current);
        row.querySelector('.metric-minimum').textContent = number(data.minimum);
        row.querySelector('.metric-target').textContent = number(target);

        const recomBadge = row.querySelector('.metric-recommendation');
        if (recomBadge) {
            recomBadge.textContent = number(recommendation);
            if (recommendation > 0) {
                recomBadge.className = 'metric-recommendation badge badge-light-danger fw-bold fs-7';
            } else {
                recomBadge.className = 'metric-recommendation badge badge-light-success fw-bold fs-7';
            }
        }

        const qty = row.querySelector('.restock-qty');
        if (!qty.value || qty.dataset.auto === '1') {
            qty.value = recommendation > 0 ? recommendation : '';
            qty.dataset.auto = '1';
        }
    };

    // Format option Select2 dengan gambar produk
    const formatProductOption = state => {
        if (!state.id) return state.text;
        const data = metrics[state.id];
        if (!data) return state.text;
        const imgUrl = data.image_url || defaultImageUrl;
        const category = data.category || 'Tanpa kategori';
        return $(`
            <div class="d-flex align-items-center gap-2 py-1">
                <img src="${imgUrl}" class="rounded border object-fit-cover flex-shrink-0" style="width:28px;height:28px;" onerror="this.src='${defaultImageUrl}'" alt="" />
                <div class="d-flex flex-column text-start">
                    <span class="fw-semibold fs-7 text-gray-900">${state.text}</span>
                    <span class="text-muted fs-8">${category}</span>
                </div>
            </div>
        `);
    };

    const formatProductSelection = state => {
        if (!state.id) return state.text || 'Pilih produk...';
        const data = metrics[state.id];
        return data ? `${data.sku} - ${data.name}` : state.text;
    };

    const initSelect2OnRow = row => {
        const select = row.querySelector('.restock-product');
        if (!select) return;
        if (window.$ && typeof $.fn.select2 === 'function') {
            const $select = $(select);
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            $select.select2({
                theme: 'bootstrap5',
                width: '100%',
                placeholder: 'Pilih produk...',
                templateResult: formatProductOption,
                templateSelection: formatProductSelection,
            }).on('change', () => {
                update(row);
                syncCatalogCardStatuses();
            });
        }
    };

    // Tambah baris baru ke tabel item restok
    const add = values => {
        body.insertAdjacentHTML('beforeend', template.replaceAll('__INDEX__', index++));
        const row = body.lastElementChild;
        if (values?.product_id) {
            row.querySelector('.restock-product').value = values.product_id;
        }
        if (values?.quantity_requested) {
            row.querySelector('.restock-qty').value = values.quantity_requested;
            row.querySelector('.restock-qty').dataset.auto = '0';
        }

        row.querySelector('.restock-product').addEventListener('change', () => {
            update(row);
            syncCatalogCardStatuses();
        });
        row.querySelector('.restock-qty').addEventListener('input', event => event.target.dataset.auto = '0');
        row.querySelector('.remove-restock-item').addEventListener('click', () => {
            if (body.children.length > 1) {
                row.remove();
            } else {
                row.querySelector('.restock-product').value = '';
                if (window.$ && typeof $.fn.select2 === 'function') {
                    $(row.querySelector('.restock-product')).val('').trigger('change.select2');
                }
                row.querySelector('.restock-qty').value = '';
            }
            update(row);
            syncCatalogCardStatuses();
        });

        // Event klik gambar untuk preview modal
        const imgThumb = row.querySelector('.preview-product-modal');
        if (imgThumb) {
            imgThumb.addEventListener('click', () => {
                const src = imgThumb.src;
                const name = imgThumb.dataset.name || 'Foto Produk';
                const sku = imgThumb.dataset.sku ? `SKU: ${imgThumb.dataset.sku}` : '';
                openImagePreview(src, name, sku);
            });
        }

        initSelect2OnRow(row);
        update(row);
        syncCatalogCardStatuses();
        return row;
    };

    // Buka modal preview foto
    const openImagePreview = (src, title, caption) => {
        if (!previewModal) return;
        const img = document.getElementById('imagePreviewImg');
        const titleEl = document.getElementById('imagePreviewTitle');
        const captionEl = document.getElementById('imagePreviewCaption');
        if (img) img.src = src;
        if (titleEl) titleEl.textContent = title;
        if (captionEl) captionEl.textContent = caption || '';
        previewModal.show();
    };

    // Mendapatkan daftar ID produk yang saat ini sudah ada di tabel form
    const getSelectedProductIds = () => {
        const ids = new Set();
        body.querySelectorAll('.restock-product').forEach(select => {
            if (select.value) ids.add(select.value.toString());
        });
        return ids;
    };

    // Update status tombol pada kartu modal katalog
    const syncCatalogCardStatuses = () => {
        const selectedIds = getSelectedProductIds();
        const selectedCount = selectedIds.size;
        const selectedCountEl = document.getElementById('catalog-selected-count');
        if (selectedCountEl) selectedCountEl.textContent = selectedCount;

        document.querySelectorAll('.catalog-card-wrap').forEach(wrap => {
            const productId = wrap.dataset.productId;
            const btn = wrap.querySelector('.btn-card-action');
            if (!btn) return;
            if (selectedIds.has(productId)) {
                btn.className = 'btn btn-sm btn-light-success w-100 disabled btn-card-action fw-semibold';
                btn.innerHTML = '<i class="ki-outline ki-check fs-5 me-1 text-success"></i>Sudah di Daftar';
            } else {
                btn.className = 'btn btn-sm btn-primary w-100 btn-card-action';
                btn.innerHTML = '<i class="ki-outline ki-plus fs-5 me-1"></i>+ Tambah ke Restok';
            }
        });
    };

    // Render Grid Kartu Produk di Modal Katalog
    const renderCatalogGrid = () => {
        const grid = document.getElementById('catalog-products-grid');
        const emptyState = document.getElementById('catalog-empty-state');
        const searchVal = (document.getElementById('catalog-search')?.value || '').toLowerCase().trim();
        const categoryVal = document.getElementById('catalog-category-filter')?.value || '';
        const stockVal = document.getElementById('catalog-stock-filter')?.value || 'all';
        const branchId = getCurrentBranchId();
        const selectedIds = getSelectedProductIds();

        const branchLabel = document.getElementById('modal-branch-label');
        if (branchLabel) {
            branchLabel.textContent = getCurrentBranchName();
        }

        let visibleCount = 0;
        let html = '';

        Object.values(metrics).forEach(item => {
            // Filter Search (nama & SKU)
            const matchSearch = !searchVal ||
                item.name.toLowerCase().includes(searchVal) ||
                item.sku.toLowerCase().includes(searchVal);

            // Filter Kategori
            const matchCategory = !categoryVal || (item.category_id && item.category_id.toString() === categoryVal.toString());

            // Metrik Stok Cabang
            const currentStock = Number(item.stocks?.[branchId] || 0);
            const targetStock = Number(item.target || 0);
            const minStock = Number(item.minimum || 0);
            const recommendation = Math.max(targetStock - currentStock, 0);

            // Filter Status Stok
            let matchStock = true;
            if (stockVal === 'needed') {
                matchStock = recommendation > 0;
            } else if (stockVal === 'critical') {
                matchStock = currentStock <= minStock;
            } else if (stockVal === 'safe') {
                matchStock = recommendation <= 0;
            }

            if (!matchSearch || !matchCategory || !matchStock) {
                return;
            }

            visibleCount++;
            const isSelected = selectedIds.has(item.id.toString());

            // Badge status kebutuhan
            let statusBadge = '';
            if (currentStock <= minStock) {
                statusBadge = '<span class="badge badge-danger fs-9 py-1 px-2 position-absolute top-0 end-0 m-2 shadow-xs">🚨 Kritis</span>';
            } else if (recommendation > 0) {
                statusBadge = '<span class="badge badge-warning fs-9 py-1 px-2 position-absolute top-0 end-0 m-2 shadow-xs">⚠️ Perlu Restok</span>';
            } else {
                statusBadge = '<span class="badge badge-success fs-9 py-1 px-2 position-absolute top-0 end-0 m-2 shadow-xs">✅ Stok Cukup</span>';
            }

            const buttonHtml = isSelected
                ? '<button type="button" class="btn btn-sm btn-light-success w-100 disabled btn-card-action fw-semibold"><i class="ki-outline ki-check fs-5 me-1 text-success"></i>Sudah di Daftar</button>'
                : `<button type="button" class="btn btn-sm btn-primary w-100 btn-card-action btn-add-product" data-product-id="${item.id}"><i class="ki-outline ki-plus fs-5 me-1"></i>+ Tambah ke Restok</button>`;

            html += `
                <div class="col-sm-6 col-md-4 col-xl-3 catalog-card-wrap" data-product-id="${item.id}">
                    <div class="card h-100 border border-gray-200 catalog-card shadow-xs">
                        <div class="catalog-card-img-wrap" data-name="${item.name}" data-sku="${item.sku}" data-img="${item.image_url || defaultImageUrl}">
                            <img src="${item.image_url || defaultImageUrl}" alt="${item.name}" class="catalog-card-img" onerror="this.src='${defaultImageUrl}'">
                            <span class="badge badge-secondary fs-9 position-absolute bottom-0 start-0 m-2 bg-dark bg-opacity-75 text-white">
                                ${item.category || 'Tanpa kategori'}
                            </span>
                            ${statusBadge}
                        </div>
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="text-muted fs-8 fw-semibold mb-1">SKU: ${item.sku}</div>
                            <h4 class="fs-6 fw-bold text-gray-900 mb-3 text-truncate-2" title="${item.name}" style="min-height: 2.5rem;">
                                ${item.name}
                            </h4>
                            <div class="bg-light rounded p-3 mb-3 fs-8 border border-dashed border-gray-300">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Stok Cabang:</span>
                                    <strong class="${currentStock <= minStock ? 'text-danger' : 'text-gray-800'}">${number(currentStock)} ${item.unit}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Target (Min+Safe):</span>
                                    <span class="text-gray-700">${number(targetStock)} ${item.unit}</span>
                                </div>
                                <div class="d-flex justify-content-between pt-1 border-top border-gray-300">
                                    <span class="fw-semibold">Rekomendasi:</span>
                                    <strong class="${recommendation > 0 ? 'text-danger' : 'text-success'}">${recommendation > 0 ? '+' : ''}${number(recommendation)} ${item.unit}</strong>
                                </div>
                            </div>
                            <div class="mt-auto action-wrap">
                                ${buttonHtml}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        grid.innerHTML = html;
        const visibleEl = document.getElementById('catalog-visible-count');
        if (visibleEl) visibleEl.textContent = visibleCount;

        if (visibleCount === 0) {
            emptyState.classList.remove('d-none');
        } else {
            emptyState.classList.add('d-none');
        }

        // Attach event listener ke tombol tambah produk di kartu
        grid.querySelectorAll('.btn-add-product').forEach(btn => {
            btn.addEventListener('click', e => {
                const productId = btn.dataset.productId;
                if (!productId) return;

                // Cari baris kosong di tabel restok
                let targetRow = null;
                body.querySelectorAll('.restock-item-row').forEach(r => {
                    const sel = r.querySelector('.restock-product');
                    if (sel && !sel.value && !targetRow) {
                        targetRow = r;
                    }
                });

                if (targetRow) {
                    const sel = targetRow.querySelector('.restock-product');
                    sel.value = productId;
                    if (window.$ && typeof $.fn.select2 === 'function') {
                        $(sel).val(productId).trigger('change.select2');
                    } else {
                        update(targetRow);
                    }
                } else {
                    targetRow = add({ product_id: productId });
                }

                syncCatalogCardStatuses();

                // Highlight sebentar baris yang ditambah
                if (targetRow) {
                    targetRow.classList.add('table-primary');
                    setTimeout(() => targetRow.classList.remove('table-primary'), 1000);
                }
            });
        });

        // Attach event preview foto pada klik gambar kartu
        grid.querySelectorAll('.catalog-card-img-wrap').forEach(wrap => {
            wrap.addEventListener('click', () => {
                openImagePreview(wrap.dataset.img, wrap.dataset.name, `SKU: ${wrap.dataset.sku}`);
            });
        });
    };

    // Tombol Buka Katalog Visual Modal
    const btnOpenCatalog = document.getElementById('btn-open-catalog-modal');
    if (btnOpenCatalog && catalogModal) {
        btnOpenCatalog.addEventListener('click', () => {
            renderCatalogGrid();
            catalogModal.show();
        });
    }

    // Filter Listeners di Modal
    document.getElementById('catalog-search')?.addEventListener('input', renderCatalogGrid);
    document.getElementById('catalog-category-filter')?.addEventListener('change', renderCatalogGrid);
    document.getElementById('catalog-stock-filter')?.addEventListener('change', renderCatalogGrid);

    // Event tombol tambah manual di form
    document.getElementById('add-restock-item').addEventListener('click', () => add());

    // Event perubahan cabang pemohon
    branch?.addEventListener('change', () => {
        body.querySelectorAll('tr').forEach(update);
        if (catalogModalEl.classList.contains('show')) {
            renderCatalogGrid();
        }
    });

    // Load initial items (old input atau baris default)
    const oldItems = @json(old('items', []));
    if (oldItems && oldItems.length) {
        oldItems.forEach(add);
    } else {
        add();
    }
})();
</script>
@endpush
