import './vendor';
import './bootstrap';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const loadingOverlay = document.querySelector('[data-app-loading]');

if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

window.appFetch = (input, options = {}) => fetch(input, {
    ...options,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        ...options.headers,
    },
});

window.AppLoading = {
    show: () => loadingOverlay?.classList.add('is-visible'),
    hide: () => loadingOverlay?.classList.remove('is-visible'),
};

const initializeTheme = () => {
    const storedTheme = localStorage.getItem('gudangtoko-theme') || 'light';
    document.documentElement.setAttribute('data-bs-theme', storedTheme);

    document.querySelectorAll('[data-theme-value]').forEach((button) => {
        button.addEventListener('click', () => {
            const theme = button.dataset.themeValue;
            localStorage.setItem('gudangtoko-theme', theme);
            document.documentElement.setAttribute('data-bs-theme', theme);
        });
    });
};

const searchableSelectSelector = [
    'select[data-control="select2"]',
    'select[data-kt-select2="true"]',
    'select.form-select:not([data-control="native"]):not([data-searchable="false"])',
].join(', ');

const select2Language = {
    errorLoading: () => 'Data tidak dapat dimuat.',
    inputTooLong: () => 'Kata pencarian terlalu panjang.',
    inputTooShort: () => 'Ketik kata untuk mencari.',
    loadingMore: () => 'Memuat data berikutnya...',
    maximumSelected: () => 'Pilihan sudah mencapai batas maksimum.',
    noResults: () => 'Data tidak ditemukan.',
    searching: () => 'Mencari data...',
};

const select2ElementsWithin = (root) => {
    const elements = [];

    if (root instanceof Element && root.matches(searchableSelectSelector)) {
        elements.push(root);
    }

    if (typeof root?.querySelectorAll === 'function') {
        elements.push(...root.querySelectorAll(searchableSelectSelector));
    }

    return [...new Set(elements)];
};

const initializeSelect2 = (root = document) => {
    if (typeof window.$?.fn?.select2 !== 'function') return;

    select2ElementsWithin(root).forEach((element) => {
        const select = window.$(element);

        if (select.hasClass('select2-hidden-accessible')) return;

        const configuredParent = element.dataset.dropdownParent
            ? document.querySelector(element.dataset.dropdownParent)
            : null;
        const parent = configuredParent || element.closest('.modal, .offcanvas');
        const emptyOption = Array.from(element.options).find((option) => option.value === '');
        const fixedWidthClass = Array.from(element.classList)
            .find((className) => /^w-(?:auto|\d+(?:px)?)$/.test(className) && className !== 'w-100');
        const width = element.dataset.select2Width
            || (fixedWidthClass ? window.getComputedStyle(element).width : '100%');

        select.select2({
            theme: 'bootstrap5',
            selectionCssClass: ':all:',
            dropdownParent: parent ? window.$(parent) : undefined,
            placeholder: element.dataset.placeholder || emptyOption?.text?.trim() || 'Pilih opsi',
            allowClear: element.dataset.allowClear === 'true',
            closeOnSelect: element.dataset.closeOnSelect !== 'false',
            minimumResultsForSearch: 0,
            language: select2Language,
            width,
        });
        element.setAttribute('data-kt-initialized', '1');
        element.setAttribute('data-searchable-initialized', 'true');
    });
};

let select2Observer;

const observeDynamicSelect2 = () => {
    if (!document.body || select2Observer) return;

    select2Observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node instanceof Element) initializeSelect2(node);
            });
        });
    });

    select2Observer.observe(document.body, { childList: true, subtree: true });
};

window.GudangTokoSelect2 = {
    initialize: initializeSelect2,
    selector: searchableSelectSelector,
};

const closeSearchableSelect = (wrapper) => {
    wrapper.classList.remove('is-open');
    wrapper.querySelector('.searchable-select-toggle')?.setAttribute('aria-expanded', 'false');
};

const initializeSearchableSelectFallbacks = () => {
    document.querySelectorAll('select[data-searchable-fallback="true"]').forEach((select) => {
        if (select.classList.contains('select2-hidden-accessible') || select.dataset.searchableInitialized === 'true') return;

        select.dataset.searchableInitialized = 'true';
        select.classList.add('searchable-select-native');

        const wrapper = document.createElement('div');
        wrapper.className = 'searchable-select';

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'searchable-select-toggle form-select form-select-solid text-start';
        toggle.setAttribute('aria-haspopup', 'listbox');
        toggle.setAttribute('aria-expanded', 'false');

        const dropdown = document.createElement('div');
        dropdown.className = 'searchable-select-dropdown';

        const searchWrapper = document.createElement('div');
        searchWrapper.className = 'searchable-select-search';

        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'form-control form-control-solid';
        search.placeholder = 'Ketik untuk mencari...';
        search.autocomplete = 'off';
        search.setAttribute('aria-label', `Cari ${select.dataset.placeholder || 'pilihan'}`);

        const options = document.createElement('div');
        options.className = 'searchable-select-options';
        options.setAttribute('role', 'listbox');

        const empty = document.createElement('div');
        empty.className = 'searchable-select-empty text-muted';
        empty.textContent = 'Data tidak ditemukan.';

        const optionButtons = Array.from(select.options).map((option) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'searchable-select-option';
            button.dataset.value = option.value;
            button.dataset.search = option.text.toLocaleLowerCase('id-ID');
            button.textContent = option.text;
            button.disabled = option.disabled;
            button.setAttribute('role', 'option');

            button.addEventListener('click', () => {
                select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                updateSelection();
                closeSearchableSelect(wrapper);
                toggle.focus();
            });

            options.appendChild(button);
            return button;
        });

        const updateSelection = () => {
            const selected = select.options[select.selectedIndex];
            const placeholder = select.dataset.placeholder || 'Pilih opsi';
            toggle.textContent = selected?.value ? selected.text : placeholder;
            toggle.classList.toggle('text-muted', !selected?.value);

            optionButtons.forEach((button) => {
                const selectedOption = button.dataset.value === select.value;
                button.classList.toggle('is-selected', selectedOption);
                button.setAttribute('aria-selected', selectedOption ? 'true' : 'false');
            });
        };

        const filterOptions = () => {
            const keyword = search.value.trim().toLocaleLowerCase('id-ID');
            let visibleCount = 0;

            optionButtons.forEach((button) => {
                const visible = button.dataset.search.includes(keyword);
                button.hidden = !visible;
                if (visible) visibleCount += 1;
            });

            empty.classList.toggle('d-none', visibleCount > 0);
        };

        toggle.addEventListener('click', () => {
            const willOpen = !wrapper.classList.contains('is-open');
            document.querySelectorAll('.searchable-select.is-open').forEach(closeSearchableSelect);
            wrapper.classList.toggle('is-open', willOpen);
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

            if (willOpen) {
                search.value = '';
                filterOptions();
                window.requestAnimationFrame(() => search.focus());
            }
        });

        search.addEventListener('input', filterOptions);
        search.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeSearchableSelect(wrapper);
                toggle.focus();
            }
        });

        searchWrapper.appendChild(search);
        dropdown.append(searchWrapper, options, empty);
        wrapper.append(toggle, dropdown);
        select.insertAdjacentElement('afterend', wrapper);
        select.addEventListener('change', updateSelection);
        updateSelection();
        filterOptions();
    });

    if (document.body.dataset.searchableSelectListener !== 'true') {
        document.body.dataset.searchableSelectListener = 'true';
        document.addEventListener('click', (event) => {
            document.querySelectorAll('.searchable-select.is-open').forEach((wrapper) => {
                if (!wrapper.contains(event.target)) closeSearchableSelect(wrapper);
            });
        });
    }
};

const initializeDatePickers = () => {
    document.querySelectorAll('[data-datepicker]').forEach((element) => {
        window.flatpickr(element, {
            allowInput: true,
            dateFormat: element.dataset.dateFormat || 'd/m/Y',
            locale: { firstDayOfWeek: 1 },
        });
    });
};

const initializeCurrencyInputs = () => {
    document.querySelectorAll('[data-currency-input]').forEach((input) => {
        const format = () => {
            const digits = input.value.replace(/[^0-9]/g, '');
            const target = input.dataset.currencyTarget ? document.querySelector(input.dataset.currencyTarget) : null;
            if (target) target.value = digits;
            input.value = digits ? new Intl.NumberFormat('id-ID').format(digits) : '';
        };
        input.addEventListener('input', format);
        format();
    });
};

const initializeDataTables = () => {
    document.querySelectorAll('[data-datatable]').forEach((table) => {
        if (window.DataTable.isDataTable(table)) return;

        table.appDataTable = new window.DataTable(table, {
            processing: true,
            serverSide: table.dataset.serverSide === 'true',
            ajax: table.dataset.source || undefined,
            responsive: true,
            pageLength: Number(table.dataset.pageLength || 10),
            language: {
                emptyTable: 'Belum ada data.',
                info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
                infoEmpty: 'Tidak ada data',
                lengthMenu: 'Tampilkan _MENU_',
                processing: 'Memuat data...',
                search: 'Cari:',
                zeroRecords: 'Data tidak ditemukan.',
                paginate: { next: 'Berikutnya', previous: 'Sebelumnya' },
            },
        });
    });
};

const initializeTableSearch = () => {
    document.querySelectorAll('[data-table-search]').forEach((input) => {
        input.addEventListener('input', () => {
            const card = input.closest('.card');
            const table = card?.querySelector('[data-datatable]');
            if (table?.appDataTable) {
                table.appDataTable.search(input.value).draw();
            }
        });
    });
};

const initializeModalSubmissions = () => {
    document.querySelectorAll('[data-modal-submit-form]').forEach((button) => {
        button.addEventListener('click', () => document.getElementById(button.dataset.modalSubmitForm)?.submit());
    });
};

const initializeConfirmations = () => {
    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-confirm]');
        if (!trigger) return;

        event.preventDefault();
        const result = await window.Swal.fire({
            title: trigger.dataset.confirmTitle || 'Konfirmasi tindakan',
            text: trigger.dataset.confirmText || 'Tindakan ini akan diproses.',
            icon: trigger.dataset.confirmIcon || 'warning',
            showCancelButton: true,
            confirmButtonText: trigger.dataset.confirmButton || 'Ya, lanjutkan',
            cancelButtonText: 'Batal',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-primary',
                cancelButton: 'btn btn-light ms-3',
            },
        });

        if (!result.isConfirmed) return;

        const formId = trigger.dataset.confirmForm;
        if (formId) document.getElementById(formId)?.submit();
        else if (trigger.href) window.location.assign(trigger.href);
    });
};

const initializeSidebarToggle = () => {
    const toggle = document.getElementById('kt_app_sidebar_mobile_toggle');
    const sidebar = document.getElementById('kt_app_sidebar');
    toggle?.addEventListener('click', () => sidebar?.classList.toggle('drawer-on'));
};

const initializeApplication = () => {
    initializeTheme();
    initializeSelect2();
    observeDynamicSelect2();
    initializeSearchableSelectFallbacks();
    initializeDatePickers();
    initializeCurrencyInputs();
    initializeDataTables();
    initializeTableSearch();
    initializeModalSubmissions();
    initializeConfirmations();
    initializeSidebarToggle();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeApplication, { once: true });
} else {
    initializeApplication();
}

window.addEventListener('pageshow', () => window.AppLoading.hide());
document.addEventListener('submit', (event) => {
    if (!event.target.hasAttribute('data-no-loading')) window.AppLoading.show();
});
