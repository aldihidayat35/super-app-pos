@extends('layouts.metronic.app')

@section('title', 'Daftar Role - ' . config('app.name'))
@section('page_title', 'Daftar Role')

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
                                        <button type="submit" class="btn btn-sm btn-light">Salin</button>
                                    </form>
                                @endcan
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
