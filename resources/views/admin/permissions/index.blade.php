@extends('layouts.metronic.app')

@section('title', 'Daftar Permission - ' . config('app.name'))
@section('page_title', 'Daftar Permission')

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
