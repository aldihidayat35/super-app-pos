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

const initializeSelect2 = () => {
    window.$('[data-control="select2"]').each(function () {
        const parent = this.closest('.modal, .offcanvas');
        window.$(this).select2({
            dropdownParent: parent ? window.$(parent) : undefined,
            placeholder: this.dataset.placeholder || 'Pilih opsi',
            allowClear: this.dataset.allowClear === 'true',
            closeOnSelect: this.dataset.closeOnSelect !== 'false',
            width: '100%',
        });
    });
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

document.addEventListener('DOMContentLoaded', () => {
    initializeTheme();
    initializeSelect2();
    initializeDatePickers();
    initializeCurrencyInputs();
    initializeDataTables();
    initializeTableSearch();
    initializeModalSubmissions();
    initializeConfirmations();
    initializeSidebarToggle();
});

window.addEventListener('pageshow', () => window.AppLoading.hide());
document.addEventListener('submit', (event) => {
    if (!event.target.hasAttribute('data-no-loading')) window.AppLoading.show();
});
