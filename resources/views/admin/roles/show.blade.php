@extends('layouts.metronic.app')

@section('title', 'Detail Role - ' . config('app.name'))
@section('page_title', 'Detail Role')

@section('page_guide')
    <x-metronic.page-guide id="admin-role-show" title="Panduan Detail Role">
        <x-slot:function><p>Halaman menampilkan rincian role, permission lengkap, dan daftar user yang menggunakan role ini.</p></x-slot:function>
        <x-slot:parts><ul><li><strong>Role/Label/Guard:</strong> metadata role.</li><li><strong>Status:</strong> Sistem atau Kustom.</li><li><strong>Pengguna:</strong> jumlah user dengan role ini.</li><li><strong>Permission:</strong> daftar hak akses.</li><li><strong>Salin/Salin Role:</strong> duplikasi role.</li><li><strong>Edit Role:</strong> mengubah role kustom.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Role dapat disalin. Role sistem tidak dapat diedit atau dihapus.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Periksa metadata role.</li><li>Lihat daftar permission.</li><li>Salin role jika perlu role mirip baru.</li></ol></x-slot:operation>
    </x-metronic.page-guide>
@endsection

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

    <div class="row g-6 mb-6">
        <div class="col-lg-6">
            <x-metronic.card title="Informasi Peran (Role)">
                <div class="d-flex align-items-center mb-4">
                    <i class="ki-outline ki-shield-tick fs-1 text-primary me-4"></i>
                    <div>
                        <div class="text-muted fs-7">Nama Role</div>
                        <div class="fw-bold fs-4">{{ $role->label ?: str_replace('_', ' ', $role->name) }}</div>
                        <div class="text-muted">{{ $role->name }}</div>
                    </div>
                </div>
                <div class="d-flex align-items-center mb-4">
                    <i class="ki-outline ki-security-user fs-1 text-info me-4"></i>
                    <div>
                        <div class="text-muted fs-7">Tipe Keamanan (Guard)</div>
                        <span class="badge badge-light-info">{{ $role->guard_name }}</span>
                    </div>
                </div>
                <div class="d-flex align-items-center mb-4">
                    <i class="ki-outline ki-flag fs-1 text-success me-4"></i>
                    <div>
                        <div class="text-muted fs-7">Status Role</div>
                        <span class="badge {{ $role->is_system ? 'badge-light-primary' : 'badge-light-secondary' }}">{{ $role->is_system ? 'Sistem Default' : 'Kustomisasi' }}</span>
                    </div>
                </div>
                <div class="d-flex align-items-center mb-6">
                    <i class="ki-outline ki-text fs-1 text-warning me-4"></i>
                    <div>
                        <div class="text-muted fs-7">Deskripsi</div>
                        <div class="fw-semibold">{{ $role->description ?: '-' }}</div>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-6">
                        <div class="border border-dashed rounded p-4 text-center bg-light">
                            <i class="ki-outline ki-profile-user fs-2x text-primary mb-2"></i>
                            <div class="fs-2 fw-bold text-gray-800">{{ $role->users->count() }}</div>
                            <div class="text-muted fs-7">Jumlah Pengguna</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border border-dashed rounded p-4 text-center bg-light">
                            <i class="ki-outline ki-setting-2 fs-2x text-primary mb-2"></i>
                            <div class="fs-2 fw-bold text-gray-800">{{ $role->permissions->count() }}</div>
                            <div class="text-muted fs-7">Hak Akses Aktif</div>
                        </div>
                    </div>
                </div>
            </x-metronic.card>
        </div>

        <div class="col-lg-6">
            <x-metronic.card title="Pengguna dengan Peran Ini">
                <div class="overflow-auto" style="max-height: 425px;">
                    @forelse ($role->users as $user)
                        <div class="d-flex justify-content-between align-items-center border border-dashed rounded p-3 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="symbol symbol-40px me-3">
                                    <div class="symbol-label bg-light-primary text-primary fs-3 fw-bold">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                                </div>
                                <div>
                                    <div class="fw-bold">{{ $user->name }}</div>
                                    <div class="text-muted fs-7">{{ $user->email }}</div>
                                </div>
                            </div>
                            <a href="{{ route('admin.users.show', $user) }}" class="btn btn-sm btn-icon btn-light-primary" title="Lihat Profil">
                                <i class="ki-outline ki-arrow-right fs-2"></i>
                            </a>
                        </div>
                    @empty
                        <div class="py-10 text-center">
                            <x-metronic.empty-state title="Belum ada pengguna" description="Belum ada pengguna yang memiliki role ini." />
                        </div>
                    @endforelse
                </div>
            </x-metronic.card>
        </div>
    </div>

    <div class="row g-6">
        <div class="col-lg-12">
            <x-metronic.card title="Matriks Hak Akses (Permissions)">
                <form method="POST" action="{{ route('admin.roles.permissions.update', $role) }}">
                    @csrf
                    @method('PUT')

                    @include('admin.roles._permission_matrix', [
                        'permissions' => $permissions,
                        'selectedPermissions' => $selectedPermissions,
                        'disabled' => ! auth()->user()?->can('updatePermissions', $role),
                    ])

                    @error('permissions')<div class="invalid-feedback d-block mb-4">{{ $message }}</div>@enderror

                    @can('updatePermissions', $role)
                        <div class="d-flex justify-content-end mt-6 pt-4 border-top">
                            <button type="submit" class="btn btn-primary">
                                <i class="ki-outline ki-check fs-2 me-1"></i> Simpan Matriks Permission
                            </button>
                        </div>
                    @endcan
                </form>
            </x-metronic.card>
        </div>
    </div>
@endsection
