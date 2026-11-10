@extends('layouts.metronic.app')

@section('title', 'Daftar Role - ' . config('app.name'))
@section('page_title', 'Daftar Role')

@section('page_guide')
    <x-metronic.page-guide id="admin-roles" title="Panduan Halaman Daftar Role">
        <x-slot:function>
            <p>Halaman ini mengelola role pengguna untuk sistem RBAC. Super Admin menggunakan halaman ini untuk menambah, mengedit, dan menyalin role beserta permission-nya.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Cari role berdasarkan nama atau label.</li><li>Role sistem tidak dapat diedit atau dihapus.</li><li>Role kustom dapat disalin untuk membuat role baru mirip.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Cari role:</strong> kolom pencarian.</li><li><strong>Role/Label:</strong> nama tampilan dan internal role.</li><li><strong>Guard:</strong> guard authentication role.</li><li><strong>Status:</strong> Sistem (readonly) atau Kustom.</li><li><strong>Pengguna:</strong> jumlah user dengan role ini.</li><li><strong>Permission:</strong> jumlah permission role ini.</li><li><strong>Detail:</strong> membuka halaman rincian role.</li><li><strong>Edit:</strong> mengubah role kustom.</li><li><strong>Salin:</strong> membuat role baru dari role ini.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Role ditentukan saat assign user. Permission role memengaruhi akses halaman dan tombol.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Cari role.</li><li>Klik Detail untuk melihat permission.</li><li>Klik Salin untuk duplikasi role kustom.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Role sistem tidak dapat diedit atau dihapus.</li><li>Duplikasi role menyalin semua permission.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Role "Staff Gudang" kustom dengan 10 permission. Disalin jadi "Staff Gudang Cabang B".</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <x-metronic.permission-button permission="admin.roles.create" :href="route('admin.roles.create')" icon="ki-outline ki-plus">
        Tambah Role
    </x-metronic.permission-button>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" action="{{ route('admin.roles.index') }}" class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
            <input type="search" name="q" value="{{ $search }}" class="form-control form-control-solid w-250px" placeholder="Cari role...">
            <button type="submit" class="btn btn-light-primary"><i class="ki-outline ki-magnifier"></i> Filter</button>
        </form>

        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead>
                    <tr class="text-muted fw-bold text-uppercase fs-7">
                        <th>Role</th>
                        <th>Guard</th>
                        <th>Status</th>
                        <th>Pengguna</th>
                        <th>Permission</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr>
                            <td>
                                <a href="{{ route('admin.roles.show', $role) }}" class="fw-bold text-gray-900 text-hover-primary">{{ $role->label ?: str_replace('_', ' ', $role->name) }}</a>
                                <div class="text-muted fs-7">{{ $role->name }}</div>
                            </td>
                            <td><span class="badge badge-light">{{ $role->guard_name }}</span></td>
                            <td><span class="badge {{ $role->is_system ? 'badge-light-info' : 'badge-light-secondary' }}">{{ $role->is_system ? 'Sistem' : 'Kustom' }}</span></td>
                            <td>{{ $role->users_count }}</td>
                            <td>{{ $role->permissions_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.roles.show', $role) }}" class="btn btn-sm btn-light">Detail</a>
                                @can('update', $role)
                                    <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-light-primary">Edit</a>
                                @endcan
                                @can('create', \Spatie\Permission\Models\Role::class)
                                    <form method="POST" action="{{ route('admin.roles.duplicate', $role) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-light" title="Duplikasi role ini">Salin</button>
                                    </form>
                                @endcan
                                @if($role->name !== 'super_admin' && auth()->user()?->hasRole('super_admin'))
                                    @if((int) $role->users_count === 0)
                                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline" id="delete-role-{{ $role->id }}">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-icon btn-light-danger"
                                                data-confirm
                                                data-confirm-form="delete-role-{{ $role->id }}"
                                                data-confirm-title="Hapus role ini?"
                                                data-confirm-text="Role '{{ $role->label ?: $role->name }}' akan dihapus permanen dari sistem."
                                                data-confirm-button="Ya, hapus role"
                                                title="Hapus Role {{ $role->label ?: $role->name }}"
                                                aria-label="Hapus role {{ $role->label ?: $role->name }}"
                                            >
                                                <i class="ki-outline ki-trash fs-5"></i>
                                            </button>
                                        </form>
                                    @else
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-icon btn-light-secondary opacity-50"
                                            title="Role tidak dapat dihapus karena masih digunakan oleh {{ $role->users_count }} pengguna"
                                            disabled
                                        >
                                            <i class="ki-outline ki-trash fs-5"></i>
                                        </button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-metronic.empty-state title="Belum ada role" description="Role akan tampil setelah tersedia di RBAC." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $roles->links() }}
    </x-metronic.card>
@endsection
