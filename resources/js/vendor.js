import * as bootstrap from 'bootstrap';
import $ from 'jquery';
import Swal from 'sweetalert2';
import flatpickr from 'flatpickr';
import DataTable from 'datatables.net-bs5';
import 'select2';

window.bootstrap = bootstrap;
window.jQuery = window.$ = $;
window.Swal = Swal;
window.flatpickr = flatpickr;
window.DataTable = DataTable;

await import('./vendor/metronic/scripts.bundle.js');
