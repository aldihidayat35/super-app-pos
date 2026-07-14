@extends('layouts.metronic.app')

@section('title', 'Detail Role - ' . config('app.name'))
@section('page_title', 'Detail Role')

@section('toolbar_actions')
    @php($canDeleteRole = auth()->user()?->hasRole('super_admin') && ! (bool) $role->is_system && $role->users->count() === 0)

    @can('create', \Spatie\Permission\Models\Role::class)
        <form method="POST" action="{{ route('admin.roles.duplicate', $role) }}">
            @csrf
            <button type="submit" class="btn btn-light"><i class="ki-outline ki-copy"></i> Salin Role</button>
        </form>
    @endcan
    @can('update', $role)
        <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-primary"><i class="ki-outline ki-pencil"></i> Edit Role</a>
    @endcan
    @if ($canDeleteRole)
        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" id="delete-role-{{ $role->id }}">
            @csrf
            @method('DELETE')
            <button
                type="submit"
                class="btn btn-light-danger"
                data-confirm
                data-confirm-form="delete-role-{{ $role->id }}"
                data-confirm-title="Hapus role ini?"
                data-confirm-text="Role kustom ini akan dihapus permanen. Pastikan tidak ada pengguna yang masih membutuhkan role ini."
                data-confirm-button="Ya, hapus role"
            >
                <i class="ki-outline ki-cross-circle"></i> Hapus Role
            </button>
        </form>
    @endif
@endsection

@section('content')
    <x-metronic.page-title
        title="Detail Role"
        description="Lihat pengguna yang memakai role ini dan atur hak aksesnya."
        help="Role adalah kelompok hak akses. Semua pengguna yang memakai role ini akan mengikuti daftar permission yang dicentang di halaman ini."
        class="mb-5"
    />

    <div class="row g-6">
        <div class="col-lg-4">
            <x-metronic.card title="Informasi Role">
                <div class="mb-4"><div class="text-muted fs-7">Nama Role</div><div class="fw-bold fs-4">{{ $role->label ?: str_replace('_', ' ', $role->name) }}</div><div class="text-muted">{{ $role->name }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Guard</div><span class="badge badge-light">{{ $role->guard_name }}</span></div>
                <div class="mb-4"><div class="text-muted fs-7">Status Role</div><span class="badge {{ $role->is_system ? 'badge-light-info' : 'badge-light-secondary' }}">{{ $role->is_system ? 'Sistem' : 'Kustom' }}</span></div>
                <div class="mb-4"><div class="text-muted fs-7">Deskripsi</div><div class="fw-semibold">{{ $role->description ?: '-' }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Jumlah Pengguna</div><div class="fw-semibold">{{ $role->users->count() }}</div></div>
                <div><div class="text-muted fs-7">Jumlah Permission</div><div class="fw-semibold">{{ $role->permissions->count() }}</div></div>
            </x-metronic.card>

            <x-metronic.card title="Pengguna dengan Role Ini" class="mt-6">
                @forelse ($role->users as $user)
                    <div class="d-flex justify-content-between align-items-center border rounded p-3 mb-3">
                        <div>
                            <div class="fw-bold">{{ $user->name }}</div>
                            <div class="text-muted fs-7">{{ $user->email }}</div>
                        </div>
                        <a href="{{ route('admin.users.show', $user) }}" class="btn btn-sm btn-light">Detail</a>
                    </div>
                @empty
                    <x-metronic.empty-state title="Belum ada pengguna" description="Belum ada pengguna yang memakai role ini." />
                @endforelse
            </x-metronic.card>
        </div>

        <div class="col-lg-8">
            <x-metronic.card title="Matriks Permission">
                <form method="POST" action="{{ route('admin.roles.permissions.update', $role) }}">
                    @csrf
                    @method('PUT')

                    @foreach ($permissions as $group => $groupPermissions)
                        <div class="mb-8">
                            <h4 class="fw-bold text-gray-900 mb-4 d-flex align-items-center">
                                {{ strtoupper($group) }}
                                @include('admin.roles._help-icon', ['text' => 'Bagian ini berisi hak akses untuk satu area kerja. Centang berarti role ini boleh melakukan tindakan tersebut.'])
                            </h4>
                            <div class="row g-3">
                                @foreach ($groupPermissions as $permission)
                                    <div class="col-md-6">
                                        <label class="form-check form-check-custom form-check-solid border rounded p-3 h-100">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(collect(old('permissions', $selectedPermissions))->contains($permission->id)) @disabled(! auth()->user()?->can('updatePermissions', $role))>
                                            <span class="form-check-label ms-2">
                                                <span class="fw-semibold d-flex align-items-center">
                                                    {{ $permission->label ?: $permission->name }}
                                                    @include('admin.roles._help-icon', ['text' => $permission->help_text])
                                                </span>
                                                <span class="text-muted fs-8">{{ $permission->name }} · {{ $permission->guard_name }}</span>
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    @error('permissions')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                    @can('updatePermissions', $role)
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Simpan Matriks Permission</button>
                        </div>
                    @endcan
                </form>
            </x-metronic.card>
        </div>
    </div>
@endsection
