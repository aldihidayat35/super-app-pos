const initializeStockTransferForm = () => {
    const form = document.getElementById('stock-transfer-form');
    if (!form || form.dataset.cascadeInitialized === 'true') return;

    form.dataset.cascadeInitialized = 'true';
    const optionsUrl = form.dataset.optionsUrl;
    const body = document.getElementById('transfer-items');
    const source = document.getElementById('source-work-location');
    const destination = document.getElementById('destination-work-location');
    const defaultSourceBin = form.querySelector('select[name="source_warehouse_location_id"]');
    const feedback = document.getElementById('transfer-location-feedback');
    const binRequests = new WeakMap();
    let nextIndex = body.querySelectorAll('.transfer-item-row').length;

    const destroy = (select) => {
        if (window.$?.fn?.select2 && window.$(select).hasClass('select2-hidden-accessible')) {
            window.$(select).select2('destroy');
        }
    };

    const bindSelectChange = (select, namespace, handler) => {
        if (window.$?.fn?.on) {
            window.$(select).on(`change.${namespace}`, handler);
            return;
        }

        select.addEventListener('change', handler);
    };

    const bindDelegatedSelectChange = (root, selector, namespace, handler) => {
        if (window.$?.fn?.on) {
            window.$(root).on(`change.${namespace}`, selector, handler);
            return;
        }

        root.addEventListener('change', (event) => {
            if (event.target.matches(selector)) handler(event);
        });
    };

    const showFeedback = (message, type = 'danger') => {
        if (!feedback) return;
        feedback.className = `alert alert-light-${type} mt-4`;
        feedback.textContent = message;
    };
    const hideFeedback = () => feedback?.classList.add('d-none');
    const effectiveSourceBin = (row) => row?.querySelector('.source-bin-select')?.value || defaultSourceBin?.value || '';

    const resetProduct = (row, notify = false) => {
        const select = row?.querySelector('.product-select');
        if (!select) return;
        const hadProduct = Boolean(select.value);
        destroy(select);
        select.innerHTML = '<option value="">Pilih lokasi ambil terlebih dahulu</option>';
        select.disabled = true;
        if (notify && hadProduct) showFeedback('Produk dikosongkan karena lokasi ambil berubah.', 'info');
    };

    const configureProduct = (row, preserveSelected = false) => {
        const select = row?.querySelector('.product-select');
        const warehouseLocationId = effectiveSourceBin(row);
        if (!select || !source.value || !warehouseLocationId) {
            resetProduct(row);
            return;
        }

        const selectedOption = preserveSelected && select.value
            ? new Option(select.selectedOptions[0]?.text || '', select.value, true, true)
            : null;
        destroy(select);
        select.innerHTML = '<option value="">Cari SKU atau nama produk</option>';
        if (selectedOption) select.add(selectedOption);
        select.disabled = false;
        if (!window.$?.fn?.select2) return;

        window.$(select).select2({
            theme: 'bootstrap5',
            selectionCssClass: ':all:',
            width: '100%', allowClear: true, minimumInputLength: 0,
            placeholder: 'Cari SKU atau nama produk',
            ajax: {
                url: optionsUrl, dataType: 'json', delay: 250,
                data: (params) => ({
                    work_location_id: source.value,
                    warehouse_location_id: effectiveSourceBin(row),
                    context: 'product', q: params.term || '', page: params.page || 1,
                }),
                processResults: (payload) => ({
                    results: Array.isArray(payload.results) ? payload.results : [],
                    pagination: payload.pagination || {more: false},
                }),
            },
            language: {
                noResults: () => 'Tidak ada produk dengan stok tersedia pada lokasi ambil ini',
                searching: () => 'Mencari produk…',
                errorLoading: () => 'Produk gagal dimuat. Silakan coba kembali.',
            },
        });
    };

    const configureLocationSearch = (select, context, workLocationId) => {
        destroy(select);
        if (!window.$?.fn?.select2) return;
        window.$(select).select2({
            theme: 'bootstrap5',
            selectionCssClass: ':all:',
            width: '100%', allowClear: true, minimumResultsForSearch: 0,
            placeholder: 'Cari zona/rak/bin',
            language: {
                noResults: () => context === 'source' ? 'Gudang ini belum memiliki zona/rak/bin aktif' : 'Tujuan ini belum memiliki zona/rak/bin aktif',
            },
        });
    };

    const loadBins = async (select, workLocationId, context, selected = '') => {
        // Guard: skip non-select elements (e.g., Select2 wrapper spans)
        if (select.tagName !== 'SELECT') return;

        binRequests.get(select)?.abort();
        const selectedOption = selected ? Array.from(select.options).find((option) => String(option.value) === String(selected)) : null;
        destroy(select);
        const label = context === 'source' ? 'sumber' : 'tujuan';
        if (!workLocationId) {
            // No work location selected yet — keep select2 initialized with a clear placeholder
            select.innerHTML = `<option value="">Pilih lokasi kerja ${label} terlebih dahulu</option>`;
            select.disabled = true;
            configureLocationSearch(select, context, null);
            return;
        }
        select.disabled = true;
        select.innerHTML = `<option value="">Memuat zona/rak/bin ${label}...</option>`;

        const controller = new AbortController();
        binRequests.set(select, controller);
        try {
            const query = new URLSearchParams({work_location_id: workLocationId, context, page: '1', per_page: '50'});
            const response = await window.appFetch(`${optionsUrl}?${query}`, {signal: controller.signal});
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();
            if (binRequests.get(select) !== controller) return;

            const results = Array.isArray(payload.results) ? payload.results : [];
            const emptyLabel = select.closest('td') ? 'Gunakan lokasi default' : (context === 'source' ? 'Pilih lokasi ambil' : 'Tanpa zona/rak/bin khusus');
            const noDataLabel = context === 'source' ? 'Gudang ini belum memiliki zona/rak/bin aktif' : 'Tujuan ini belum memiliki zona/rak/bin aktif';
            select.innerHTML = `<option value="">${results.length ? emptyLabel : noDataLabel}</option>`;
            results.forEach((item) => select.add(new Option(item.text, item.id, false, String(item.id) === String(selected))));
            if (selected && selectedOption && !Array.from(select.options).some((option) => String(option.value) === String(selected))) {
                select.add(new Option(selectedOption.text, selected, true, true));
            }
            // Always enable select2 for searchable UX, even when initial results are empty
            // so user can still search/refresh.
            select.disabled = false;
            configureLocationSearch(select, context, workLocationId);
            hideFeedback();
        } catch (error) {
            if (error?.name === 'AbortError') return;
            select.innerHTML = '<option value="">Lokasi gudang gagal dimuat</option>';
            select.disabled = true;
            showFeedback('Lokasi gudang gagal dimuat. Silakan coba kembali.');
        } finally {
            if (binRequests.get(select) === controller) binRequests.delete(select);
        }
    };

    const refreshGroup = (context, preserveSelected = true) => {
        const workLocationId = context === 'source' ? source.value : destination.value;
        document.querySelectorAll(`.${context}-bin-select`).forEach((select) => {
            const selected = preserveSelected ? (select.dataset.selected || select.value || '') : '';
            loadBins(select, workLocationId, context, selected);
        });
    };

    const bindRow = (row) => {
        row.querySelector('.remove-transfer-item').addEventListener('click', () => {
            if (body.querySelectorAll('.transfer-item-row').length > 1) row.remove();
        });
        loadBins(row.querySelector('.source-bin-select'), source.value, 'source');
        loadBins(row.querySelector('.destination-bin-select'), destination.value, 'destination');
        configureProduct(row, true);
    };

    bindSelectChange(source, 'stock-transfer-source', () => {
        document.querySelectorAll('.transfer-item-row').forEach((row) => resetProduct(row));
        document.querySelectorAll('.source-bin-select').forEach((select) => { select.dataset.selected = ''; select.value = ''; });
        refreshGroup('source', false);
    });
    bindSelectChange(destination, 'stock-transfer-destination', () => refreshGroup('destination', false));
    bindSelectChange(defaultSourceBin, 'stock-transfer-default-source-bin', () => {
        document.querySelectorAll('.transfer-item-row').forEach((row) => {
            if (!row.querySelector('.source-bin-select')?.value) {
                resetProduct(row, true);
                configureProduct(row);
            }
        });
    });
    bindDelegatedSelectChange(body, '.source-bin-select', 'stock-transfer-row-source-bin', (event) => {
        const row = event.target.closest('tr');
        resetProduct(row, true);
        configureProduct(row);
    });
    document.getElementById('add-transfer-item').addEventListener('click', () => {
        const template = document.getElementById('transfer-item-template').innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        body.insertAdjacentHTML('beforeend', template);
        bindRow(body.lastElementChild);
    });

    body.querySelectorAll('.transfer-item-row').forEach((row) => {
        row.querySelector('.remove-transfer-item').addEventListener('click', () => {
            if (body.querySelectorAll('.transfer-item-row').length > 1) row.remove();
        });
        configureProduct(row, true);
    });
    refreshGroup('source');
    refreshGroup('destination');
};

// Side-effect: register on window so Vite/Rollup won't tree-shake this module
window.initializeStockTransferForm = initializeStockTransferForm;

export {initializeStockTransferForm};
