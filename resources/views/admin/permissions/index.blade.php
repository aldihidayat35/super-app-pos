@extends('layouts.metronic.app')

@section('title', 'Daftar Permission - ' . config('app.name'))
@section('page_title', 'Daftar Permission')

@section('page_guide')
    <x-metronic.page-guide id="admin-permissions" title="Panduan Halaman Daftar Permission">
        <x-slot:function>
            <p>Halaman ini menampilkan daftar permission yang terdaftar dalam sistem RBAC. Permission mendefinisikan aksi yang dapat dilakukan pengguna pada setiap modul seperti create, read, update, delete, dan permission khusus.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Cari permission atau filter berdasarkan modul.</li><li>Tabel menampilkan semua permission beserta grup, deskripsi, dan role terkait.</li><li>Permission dibuat oleh seeder atau modul manajemen role.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Cari permission:</strong> kolom pencarian nama permission.</li><li><strong>Filter Modul:</strong> filter berdasarkan modul sistem.</li><li><strong>Permission:</strong> nama unik permission.</li><li><strong>Label:</strong> nama tampilan permission.</li><li><strong>Group:</strong> modul atau nama grup permission.</li><li><strong>Aksi:</strong> tipe action create/read/update/delete.</li><li><strong>Deskripsi:</strong> penjelasan kegunaan permission.</li><li><strong>Guard:</strong> guard authentication.</li><li><strong>Role Terkait:</strong> jumlah role yang memiliki permission ini.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Permission digunakan dalam role. Role mendapat permission, lalu diassign ke user.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Gunakan filter modul untuk melihat permission tertentu.</li><li>Periksa role terkait untuk memastikan permission sudah dipakai.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Jangan hapus permission yang masih dipakai role aktif.</li><li>Gunakan seeder untuk menambah permission baru.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Modul warehouse memiliki permission stock.create, stock.read, stock.update. Role Staff Gudang mendapat stock.create dan stock.read.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" action="{{ route('admin.permissions.index') }}" class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
            <div class="d-flex flex-wrap gap-3">
                <input type="search" name="q" value="{{ $search }}" class="form-control form-control-solid w-250px" placeholder="Cari permission...">
                <select name="module" class="form-select form-select-solid w-225px">
                    <option value="">Semua Modul</option>
                    @foreach ($modules as $item)
                        <option value="{{ $item }}" @selected($module === $item)>{{ $item }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-light-primary"><i class="ki-outline ki-magnifier"></i> Filter</button>
        </form>

        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead>
                    <tr class="text-muted fw-bold text-uppercase fs-7">
                        <th>Permission</th>
                        <th>Label</th>
                        <th>Group</th>
                        <th>Aksi</th>
                        <th>Deskripsi</th>
                        <th>Guard</th>
                        <th>Role Terkait</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($permissions as $permission)
                        <tr>
                            <td class="fw-bold text-gray-900">{{ $permission->name }}</td>
                            <td>{{ $permission->label ?: '-' }}</td>
                            <td>{{ $permission->module ?: (explode('.', $permission->name)[0] ?? '-') }}</td>
                            <td>{{ $permission->action ?: '-' }}</td>
                            <td class="text-muted">{{ $permission->description ?: '-' }}</td>
                            <td><span class="badge badge-light">{{ $permission->guard_name }}</span></td>
                            <td>{{ $permission->roles_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-metronic.empty-state title="Belum ada permission" description="Permission akan tampil setelah dibuat oleh seeder atau modul terkait." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $permissions->links() }}
    </x-metronic.card>
@endsection
