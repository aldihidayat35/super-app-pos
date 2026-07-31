import * as bootstrap from 'bootstrap';
import $ from 'jquery';
import Swal from 'sweetalert2';
import flatpickr from 'flatpickr';
import DataTable from 'datatables.net-bs5';
import installSelect2 from 'select2';

window.bootstrap = bootstrap;
window.jQuery = window.$ = $;
window.Swal = Swal;
window.flatpickr = flatpickr;
window.DataTable = DataTable;

if (typeof $.fn.select2 !== 'function') {
    installSelect2(window, $);
}

if (typeof $.fn.select2 === 'function') {
    $.fn.select2.defaults.set('theme', 'bootstrap5');
    $.fn.select2.defaults.set('width', '100%');
    $.fn.select2.defaults.set('selectionCssClass', ':all:');
}

window.metronicReady = import('./vendor/metronic/scripts.bundle.js').catch((error) => {
    console.error('Runtime Metronic gagal dimuat.', error);
});
