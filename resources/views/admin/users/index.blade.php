@extends('layouts.metronic.app')

@section('title', 'Daftar Pengguna - ' . config('app.name'))
@section('page_title', 'Daftar Pengguna')

@section('toolbar_actions')
    <x-metronic.permission-button permission="admin.users.export" :href="route('admin.users.export', request()->query())" variant="light" icon="ki-outline ki-file-down">
        Export
    </x-metronic.permission-button>
    <x-metronic.permission-button permission="admin.users.create" :href="route('admin.users.create')" icon="ki-outline ki-plus">
        Tambah Pengguna
    </x-metronic.permission-button>
@endsection

@section('content')
    <x-metronic.card>
        {{-- Filter Form --}}
        <form method="GET" action="{{ route('admin.users.index') }}" class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
            <div class="d-flex flex-wrap gap-3">
                <input type="search" name="q" value="{{ $search }}" class="form-control form-control-solid w-250px" placeholder="Cari nama, username, email...">
                <select name="role" class="form-select form-select-solid w-200px">
                    <option value="">Semua Role</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected((string) $roleFilter === (string) $role->id)>{{ $role->label ?: str_replace('_', ' ', $role->name) }}</option>
                    @endforeach
                </select>
                <select name="location" class="form-select form-select-solid w-225px">
                    <option value="">Semua Lokasi</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected((string) $locationFilter === (string) $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-select form-select-solid w-175px">
                    <option value="">Semua Status</option>
                    <option value="active" @selected($status === 'active')>Aktif</option>
                    <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <button type="submit" class="btn btn-light-primary"><i class="ki-outline ki-magnifier"></i> Terapkan Filter</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-light-secondary"><i class="ki-outline ki-cross"></i> Reset</a>
        </form>

        {{-- DataTables Container --}}
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6" id="usersDatatable">
                <thead>
                    <tr class="text-muted fw-bold text-uppercase fs-7">
                        <th class="min-w-200px">Pengguna</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Lokasi Utama</th>
                        <th>Status</th>
                        <th>Login Terakhir</th>
                        <th class="text-end" style="width: 150px">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-metronic.card>

@push('scripts')
<script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.13.11/js/dataTables.bootstrap5.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js" crossorigin="anonymous"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof $.fn.DataTable === 'undefined') {
        console.warn('jQuery DataTables library not loaded.');
        return;
    }

    // Preserve filter params from initial render
    var filterParams = {
        q        : '{{ $search ?? "" }}',
        role     : '{{ $roleFilter ?? "" }}',
        location : '{{ $locationFilter ?? "" }}',
        status   : '{{ $status ?? "" }}',
    };

    var table = $('#usersDatatable').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        ajax: function (data, callback, settings) {
            var params = $.extend({}, filterParams, {
                draw:          data.draw,
                start:         data.start,
                length:        data.length,
                'search.search': data.search.value,
                columns:       data.columns,
                order:         data.order,
            });

            $.ajax({
                url: '{{ route("admin.users.datatable") }}',
                type: 'GET',
                data: params,
                dataType: 'json',
                success: function (json) {
                    // Map server rows to column render
                    var rows = json.data.map(function (row) {
                        return [
                            row.name,
                            row.email,
                            row.roles,
                            row.location,
                            row.status,
                            row.login_at,
                            row.action
                        ];
                    });

                    callback({
                        draw:            json.draw,
                        recordsTotal:    json.recordsTotal,
                        recordsFiltered: json.recordsFiltered,
                        data:            rows,
                    });
                },
            });
        },
        columns: [
            { data: null, orderable: false, render: function (data, type, row) { return data[0]; } },
            { data: null, render: function (data) { return data[1]; } },
            { data: null, render: function (data) { return data[2]; } },
            { data: null, render: function (data) { return data[3]; } },
            { data: null, render: function (data) { return data[4]; } },
            { data: null, render: function (data) { return data[5]; } },
            { data: null, orderable: false, searchable: false, render: function (data) { return data[6]; } },
        ],
        columnDefs: [
            { targets: [0, 1], orderable: true, searchable: true, render: function (data, type, row) {
                // Prevent XSS on sorting column
                return type === 'display' ? data : data.replace(/<[^>]*>/g, '');
            }},
        ],
        order: [[0, 'desc']],
        pageLength: 15,
        lengthMenu: [[10, 15, 25, 50, 100, -1], [10, 15, 25, 50, 100, 'Semua']],
        dom:
            '<"d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"<"d-flex gap-2"f><"d-flex gap-2"l>>' +
            't' +
            '<"d-flex justify-content-between align-items-center mt-3"<"text-muted fs-7"i><"pagination pagination-outline"p>>',
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.11/i18n/id.json',
            processing: '<div class="overlay-layer"><div class="spinner-border text-secondary fs-2"></div></div>',
            emptyTable: 'Tidak ada data pengguna',
            search: 'Cari cepat:',
            lengthMenu: 'Tampil _MENU_ data/halaman',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            paginate: { first: 'Pertama', last: 'Terakhir', next: 'Berikutnya', previous: 'Sebelumnya' },
        },
        initComplete: function () {
            if (typeof ktScrollbar !== 'undefined') {
                $(this).closest('.table-responsive').each(function () {
                    ktScrollbar.init(this, { direction: 'horizontal', scrollable: true, offset: { size: 15 } });
                });
            }
        },
    });
});
</script>
@endpush

@endsection
