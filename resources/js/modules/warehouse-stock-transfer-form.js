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
        feedback.classList.remove('d-none');
    };
    const hideFeedback = () => feedback?.classList.add('d-none');
    const effectiveSourceBin = (row) => row?.querySelector('.source-bin-select')?.value || defaultSourceBin?.value || '';

    const validateLocations = () => {
        if (!source?.value || !destination?.value) {
            hideFeedback();
            toggleSubmitButton();
            return true;
        }
        if (String(source.value) === String(destination.value)) {
            showFeedback('Lokasi kerja tujuan tidak boleh sama dengan lokasi sumber.', 'danger');
            toggleSubmitButton();
            return false;
        }
        hideFeedback();
        toggleSubmitButton();
        return true;
    };

    const toggleSubmitButton = () => {
        const submitBtns = form.querySelectorAll('button[name="action"]');
        const hasInvalidQty = Array.from(body.querySelectorAll('.quantity-input')).some((input) => input.classList.contains('is-invalid'));
        const isSameLocation = source?.value && destination?.value && String(source.value) === String(destination.value);

        const shouldDisable = hasInvalidQty || Boolean(isSameLocation);
        submitBtns.forEach((btn) => {
            btn.disabled = shouldDisable;
        });
    };

    const validateRowStock = (row) => {
        const qtyInput = row?.querySelector('.quantity-input');
        const unitSelect = row?.querySelector('.unit-select');
        const warningEl = row?.querySelector('.stock-warning-feedback');
        const approvedInput = row?.querySelector('.quantity-approved-input');
        if (!qtyInput) return true;

        const qty = parseFloat(qtyInput.value) || 0;
        if (approvedInput) {
            approvedInput.value = qtyInput.value;
        }

        const availableStr = row?.dataset?.availableQuantity;
        if (availableStr === undefined || availableStr === '') {
            qtyInput.classList.remove('is-invalid', 'border-danger');
            if (warningEl) {
                warningEl.classList.add('d-none');
                warningEl.textContent = '';
            }
            toggleSubmitButton();
            return true;
        }

        const selectedOption = unitSelect?.selectedOptions?.[0];
        const factor = parseFloat(selectedOption?.dataset?.factor) || 1;
        const qtyInBase = qty * factor;

        const available = parseFloat(availableStr);
        const stockPreview = row.dataset.stockPreview || `${available} Pcs`;
        const stockQtyText = stockPreview.replace(/^Stok:\s*/i, '');

        if (qtyInBase > available + 1e-7) {
            qtyInput.classList.add('is-invalid', 'border-danger');
            if (warningEl) {
                warningEl.textContent = `Melebihi stok tersedia (${stockQtyText})`;
                warningEl.classList.remove('d-none');
            }
            toggleSubmitButton();
            return false;
        } else {
            qtyInput.classList.remove('is-invalid', 'border-danger');
            if (warningEl) {
                warningEl.classList.add('d-none');
                warningEl.textContent = '';
            }
            toggleSubmitButton();
            return true;
        }
    };

    const populateUnits = (row, units = []) => {
        const unitSelect = row?.querySelector('.unit-select');
        const preview = row?.querySelector('.unit-conversion-preview');
        if (!unitSelect) return;
        unitSelect.innerHTML = '';
        units.forEach((unit, index) => {
            const unitValue = unit.id !== null && unit.id !== undefined && unit.id !== 'null' ? String(unit.id) : '';
            const option = new Option(unit.text, unitValue, index === 0, index === 0);
            option.dataset.factor = String(unit.factor || '1.000000');
            unitSelect.add(option);
        });
        if (!units.length) {
            const option = new Option('Satuan dasar', '', true, true);
            option.dataset.factor = '1.000000';
            unitSelect.add(option);
        }
        const refreshPreview = () => {
            const unit = units.find((candidate) => {
                const candidateId = candidate.id !== null && candidate.id !== undefined && candidate.id !== 'null' ? String(candidate.id) : '';
                return candidateId === String(unitSelect.value);
            });
            const factor = unit?.factor || unitSelect.selectedOptions?.[0]?.dataset?.factor || 1;
            if (preview) {
                preview.textContent = unit && Number(factor) !== 1 ? `1 ${unit.text} = ${Number(factor)} satuan dasar` : '';
            }
            validateRowStock(row);
        };
        unitSelect.onchange = refreshPreview;
        refreshPreview();
    };

    const resetProduct = (row, notify = false) => {
        const select = row?.querySelector('.product-select');
        if (!select) return;
        const hadProduct = Boolean(select.value);
        destroy(select);
        select.innerHTML = '<option value="">Pilih lokasi kerja sumber terlebih dahulu</option>';
        select.disabled = true;
        populateUnits(row);
        delete row.dataset.availableQuantity;
        delete row.dataset.stockPreview;
        const previewEl = row.querySelector('.product-stock-preview');
        if (previewEl) {
            previewEl.textContent = '';
            previewEl.classList.add('d-none');
        }
        validateRowStock(row);
        if (notify && hadProduct) showFeedback('Produk dikosongkan karena lokasi ambil berubah.', 'info');
    };

    const configureProduct = (row, preserveSelected = false) => {
        const select = row?.querySelector('.product-select');
        if (!select) return;
        if (!source.value) {
            resetProduct(row);
            return;
        }

        const selectedOption = preserveSelected && select.value
            ? new Option(select.selectedOptions[0]?.text || '', select.value, true, true)
            : null;
        const resultIdPrefix = `stock-transfer-product-${select.name.replace(/[^a-zA-Z0-9_-]/g, '-')}`;
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
                data: (params) => {
                    const reqData = {
                        work_location_id: source.value,
                        context: 'product',
                        q: params.term || '',
                        page: params.page || 1,
                    };
                    const binId = effectiveSourceBin(row);
                    if (binId) {
                        reqData.warehouse_location_id = binId;
                    }
                    return reqData;
                },
                transport: (params, success, failure) => {
                    const controller = new AbortController();
                    const query = new URLSearchParams();
                    Object.entries(params.data || {}).forEach(([key, value]) => query.set(key, String(value ?? '')));
                    let timedOut = false;
                    const timeout = window.setTimeout(() => {
                        timedOut = true;
                        controller.abort();
                    }, 10000);

                    window.appFetch(`${optionsUrl}?${query}`, {signal: controller.signal})
                        .then(async (response) => {
                            const payload = await response.json().catch(() => null);
                            if (!response.ok) {
                                throw new Error(payload?.message || `Produk gagal dimuat (HTTP ${response.status}).`);
                            }
                            if (!payload || !Array.isArray(payload.results)) {
                                throw new Error('Format data produk tidak valid.');
                            }

                            return payload;
                        })
                        .then((payload) => {
                            hideFeedback();
                            success(payload);
                        })
                        .catch((error) => {
                            if (error?.name === 'AbortError' && !timedOut) return;
                            const message = timedOut
                                ? 'Pencarian produk melewati batas waktu. Silakan buka ulang pilihan produk.'
                                : (error?.message || 'Produk gagal dimuat. Silakan coba kembali.');
                            showFeedback(message);
                            failure(error);
                        })
                        .finally(() => window.clearTimeout(timeout));

                    return {
                        abort: () => {
                            window.clearTimeout(timeout);
                            controller.abort();
                        },
                    };
                },
                processResults: (payload) => ({
                    // Select2 4.1 AjaxAdapter calls _normalizeItem without binding `this`.
                    // A stable result id bypasses its broken `this.container` branch.
                    results: Array.isArray(payload.results)
                        ? payload.results.map((item) => ({
                            ...item,
                            id: String(item.id),
                            _resultId: `${resultIdPrefix}-${item.id}`,
                        }))
                        : [],
                    pagination: payload.pagination || {more: false},
                }),
            },
            language: {
                noResults: () => 'Tidak ada produk dengan stok tersedia pada lokasi ambil ini',
                searching: () => 'Mencari produk…',
                errorLoading: () => 'Produk gagal dimuat. Silakan coba kembali.',
            },
        });
        window.$(select).off('select2:select.stock-transfer-unit').on('select2:select.stock-transfer-unit', (event) => {
            const item = event.params?.data || {};
            populateUnits(row, item.units || []);
            if (item.available_quantity !== undefined) {
                row.dataset.availableQuantity = String(item.available_quantity);
                row.dataset.stockPreview = item.stock_preview || '';
                const previewEl = row.querySelector('.product-stock-preview');
                if (previewEl) {
                    previewEl.textContent = item.stock_preview || `Tersedia: ${item.available_quantity}`;
                    previewEl.classList.remove('d-none');
                }
            }
            validateRowStock(row);
        });
        window.$(select).off('select2:clear.stock-transfer-unit').on('select2:clear.stock-transfer-unit', () => {
            delete row.dataset.availableQuantity;
            delete row.dataset.stockPreview;
            const previewEl = row.querySelector('.product-stock-preview');
            if (previewEl) {
                previewEl.textContent = '';
                previewEl.classList.add('d-none');
            }
            validateRowStock(row);
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
        if (!select || select.tagName !== 'SELECT') return;

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
            if (body.querySelectorAll('.transfer-item-row').length > 1) {
                row.remove();
                toggleSubmitButton();
            }
        });
        const qtyInput = row.querySelector('.quantity-input');
        if (qtyInput) {
            qtyInput.addEventListener('input', () => validateRowStock(row));
            qtyInput.addEventListener('change', () => validateRowStock(row));
        }
        const unitSelect = row.querySelector('.unit-select');
        if (unitSelect) {
            unitSelect.addEventListener('change', () => validateRowStock(row));
        }
        loadBins(row.querySelector('.source-bin-select'), source.value, 'source');
        configureProduct(row, true);
    };

    bindSelectChange(source, 'stock-transfer-source', () => {
        validateLocations();
        document.querySelectorAll('.transfer-item-row').forEach((row) => resetProduct(row));
        document.querySelectorAll('.source-bin-select').forEach((select) => { select.dataset.selected = ''; select.value = ''; });
        refreshGroup('source', false);
        document.querySelectorAll('.transfer-item-row').forEach((row) => configureProduct(row));
    });
    bindSelectChange(destination, 'stock-transfer-destination', () => {
        validateLocations();
        refreshGroup('destination', false);
    });
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
            if (body.querySelectorAll('.transfer-item-row').length > 1) {
                row.remove();
                toggleSubmitButton();
            }
        });
        const qtyInput = row.querySelector('.quantity-input');
        if (qtyInput) {
            qtyInput.addEventListener('input', () => validateRowStock(row));
            qtyInput.addEventListener('change', () => validateRowStock(row));
        }
        const unitSelect = row.querySelector('.unit-select');
        if (unitSelect) {
            unitSelect.addEventListener('change', () => validateRowStock(row));
        }
        configureProduct(row, true);
    });
    refreshGroup('source');
    refreshGroup('destination');
};

// Side-effect: register on window so Vite/Rollup won't tree-shake this module
window.initializeStockTransferForm = initializeStockTransferForm;

export {initializeStockTransferForm};
