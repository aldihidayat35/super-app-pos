@php
    $disabled = $disabled ?? false;
    $selectedList = collect(old('permissions', $selectedPermissions ?? []))->map(fn ($id) => (int) $id);
@endphp

<!-- TOOLBAR KONTROL CEPAT & LIVE SEARCH -->
<div class="card card-flush bg-light-primary border border-primary border-opacity-20 mb-6 shadow-sm">
    <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <!-- Input Live Search -->
        <div class="d-flex align-items-center position-relative my-1 flex-grow-1" style="max-width: 440px;">
            <i class="ki-outline ki-magnifier fs-2 position-absolute ms-4 text-gray-500"></i>
            <input type="text" id="permission-search-input" class="form-control form-control-solid ps-12 form-control-sm fs-7" placeholder="Cari hak akses (contoh: kasir, opname, stok, invoice, approval)..." autocomplete="off">
            <button type="button" id="permission-search-clear" class="btn btn-sm btn-icon btn-active-color-primary position-absolute end-0 me-2 d-none" title="Hapus pencarian">
                <i class="ki-outline ki-cross fs-4"></i>
            </button>
        </div>

        <!-- Global Action Buttons & Counters -->
        <div class="d-flex align-items-center flex-wrap gap-2">
            <span class="badge badge-light-primary fw-bold fs-7 px-3 py-2" id="global-permission-counter" title="Jumlah total hak akses yang sedang aktif">
                <i class="ki-outline ki-security-user me-1 text-primary"></i>
                <span id="global-selected-count">0</span> / <span id="global-total-count">0</span> Hak Akses Dipilih
            </span>

            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-light-info fw-semibold" id="btn-expand-all" title="Buka seluruh kelompok modul utama">
                    <i class="ki-outline ki-arrow-down-refraction me-1"></i> Buka Semua
                </button>
                <button type="button" class="btn btn-light-secondary fw-semibold" id="btn-collapse-all" title="Tutup seluruh kelompok modul utama">
                    <i class="ki-outline ki-arrow-up-refraction me-1"></i> Tutup Semua
                </button>
            </div>

            @if (!$disabled)
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-light-success fw-semibold" id="btn-select-all-global" title="Centang seluruh hak akses di semua modul">
                        <i class="ki-outline ki-check-square me-1"></i> Pilih Semua
                    </button>
                    <button type="button" class="btn btn-light-danger fw-semibold" id="btn-deselect-all-global" title="Batalkan seluruh centang">
                        <i class="ki-outline ki-close-square me-1"></i> Batal Semua
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- PESAN HASIL PENCARIAN KOSONG -->
<div id="permission-no-results" class="card card-flush bg-light-warning border border-warning border-opacity-30 p-8 text-center mb-6 d-none">
    <i class="ki-outline ki-search-list fs-3x text-warning mb-2"></i>
    <h4 class="text-gray-900 fw-bold mb-1">Tidak Ditemukan Hak Akses</h4>
    <p class="text-muted fs-7 mb-0">Tidak ada permission atau modul yang cocok dengan kata kunci pencarian Anda.</p>
</div>

<!-- ACCORDION 12 GRAND MODULES -->
<div class="accordion accordion-icon-toggle" id="kt_accordion_permissions">
    @foreach ($permissions as $grandKey => $grand)
        <div class="accordion-item card card-bordered shadow-sm mb-5 grand-module-item border border-gray-200" data-grand-key="{{ $grandKey }}">
            <!-- Accordion Header -->
            <div class="accordion-header card-header px-5 py-3 bg-light-light min-h-60px d-flex align-items-center justify-content-between flex-wrap gap-2" id="heading_{{ $grandKey }}">
                <div class="d-flex align-items-center cursor-pointer flex-grow-1 user-select-none" data-bs-toggle="collapse" data-bs-target="#collapse_{{ $grandKey }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="collapse_{{ $grandKey }}">
                    <div class="symbol symbol-40px me-3">
                        <span class="symbol-label bg-light-primary text-primary">
                            <i class="{{ $grand['icon'] }} fs-2"></i>
                        </span>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h3 class="fs-5 fw-bolder text-gray-900 mb-0 grand-title">{{ $grand['title'] }}</h3>
                            <span class="badge badge-light-primary fw-bold fs-8 grand-counter-badge" data-grand-counter="{{ $grandKey }}">
                                <span class="grand-selected-count">0</span> / {{ $grand['total_permissions'] }}
                            </span>
                        </div>
                        <div class="text-muted fs-8 grand-desc mt-0.5">{{ $grand['description'] }}</div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    @if (!$disabled)
                        <button type="button" class="btn btn-xs btn-light-primary btn-select-grand fw-semibold px-2 py-1" data-select-grand="{{ $grandKey }}" title="Centang semua hak akses di modul {{ $grand['title'] }}">
                            <i class="ki-outline ki-check-square fs-7 me-1"></i> Pilih Modul Ini
                        </button>
                        <button type="button" class="btn btn-xs btn-light-danger btn-deselect-grand fw-semibold px-2 py-1" data-deselect-grand="{{ $grandKey }}" title="Hapus centang di modul {{ $grand['title'] }}">
                            <i class="ki-outline ki-cross-circle fs-7 me-1"></i> Batal
                        </button>
                    @endif
                    <button class="accordion-button collapsed w-auto p-0 ms-2 bg-transparent shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_{{ $grandKey }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="collapse_{{ $grandKey }}">
                        <i class="ki-outline ki-down fs-2 text-gray-500 accordion-arrow"></i>
                    </button>
                </div>
            </div>

            <!-- Accordion Body -->
            <div id="collapse_{{ $grandKey }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" aria-labelledby="heading_{{ $grandKey }}">
                <div class="card-body p-5 bg-body">
                    <div class="row g-4">
                        @foreach ($grand['sub_modules'] as $subKey => $sub)
                            <div class="col-lg-4 col-md-6 sub-module-col" data-sub-key="{{ $subKey }}">
                                <div class="card card-bordered h-100 shadow-xs border border-gray-200 sub-module-card">
                                    <!-- Header Sub-Modul -->
                                    <div class="card-header min-h-45px px-4 py-2 bg-light d-flex align-items-center justify-content-between border-bottom">
                                        <div class="d-flex align-items-center gap-2 min-w-0">
                                            <i class="ki-outline ki-element-11 fs-6 text-primary flex-shrink-0"></i>
                                            <span class="fw-bold text-gray-800 fs-7 text-truncate sub-title" title="{{ $sub['label'] }}">
                                                {{ $sub['label'] }}
                                            </span>
                                            <span class="badge badge-light-secondary fs-9 px-1.5 py-0.5 sub-counter-badge" data-sub-counter="{{ $subKey }}">
                                                <span class="sub-selected-count">0</span>/{{ $sub['permissions']->count() }}
                                            </span>
                                        </div>
                                        @if (!$disabled)
                                            <button type="button" class="btn btn-icon btn-sm btn-light-primary w-24px h-24px btn-toggle-sub" data-toggle-sub="{{ $subKey }}" title="Pilih / Batal Semua di kelompok ini">
                                                <i class="ki-outline ki-check-square fs-6"></i>
                                            </button>
                                        @endif
                                    </div>

                                    <!-- Daftar Checkbox Permission -->
                                    <div class="card-body p-3">
                                        <div class="d-flex flex-column gap-2">
                                            @foreach ($sub['permissions'] as $permission)
                                                @php($isChecked = $selectedList->contains($permission->id))
                                                <label class="form-check form-check-custom form-check-solid border border-dashed rounded p-2.5 permission-item cursor-pointer {{ $isChecked ? 'bg-light-primary border-primary border-opacity-30' : 'bg-body' }}" data-permission-item data-name="{{ strtolower($permission->name) }}" data-label="{{ strtolower($permission->label ?: $permission->name) }}" data-desc="{{ strtolower($permission->description ?? '') }}">
                                                    <input
                                                        class="form-check-input permission-checkbox"
                                                        type="checkbox"
                                                        name="permissions[]"
                                                        value="{{ $permission->id }}"
                                                        data-grand="{{ $grandKey }}"
                                                        data-sub="{{ $subKey }}"
                                                        @checked($isChecked)
                                                        @disabled($disabled)
                                                    >
                                                    <span class="form-check-label ms-2 d-flex flex-column min-w-0">
                                                        <span class="fw-bold text-gray-800 fs-7 d-flex align-items-center gap-1">
                                                            <span class="permission-label-text">{{ $permission->label ?: $permission->name }}</span>
                                                            @if (!empty($permission->help_text))
                                                                @include('admin.roles._help-icon', ['text' => $permission->help_text])
                                                            @endif
                                                        </span>
                                                        <span class="text-muted fs-9 text-truncate-2 mt-0.5 permission-desc-text">
                                                            {{ $permission->description ?? $permission->name }}
                                                        </span>
                                                        <span class="text-gray-400 fs-9 font-monospace mt-0.5">
                                                            {{ $permission->name }}
                                                        </span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

@push('scripts')
<script>
(function() {
    function updateCounters() {
        let totalAll = 0;
        let selectedAll = 0;

        // Hitung Grand Modules
        document.querySelectorAll('.grand-module-item').forEach(grandEl => {
            const grandKey = grandEl.getAttribute('data-grand-key');
            const checkboxes = grandEl.querySelectorAll('.permission-checkbox');
            const total = checkboxes.length;
            let selected = 0;

            checkboxes.forEach(cb => {
                if (cb.checked) selected++;
            });

            totalAll += total;
            selectedAll += selected;

            const badge = grandEl.querySelector(`[data-grand-counter="${grandKey}"]`);
            if (badge) {
                const countSpan = badge.querySelector('.grand-selected-count');
                if (countSpan) countSpan.textContent = selected;

                if (selected > 0) {
                    badge.classList.remove('badge-light-secondary');
                    badge.classList.add('badge-light-success');
                } else {
                    badge.classList.remove('badge-light-success');
                    badge.classList.add('badge-light-secondary');
                }
            }
        });

        // Hitung Sub Modules
        document.querySelectorAll('.sub-module-col').forEach(subEl => {
            const subKey = subEl.getAttribute('data-sub-key');
            const checkboxes = subEl.querySelectorAll('.permission-checkbox');
            let selected = 0;

            checkboxes.forEach(cb => {
                if (cb.checked) selected++;
            });

            const badge = subEl.querySelector(`[data-sub-counter="${subKey}"]`);
            if (badge) {
                const countSpan = badge.querySelector('.sub-selected-count');
                if (countSpan) countSpan.textContent = selected;

                if (selected === checkboxes.length && checkboxes.length > 0) {
                    badge.classList.remove('badge-light-secondary', 'badge-light-primary');
                    badge.classList.add('badge-light-success');
                } else if (selected > 0) {
                    badge.classList.remove('badge-light-secondary', 'badge-light-success');
                    badge.classList.add('badge-light-primary');
                } else {
                    badge.classList.remove('badge-light-primary', 'badge-light-success');
                    badge.classList.add('badge-light-secondary');
                }
            }
        });

        // Hitung Global
        const globalSelectedEl = document.getElementById('global-selected-count');
        const globalTotalEl = document.getElementById('global-total-count');
        if (globalSelectedEl) globalSelectedEl.textContent = selectedAll;
        if (globalTotalEl) globalTotalEl.textContent = totalAll;
    }

    // Event listener checkbox change untuk styling aktif & update counter
    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('permission-checkbox')) {
            const label = e.target.closest('label.permission-item');
            if (label) {
                if (e.target.checked) {
                    label.classList.add('bg-light-primary', 'border-primary', 'border-opacity-30');
                    label.classList.remove('bg-body');
                } else {
                    label.classList.remove('bg-light-primary', 'border-primary', 'border-opacity-30');
                    label.classList.add('bg-body');
                }
            }
            updateCounters();
        }
    });

    // Tombol Pilih Semua di Modul Utama
    document.querySelectorAll('[data-select-grand]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const grandKey = this.getAttribute('data-select-grand');
            document.querySelectorAll(`.permission-checkbox[data-grand="${grandKey}"]`).forEach(cb => {
                if (!cb.disabled) {
                    cb.checked = true;
                    const label = cb.closest('label.permission-item');
                    if (label) {
                        label.classList.add('bg-light-primary', 'border-primary', 'border-opacity-30');
                        label.classList.remove('bg-body');
                    }
                }
            });
            updateCounters();
        });
    });

    // Tombol Batal Pilih di Modul Utama
    document.querySelectorAll('[data-deselect-grand]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const grandKey = this.getAttribute('data-deselect-grand');
            document.querySelectorAll(`.permission-checkbox[data-grand="${grandKey}"]`).forEach(cb => {
                if (!cb.disabled) {
                    cb.checked = false;
                    const label = cb.closest('label.permission-item');
                    if (label) {
                        label.classList.remove('bg-light-primary', 'border-primary', 'border-opacity-30');
                        label.classList.add('bg-body');
                    }
                }
            });
            updateCounters();
        });
    });

    // Tombol Toggle Sub-Modul Card
    document.querySelectorAll('[data-toggle-sub]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const subKey = this.getAttribute('data-toggle-sub');
            const checkboxes = document.querySelectorAll(`.permission-checkbox[data-sub="${subKey}"]:not(:disabled)`);
            
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            const targetState = !allChecked;

            checkboxes.forEach(cb => {
                cb.checked = targetState;
                const label = cb.closest('label.permission-item');
                if (label) {
                    if (targetState) {
                        label.classList.add('bg-light-primary', 'border-primary', 'border-opacity-30');
                        label.classList.remove('bg-body');
                    } else {
                        label.classList.remove('bg-light-primary', 'border-primary', 'border-opacity-30');
                        label.classList.add('bg-body');
                    }
                }
            });
            updateCounters();
        });
    });

    // Global: Buka Semua Accordion
    const btnExpandAll = document.getElementById('btn-expand-all');
    if (btnExpandAll) {
        btnExpandAll.addEventListener('click', function() {
            document.querySelectorAll('#kt_accordion_permissions .accordion-collapse').forEach(el => {
                const bsCollapse = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
                bsCollapse.show();
            });
        });
    }

    // Global: Tutup Semua Accordion
    const btnCollapseAll = document.getElementById('btn-collapse-all');
    if (btnCollapseAll) {
        btnCollapseAll.addEventListener('click', function() {
            document.querySelectorAll('#kt_accordion_permissions .accordion-collapse').forEach(el => {
                const bsCollapse = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
                bsCollapse.hide();
            });
        });
    }

    // Global: Pilih Semua Hak Akses
    const btnSelectAllGlobal = document.getElementById('btn-select-all-global');
    if (btnSelectAllGlobal) {
        btnSelectAllGlobal.addEventListener('click', function() {
            document.querySelectorAll('.permission-checkbox:not(:disabled)').forEach(cb => {
                cb.checked = true;
                const label = cb.closest('label.permission-item');
                if (label) {
                    label.classList.add('bg-light-primary', 'border-primary', 'border-opacity-30');
                    label.classList.remove('bg-body');
                }
            });
            updateCounters();
        });
    }

    // Global: Batal Semua Hak Akses
    const btnDeselectAllGlobal = document.getElementById('btn-deselect-all-global');
    if (btnDeselectAllGlobal) {
        btnDeselectAllGlobal.addEventListener('click', function() {
            document.querySelectorAll('.permission-checkbox:not(:disabled)').forEach(cb => {
                cb.checked = false;
                const label = cb.closest('label.permission-item');
                if (label) {
                    label.classList.remove('bg-light-primary', 'border-primary', 'border-opacity-30');
                    label.classList.add('bg-body');
                }
            });
            updateCounters();
        });
    }

    // Live Search Filter
    const searchInput = document.getElementById('permission-search-input');
    const searchClear = document.getElementById('permission-search-clear');
    const noResultsEl = document.getElementById('permission-no-results');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();

            if (searchClear) {
                if (query.length > 0) {
                    searchClear.classList.remove('d-none');
                } else {
                    searchClear.classList.add('d-none');
                }
            }

            let anyGrandVisible = false;

            document.querySelectorAll('.grand-module-item').forEach(grandEl => {
                const grandTitle = (grandEl.querySelector('.grand-title')?.textContent || '').toLowerCase();
                const grandDesc = (grandEl.querySelector('.grand-desc')?.textContent || '').toLowerCase();
                const grandMatches = query === '' || grandTitle.includes(query) || grandDesc.includes(query);

                let anySubInGrandVisible = false;

                grandEl.querySelectorAll('.sub-module-col').forEach(subCol => {
                    const subTitle = (subCol.querySelector('.sub-title')?.textContent || '').toLowerCase();
                    const subMatches = grandMatches || subTitle.includes(query);

                    let anyPermissionInSubVisible = false;

                    subCol.querySelectorAll('.permission-item').forEach(permItem => {
                        const pName = permItem.getAttribute('data-name') || '';
                        const pLabel = permItem.getAttribute('data-label') || '';
                        const pDesc = permItem.getAttribute('data-desc') || '';

                        const isMatch = query === '' || subMatches || pName.includes(query) || pLabel.includes(query) || pDesc.includes(query);

                        if (isMatch) {
                            permItem.classList.remove('d-none');
                            anyPermissionInSubVisible = true;
                        } else {
                            permItem.classList.add('d-none');
                        }
                    });

                    if (anyPermissionInSubVisible) {
                        subCol.classList.remove('d-none');
                        anySubInGrandVisible = true;
                    } else {
                        subCol.classList.add('d-none');
                    }
                });

                if (anySubInGrandVisible) {
                    grandEl.classList.remove('d-none');
                    anyGrandVisible = true;

                    // Otomatis buka accordion jika sedang mencari kata kunci
                    if (query.length > 1) {
                        const collapseEl = grandEl.querySelector('.accordion-collapse');
                        if (collapseEl) {
                            const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
                            bsCollapse.show();
                        }
                    }
                } else {
                    grandEl.classList.add('d-none');
                }
            });

            if (noResultsEl) {
                if (!anyGrandVisible && query.length > 0) {
                    noResultsEl.classList.remove('d-none');
                } else {
                    noResultsEl.classList.add('d-none');
                }
            }
        });

        if (searchClear) {
            searchClear.addEventListener('click', function() {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
                searchInput.focus();
            });
        }
    }

    // Inisialisasi hitungan pertama kali
    updateCounters();
})();
</script>
@endpush
