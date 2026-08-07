@php
    $isEdit = $return->exists;
    $selectedWorkLocation = old('work_location_id', $return->work_location_id);
    $selectedSourceType = old('source_type', $return->source_type ?: 'pos');
    $selectedReferenceId = old('reference_id', $return->reference_id);
    $selectedCondition = old('condition', \App\Enums\ReturnCondition::GOOD->value);
    $returnDate = old('return_date', $return->return_date?->format('Y-m-d') ?: now()->toDateString());
    $reasonOptions = [
        'broken' => 'Pecah / rusak',
        'wrong_item' => 'Barang tidak sesuai',
        'defect' => 'Cacat produk',
        'expired' => 'Kedaluwarsa',
        'shortage' => 'Kekurangan / selisih',
        'customer_request' => 'Permintaan pelanggan',
        'other' => 'Lainnya',
    ];
@endphp

@if ($errors->any())
    <div class="alert alert-danger d-flex align-items-start mb-6" role="alert">
        <i class="ki-outline ki-information-5 fs-2 me-3 mt-1"></i>
        <div>
            <div class="fw-bold mb-1">Form belum dapat disimpan.</div>
            <div>{{ $errors->first() }}</div>
        </div>
    </div>
@endif

<div class="alert alert-primary d-flex align-items-start mb-6" role="note">
    <i class="ki-outline ki-information-2 fs-2 me-3 mt-1"></i>
    <div>
        <div class="fw-bold mb-1">Alur retur: Draft / Ajukan &rarr; QC &rarr; Approval bila melewati batas &rarr; Settlement</div>
        <div class="fs-7">Penyimpanan draft dan pengajuan tidak mengubah stok. Mutasi stok baru dibuat saat pemeriksaan QC.</div>
    </div>
</div>

<input type="hidden" name="action" id="return-action" value="draft">
@unless($isEdit)
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
@endunless

<div class="row g-6 align-items-start">
    <div class="col-xl-8">
        <x-metronic.card title="Informasi Retur" class="mb-6">
            <div class="row g-5">
                <div class="col-md-6">
                    <x-metronic.form-group name="work_location_id" label="Lokasi Kerja" required help="Dokumen asal dan lokasi rak akan dibatasi ke lokasi ini.">
                        <select id="work_location_id" name="work_location_id" class="form-select form-select-solid" required>
                            <option value="">Pilih lokasi kerja</option>
                            @foreach($workLocations as $location)
                                <option value="{{ $location->id }}" @selected((string) $selectedWorkLocation === (string) $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>
                </div>
                <div class="col-md-6">
                    <x-metronic.form-group name="return_date" label="Tanggal Retur" required>
                        <input id="return_date" type="date" name="return_date" value="{{ $returnDate }}" max="{{ now()->addDay()->toDateString() }}" class="form-control form-control-solid" required>
                    </x-metronic.form-group>
                </div>
                <div class="col-md-6">
                    <x-metronic.form-group name="source_type" label="Jenis Sumber" required>
                        <select id="source_type" name="source_type" class="form-select form-select-solid" required>
                            @foreach($sourceTypes as $value => $label)
                                <option value="{{ $value }}" @selected($selectedSourceType === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>
                </div>
                <div class="col-md-6">
                    <x-metronic.form-group name="requested_resolution" label="Penyelesaian yang Diminta" required>
                        <select id="requested_resolution" name="requested_resolution" class="form-select form-select-solid" required>
                            <option value="">Pilih penyelesaian</option>
                            @foreach($resolutions as $value => $label)
                                <option value="{{ $value }}" @selected(old('requested_resolution', $return->requestedResolutionValue()) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>
                </div>
                <div class="col-md-6">
                    <x-metronic.form-group name="reason" label="Alasan Retur" required>
                        <select id="reason" name="reason" class="form-select form-select-solid" required>
                            <option value="">Pilih alasan</option>
                            @foreach($reasonOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('reason', $return->reason) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>
                </div>
                <div class="col-md-6">
                    <x-metronic.form-group name="notes" label="Catatan Umum">
                        <textarea id="notes" name="notes" rows="2" maxlength="2000" class="form-control form-control-solid" placeholder="Informasi tambahan untuk pemeriksa QC">{{ old('notes', $return->notes) }}</textarea>
                    </x-metronic.form-group>
                </div>
            </div>
        </x-metronic.card>

        <x-metronic.card title="Dokumen Asal" class="mb-6">
            <div id="transaction-source-fields">
                <x-metronic.form-group name="reference_id" label="Nomor Dokumen" required help="Cari berdasarkan nomor dokumen atau nama pelanggan/supplier.">
                    <select id="reference_id" name="reference_id" class="form-select form-select-solid" data-placeholder="Cari dokumen asal">
                        @if($selectedSourceDocument)
                            <option value="{{ $selectedSourceDocument['id'] }}" selected>{{ $selectedSourceDocument['text'] }}</option>
                        @endif
                    </select>
                </x-metronic.form-group>
                <div id="source-document-detail" class="border rounded p-4 {{ $selectedSourceDocument ? '' : 'd-none' }}">
                    <div class="row g-4 fs-7">
                        <div class="col-sm-6"><span class="text-muted d-block">Pihak asal</span><strong id="source-party">{{ $selectedSourceDocument['party'] ?? '-' }}</strong></div>
                        <div class="col-sm-6"><span class="text-muted d-block">Tanggal</span><strong id="source-date">{{ $selectedSourceDocument['date'] ?? '-' }}</strong></div>
                        <div class="col-sm-6"><span class="text-muted d-block">Lokasi</span><strong id="source-location">{{ $selectedSourceDocument['location'] ?? '-' }}</strong></div>
                        <div class="col-sm-6"><span class="text-muted d-block">Status / item</span><strong id="source-status">{{ isset($selectedSourceDocument) ? $selectedSourceDocument['status'].' / '.$selectedSourceDocument['item_count'].' item' : '-' }}</strong></div>
                    </div>
                </div>
            </div>
            <div id="manual-source-fields" class="row g-5 d-none">
                <div class="col-md-6">
                    <x-metronic.form-group name="source_name" label="Nama Pihak / Sumber">
                        <input name="source_name" value="{{ old('source_name', $return->source_name) }}" maxlength="255" class="form-control form-control-solid" placeholder="Contoh: Pelanggan walk-in">
                    </x-metronic.form-group>
                </div>
                <div class="col-md-6">
                    <x-metronic.form-group name="reference_no" label="Nomor Referensi">
                        <input name="reference_no" value="{{ old('reference_no', $return->reference_no) }}" maxlength="120" class="form-control form-control-solid" placeholder="Nomor dokumen eksternal bila ada">
                    </x-metronic.form-group>
                </div>
            </div>
        </x-metronic.card>

        <x-metronic.card title="Item Retur" class="mb-6">
            <x-slot:toolbar>
                <button type="button" id="add-return-item" class="btn btn-sm btn-light-primary">
                    <i class="ki-outline ki-plus fs-5 me-1"></i>Tambah Item
                </button>
            </x-slot:toolbar>
            <div id="source-items-loading" class="d-none py-10 text-center text-muted">
                <span class="spinner-border spinner-border-sm me-2"></span>Memuat item dokumen asal...
            </div>
            <div id="return-items-empty" class="text-center py-10 border border-dashed rounded">
                <i class="ki-outline ki-package fs-2x text-muted"></i>
                <div class="fw-semibold mt-3" id="return-items-empty-title">Pilih dokumen asal untuk melihat item.</div>
                <div class="text-muted fs-7 mt-1">Hanya item yang masih memiliki kuantitas dapat diretur yang ditampilkan.</div>
            </div>
            <div id="return-items" class="d-grid gap-4"></div>
        </x-metronic.card>

        <x-metronic.card title="Bukti & Catatan" class="mb-6">
            <label id="return-evidence-dropzone" class="return-dropzone d-flex flex-column align-items-center justify-content-center text-center p-8 border border-2 border-dashed rounded cursor-pointer" for="evidence_files">
                <i class="ki-outline ki-file-up fs-2x text-primary mb-3"></i>
                <span class="fw-semibold">Pilih atau jatuhkan foto/PDF bukti di sini</span>
                <span class="text-muted fs-7 mt-1">JPG, PNG, atau PDF. Maksimal 5 file, masing-masing 4 MB.</span>
                <input id="evidence_files" name="evidence_files[]" type="file" class="d-none" accept="image/jpeg,image/png,application/pdf" multiple>
            </label>
            <div id="evidence-preview" class="row g-3 mt-2"></div>
            @if($return->exists && $return->attachments->isNotEmpty())
                <div class="separator my-5"></div>
                <div class="fw-semibold mb-3">Bukti tersimpan</div>
                <div class="d-flex flex-wrap gap-3">
                    @foreach($return->attachments as $attachment)
                        <a class="btn btn-sm btn-light" href="{{ Storage::disk($attachment->disk)->url($attachment->path) }}" target="_blank" rel="noopener">
                            <i class="ki-outline ki-file fs-5 me-2"></i>{{ $attachment->original_name }}
                        </a>
                    @endforeach
                </div>
            @endif
        </x-metronic.card>
    </div>

    <div class="col-xl-4">
        <div class="return-summary-sticky">
            <x-metronic.card title="Ringkasan Pengajuan">
                <div class="d-flex justify-content-between py-2"><span class="text-muted">Status simpan</span><span class="badge badge-light-warning">Draft</span></div>
                <div class="d-flex justify-content-between py-2"><span class="text-muted">Jumlah item</span><strong id="summary-item-count">0</strong></div>
                <div class="d-flex justify-content-between py-2"><span class="text-muted">Total qty</span><strong id="summary-total-qty">0</strong></div>
                <div class="d-flex justify-content-between py-2"><span class="text-muted">Kondisi baik</span><strong id="summary-good-qty">0</strong></div>
                <div class="d-flex justify-content-between py-2"><span class="text-muted">Kondisi bermasalah</span><strong id="summary-damaged-qty">0</strong></div>
                @if($showCost)
                    <div class="separator my-3"></div>
                    <div class="d-flex justify-content-between py-2"><span class="text-muted">Estimasi nilai</span><strong id="summary-value">Rp 0</strong></div>
                    <div id="approval-warning" class="alert alert-warning fs-7 mt-4 mb-0 d-none">Estimasi nilai melewati batas {{ \App\Support\CurrencyFormatter::rupiah($approvalThreshold) }}. Hasil QC dapat memerlukan approval.</div>
                @endif
                <div class="alert alert-light-primary fs-7 mt-5 mb-0">
                    Nilai dan keputusan akhir mengikuti hasil pemeriksaan QC. Belum ada stok yang berubah pada tahap ini.
                </div>
            </x-metronic.card>
        </div>
    </div>
</div>

<div class="return-form-actions bg-body border-top py-4 mt-2">
    <div class="d-flex flex-wrap justify-content-end gap-3">
        <a href="{{ $isEdit ? route('returns.show', $return) : route('returns.index') }}" class="btn btn-light">Batal</a>
        <button type="button" class="btn btn-light-primary return-submit" data-action="draft">
            <i class="ki-outline ki-pencil fs-5 me-2"></i>{{ $isEdit ? 'Perbarui Draft' : 'Simpan Draft' }}
        </button>
        <button type="button" class="btn btn-primary return-submit" data-action="submit">
            <i class="ki-outline ki-send fs-5 me-2"></i>Ajukan ke QC
        </button>
    </div>
</div>

<template id="return-item-template">
    <section class="return-item border rounded p-4" data-index="__INDEX__">
        <div class="d-flex align-items-start gap-3 mb-4">
            <div class="return-product-thumb bg-light rounded d-flex align-items-center justify-content-center flex-shrink-0"><i class="ki-outline ki-package fs-2 text-muted"></i></div>
            <div class="flex-grow-1 min-w-0">
                <label class="form-label required fw-semibold">Produk</label>
                <select class="form-select form-select-solid return-product-select" aria-label="Pilih produk"></select>
                <input type="hidden" class="return-product-id" name="items[__INDEX__][product_id]">
                <input type="hidden" class="return-source-item-id" name="items[__INDEX__][source_item_id]">
                <div class="return-product-meta text-muted fs-7 mt-2">Belum ada produk dipilih.</div>
            </div>
            <button type="button" class="btn btn-sm btn-icon btn-light-danger remove-return-item" title="Hapus item" aria-label="Hapus item"><i class="ki-outline ki-trash fs-5"></i></button>
        </div>
        <div class="row g-4">
            <div class="col-6 col-md-3"><span class="text-muted fs-8 d-block">QTY DOKUMEN</span><strong class="source-quantity">-</strong></div>
            <div class="col-6 col-md-3"><span class="text-muted fs-8 d-block">SUDAH RETUR</span><strong class="returned-quantity">-</strong></div>
            <div class="col-6 col-md-3"><span class="text-muted fs-8 d-block">MAKSIMUM</span><strong class="maximum-quantity">-</strong></div>
            @if($showCost)<div class="col-6 col-md-3"><span class="text-muted fs-8 d-block">HPP SNAPSHOT</span><strong class="unit-cost">-</strong></div>@endif
            <div class="col-md-4">
                <label class="form-label required">Qty Retur</label>
                <div class="input-group input-group-solid"><input type="number" min="0.0001" step="0.0001" class="form-control return-qty" name="items[__INDEX__][quantity_requested]" required><span class="input-group-text return-unit">Unit</span></div>
            </div>
            <div class="col-md-4">
                <label class="form-label required">Kondisi Awal</label>
                <select class="form-select form-select-solid return-condition" name="items[__INDEX__][condition]" required>
                    @foreach($conditions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Lokasi Masuk</label>
                <select class="form-select form-select-solid return-location" name="items[__INDEX__][warehouse_location_id]" data-placeholder="Pilih rak / bin"><option value="">Belum ditentukan</option></select>
            </div>
            <div class="col-12"><label class="form-label">Catatan Item</label><input class="form-control form-control-solid" maxlength="500" name="items[__INDEX__][notes]" placeholder="Kerusakan fisik, kemasan, atau informasi lain"></div>
        </div>
    </section>
</template>

@push('styles')
    <style>
        .return-summary-sticky { position: sticky; top: 92px; }
        .return-form-actions { position: sticky; bottom: 0; z-index: 20; }
        .return-product-thumb { width: 52px; height: 52px; overflow: hidden; }
        .return-product-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .return-dropzone.is-dragging { border-color: var(--bs-primary) !important; background: var(--bs-primary-light); }
        .return-evidence-file { min-height: 72px; }
        .min-w-0 { min-width: 0; }
        @media (max-width: 1199.98px) { .return-summary-sticky { position: static; } }
    </style>
@endpush

@push('scripts')
<script>
const initializeReturnForm = () => {
    const form = document.getElementById('return-form');
    if (!form) return;

    const $ = window.jQuery;
    const endpoints = {
        documents: @json(route('returns.source-documents')),
        items: @json(route('returns.source-items')),
        locations: @json(route('returns.locations')),
    };
    const showCost = @json($showCost);
    const approvalThreshold = Number(@json($approvalThreshold));
    const currentReturnId = @json($return->exists ? $return->id : null);
    const initialItems = @json($formItems);
    const selectedDocument = @json($selectedSourceDocument);
    const sourceItems = new Map();
    let rowSequence = 0;

    const workLocation = document.getElementById('work_location_id');
    const sourceType = document.getElementById('source_type');
    const reference = document.getElementById('reference_id');
    const itemContainer = document.getElementById('return-items');
    const emptyState = document.getElementById('return-items-empty');
    const loadingState = document.getElementById('source-items-loading');

    const formatQty = value => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 4 });
    const formatMoney = value => 'Rp ' + Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
    const isManual = () => sourceType.value === 'manual';

    function initializeBasicSelect(element) {
        if (!$ || typeof $.fn.select2 !== 'function') return;
        const select = $(element);
        if (select.hasClass('select2-hidden-accessible')) select.select2('destroy');
        select.select2({ theme: 'bootstrap5', width: '100%', minimumResultsForSearch: 0, allowClear: false });
        select.attr('data-kt-initialized', '1');
    }

    function initializeReferenceSelect() {
        if (!$ || typeof $.fn.select2 !== 'function') return;
        const select = $(reference);
        if (select.hasClass('select2-hidden-accessible')) select.select2('destroy');
        select.select2({
            theme: 'bootstrap5', width: '100%', placeholder: 'Cari dokumen asal', allowClear: true, minimumInputLength: 0,
            ajax: {
                url: endpoints.documents, dataType: 'json', delay: 250,
                data: params => ({ q: params.term || '', page: params.page || 1, source_type: sourceType.value, work_location_id: workLocation.value }),
                processResults: response => response,
            },
            language: { noResults: () => 'Dokumen tidak ditemukan.', searching: () => 'Mencari dokumen...' },
        });
        select.attr('data-kt-initialized', '1');
        select.off('select2:select.return').on('select2:select.return', event => {
            renderDocumentDetail(event.params.data);
            loadDocumentItems();
        });
        select.off('select2:clear.return').on('select2:clear.return', () => {
            renderDocumentDetail(null);
            clearRows('Pilih dokumen asal untuk melihat item.');
        });
    }

    function renderDocumentDetail(documentData) {
        const panel = document.getElementById('source-document-detail');
        panel.classList.toggle('d-none', !documentData);
        if (!documentData) return;
        document.getElementById('source-party').textContent = documentData.party || '-';
        document.getElementById('source-date').textContent = documentData.date || '-';
        document.getElementById('source-location').textContent = documentData.location || '-';
        document.getElementById('source-status').textContent = `${documentData.status || '-'} / ${documentData.item_count || 0} item`;
    }

    function toggleSourceMode(reset = false) {
        const manual = isManual();
        document.getElementById('transaction-source-fields').classList.toggle('d-none', manual);
        document.getElementById('manual-source-fields').classList.toggle('d-none', !manual);
        reference.disabled = manual || !workLocation.value;
        if (reset) {
            if ($ && $(reference).hasClass('select2-hidden-accessible')) $(reference).val(null).trigger('change');
            else reference.innerHTML = '';
            renderDocumentDetail(null);
            clearRows(manual ? 'Tambahkan produk yang akan diretur.' : 'Pilih dokumen asal untuk melihat item.');
        }
    }

    async function fetchItems(query = '') {
        const params = new URLSearchParams({ source_type: sourceType.value, work_location_id: workLocation.value, q: query });
        if (!isManual()) params.set('reference_id', reference.value);
        if (currentReturnId) params.set('exclude_return_id', currentReturnId);
        const response = await fetch(`${endpoints.items}?${params.toString()}`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Item sumber tidak dapat dimuat. Periksa kembali lokasi kerja dan dokumen asal.');
        return (await response.json()).results || [];
    }

    async function loadDocumentItems() {
        if (!workLocation.value || !reference.value || isManual()) return;
        loadingState.classList.remove('d-none');
        emptyState.classList.add('d-none');
        try {
            const items = await fetchItems();
            sourceItems.clear();
            items.forEach(item => sourceItems.set(String(item.source_item_id), item));
            itemContainer.innerHTML = '';
            if (!items.length) {
                clearRows('Semua item dalam transaksi ini sudah selesai diretur.');
                return;
            }
            addRow();
            updateSummary();
        } catch (error) {
            clearRows(error.message);
        } finally {
            loadingState.classList.add('d-none');
        }
    }

    function clearRows(message) {
        itemContainer.innerHTML = '';
        sourceItems.clear();
        document.getElementById('return-items-empty-title').textContent = message;
        emptyState.classList.remove('d-none');
        updateSummary();
    }

    function initializeProductSelect(row, selectedItem = null) {
        const select = row.querySelector('.return-product-select');
        if (selectedItem) select.append(new Option(selectedItem.text || `${selectedItem.sku} - ${selectedItem.name}`, selectedItem.source_item_id || selectedItem.product_id, true, true));
        if (!$ || typeof $.fn.select2 !== 'function') return;
        const $select = $(select);
        const options = { theme: 'bootstrap5', width: '100%', placeholder: 'Pilih produk', allowClear: true };
        if (isManual()) {
            options.minimumInputLength = 1;
            options.ajax = {
                url: endpoints.items, dataType: 'json', delay: 250,
                data: params => ({ q: params.term || '', source_type: 'manual', work_location_id: workLocation.value }),
                processResults: response => response,
            };
        } else {
            options.data = Array.from(sourceItems.values()).map(item => ({ ...item, id: item.source_item_id }));
        }
        $select.select2(options).attr('data-kt-initialized', '1');
        $select.on('select2:select', event => applyItem(row, event.params.data));
        $select.on('select2:clear', () => applyItem(row, null));
    }

    function initializeLocationSelect(row, selectedId, selectedText) {
        const select = row.querySelector('.return-location');
        if (selectedId) select.append(new Option(selectedText || `Lokasi #${selectedId}`, selectedId, true, true));
        if (!$ || typeof $.fn.select2 !== 'function') return;
        $(select).select2({
            theme: 'bootstrap5', width: '100%', placeholder: 'Pilih rak / bin', allowClear: true,
            ajax: {
                url: endpoints.locations, dataType: 'json', delay: 250,
                data: params => ({ q: params.term || '', page: params.page || 1, work_location_id: workLocation.value }),
                processResults: response => response,
            },
            language: { noResults: () => 'Rak/bin aktif tidak ditemukan.' },
        }).attr('data-kt-initialized', '1');
    }

    function addRow(item = null) {
        if (!workLocation.value) {
            notify('Pilih lokasi kerja terlebih dahulu.', 'warning');
            return;
        }
        if (!isManual() && !reference.value) {
            notify('Pilih dokumen asal terlebih dahulu.', 'warning');
            return;
        }
        const html = document.getElementById('return-item-template').innerHTML.replaceAll('__INDEX__', rowSequence++);
        itemContainer.insertAdjacentHTML('beforeend', html);
        const row = itemContainer.lastElementChild;
        emptyState.classList.add('d-none');
        initializeProductSelect(row, item);
        initializeLocationSelect(row, item?.warehouse_location_id, item?.warehouse_location_text);
        initializeBasicSelect(row.querySelector('.return-condition'));
        applyItem(row, item);
        if (item) {
            row.querySelector('.return-qty').value = item.quantity_requested ?? item.maximum_quantity ?? '';
            row.querySelector('.return-condition').value = item.condition || @json($selectedCondition);
            if ($) $(row.querySelector('.return-condition')).trigger('change.select2');
            row.querySelector('[name$="[notes]"]').value = item.notes || '';
        }
        updateSummary();
    }

    function applyItem(row, item) {
        const productId = item?.product_id || '';
        const sourceItemId = isManual() ? '' : (item?.source_item_id || item?.id || '');
        row.dataset.unitCost = Number(item?.unit_cost || 0);
        row.querySelector('.return-product-id').value = productId;
        row.querySelector('.return-source-item-id').value = sourceItemId;
        row.querySelector('.return-product-meta').textContent = item ? `${item.sku || '-'} / ${item.unit || 'Unit'}` : 'Belum ada produk dipilih.';
        row.querySelector('.source-quantity').textContent = item?.source_quantity == null ? '-' : formatQty(item.source_quantity);
        row.querySelector('.returned-quantity').textContent = item?.source_quantity == null ? '-' : formatQty(item.already_returned);
        row.querySelector('.maximum-quantity').textContent = item?.maximum_quantity == null ? '-' : formatQty(item.maximum_quantity);
        row.querySelector('.return-unit').textContent = item?.unit || 'Unit';
        const qtyInput = row.querySelector('.return-qty');
        if (item?.maximum_quantity != null) qtyInput.max = item.maximum_quantity;
        else qtyInput.removeAttribute('max');
        const thumb = row.querySelector('.return-product-thumb');
        thumb.innerHTML = item?.thumbnail ? `<img src="${escapeAttribute(item.thumbnail)}" alt="">` : '<i class="ki-outline ki-package fs-2 text-muted"></i>';
        if (showCost) row.querySelector('.unit-cost').textContent = item?.unit_cost == null ? '-' : formatMoney(item.unit_cost);
        updateSummary();
    }

    function escapeAttribute(value) {
        const div = document.createElement('div');
        div.textContent = value || '';
        return div.innerHTML.replaceAll('"', '&quot;');
    }

    function updateSummary() {
        const rows = Array.from(itemContainer.querySelectorAll('.return-item'));
        let total = 0, good = 0, damaged = 0, value = 0, completeRows = 0;
        rows.forEach(row => {
            const qty = Number(row.querySelector('.return-qty').value || 0);
            if (row.querySelector('.return-product-id').value && qty > 0) completeRows++;
            total += qty;
            const condition = row.querySelector('.return-condition').value;
            if (condition === 'good') good += qty; else damaged += qty;
            value += qty * Number(row.dataset.unitCost || 0);
        });
        document.getElementById('summary-item-count').textContent = completeRows;
        document.getElementById('summary-total-qty').textContent = formatQty(total);
        document.getElementById('summary-good-qty').textContent = formatQty(good);
        document.getElementById('summary-damaged-qty').textContent = formatQty(damaged);
        if (showCost) {
            document.getElementById('summary-value').textContent = formatMoney(value);
            document.getElementById('approval-warning').classList.toggle('d-none', value <= approvalThreshold);
        }
    }

    function notify(message, icon = 'error') {
        if (window.Swal) window.Swal.fire({ text: message, icon, confirmButtonText: 'Tutup' });
        else alert(message);
    }

    function validateRows() {
        const rows = Array.from(itemContainer.querySelectorAll('.return-item'));
        if (!rows.length) return 'Tambahkan minimal satu item retur.';
        for (const row of rows) {
            const qty = Number(row.querySelector('.return-qty').value || 0);
            const max = row.querySelector('.return-qty').max;
            if (!row.querySelector('.return-product-id').value) return 'Pilih produk pada setiap baris item.';
            if (qty <= 0) return 'Qty retur harus lebih dari nol.';
            if (max && qty > Number(max)) return 'Qty retur tidak boleh melebihi kuantitas maksimum dari dokumen asal.';
        }
        return null;
    }

    async function submitForm(action) {
        const rowError = validateRows();
        if (!form.reportValidity() || rowError) {
            if (rowError) notify(rowError);
            return;
        }
        if (action === 'submit') {
            const confirmed = window.Swal
                ? (await window.Swal.fire({ title: 'Ajukan retur ke QC?', text: 'Draft tidak dapat diubah lagi setelah diajukan. Stok belum berubah sampai QC diproses.', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, ajukan', cancelButtonText: 'Batal' })).isConfirmed
                : confirm('Ajukan retur ke QC?');
            if (!confirmed) return;
        }
        document.getElementById('return-action').value = action;
        form.querySelectorAll('.return-submit').forEach(button => button.disabled = true);
        form.submit();
    }

    function initializeEvidence() {
        const input = document.getElementById('evidence_files');
        const dropzone = document.getElementById('return-evidence-dropzone');
        const preview = document.getElementById('evidence-preview');
        let files = [];

        function sync(newFiles) {
            const accepted = [];
            for (const file of newFiles) {
                if (!['image/jpeg', 'image/png', 'application/pdf'].includes(file.type) || file.size > 4 * 1024 * 1024) {
                    notify(`File ${file.name} tidak sesuai format atau melebihi 4 MB.`);
                    continue;
                }
                if (accepted.length >= 5) break;
                accepted.push(file);
            }
            files = accepted;
            const transfer = new DataTransfer();
            files.forEach(file => transfer.items.add(file));
            input.files = transfer.files;
            preview.innerHTML = '';
            files.forEach((file, index) => {
                const url = file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
                preview.insertAdjacentHTML('beforeend', `<div class="col-sm-6"><div class="return-evidence-file border rounded p-3 d-flex align-items-center gap-3">${url ? `<img src="${url}" alt="" width="48" height="48" class="rounded object-fit-cover">` : '<i class="ki-outline ki-file fs-2 text-danger"></i>'}<div class="flex-grow-1 text-truncate fs-7">${escapeAttribute(file.name)}</div><button type="button" class="btn btn-sm btn-icon btn-light-danger remove-evidence" data-index="${index}" aria-label="Hapus file"><i class="ki-outline ki-cross fs-4"></i></button></div></div>`);
            });
        }
        input.addEventListener('change', () => sync(Array.from(input.files)));
        ['dragenter', 'dragover'].forEach(name => dropzone.addEventListener(name, event => { event.preventDefault(); dropzone.classList.add('is-dragging'); }));
        ['dragleave', 'drop'].forEach(name => dropzone.addEventListener(name, event => { event.preventDefault(); dropzone.classList.remove('is-dragging'); }));
        dropzone.addEventListener('drop', event => sync([...files, ...Array.from(event.dataTransfer.files)]));
        preview.addEventListener('click', event => {
            const button = event.target.closest('.remove-evidence');
            if (!button) return;
            sync(files.filter((_, index) => index !== Number(button.dataset.index)));
        });
    }

    [workLocation, sourceType, document.getElementById('requested_resolution'), document.getElementById('reason')].forEach(initializeBasicSelect);
    initializeReferenceSelect();
    toggleSourceMode(false);
    renderDocumentDetail(selectedDocument);
    initialItems.forEach(item => {
        if (item.source_item_id) sourceItems.set(String(item.source_item_id), item);
        addRow(item);
    });
    if (!initialItems.length && isManual()) clearRows('Tambahkan produk yang akan diretur.');
    initializeEvidence();
    updateSummary();

    workLocation.addEventListener('change', () => toggleSourceMode(true));
    sourceType.addEventListener('change', () => { toggleSourceMode(true); initializeReferenceSelect(); });
    document.getElementById('add-return-item').addEventListener('click', async () => {
        if (!isManual() && reference.value) {
            try {
                (await fetchItems()).forEach(item => sourceItems.set(String(item.source_item_id), item));
            } catch (error) {
                notify(error.message);
                return;
            }
        }
        addRow();
    });
    itemContainer.addEventListener('click', event => {
        const button = event.target.closest('.remove-return-item');
        if (!button) return;
        button.closest('.return-item').remove();
        if (!itemContainer.children.length) clearRows(isManual() ? 'Tambahkan produk yang akan diretur.' : 'Pilih dokumen asal untuk melihat item.');
        updateSummary();
    });
    itemContainer.addEventListener('input', updateSummary);
    itemContainer.addEventListener('change', updateSummary);
    document.querySelectorAll('.return-submit').forEach(button => button.addEventListener('click', () => submitForm(button.dataset.action)));
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeReturnForm, { once: true });
} else {
    initializeReturnForm();
}
</script>
@endpush
