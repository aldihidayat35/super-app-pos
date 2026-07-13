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
            <button type="submit" class="btn btn-light-primary"><i class="ki-outline ki-magnifier"></i> Filter</button>
        </form>

        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead>
                    <tr class="text-muted fw-bold text-uppercase fs-7">
                        <th>Pengguna</th>
                        <th>Kontak</th>
                        <th>Role</th>
                        <th>Lokasi Utama</th>
                        <th>Status</th>
                        <th>Login Terakhir</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <a href="{{ route('admin.users.show', $user) }}" class="fw-bold text-gray-900 text-hover-primary">{{ $user->name }}</a>
                                <div class="text-muted fs-7">{{ $user->username }}</div>
                            </td>
                            <td>
                                <div>{{ $user->email }}</div>
                                <div class="text-muted fs-7">{{ $user->phone_number ?: '-' }}</div>
                            </td>
                            <td>
                                @forelse ($user->roles as $role)
                                    <span class="badge badge-light-primary me-1">{{ $role->label ?: str_replace('_', ' ', $role->name) }}</span>
                                @empty
                                    <span class="text-muted">Belum ada</span>
                                @endforelse
                            </td>
                            <td>
                                @php($primaryLocation = $user->workLocations->firstWhere('pivot.is_default', true))
                                @if ($primaryLocation)
                                    <div class="fw-semibold">{{ $primaryLocation->name }}</div>
                                    <div class="text-muted fs-7">{{ $primaryLocation->typeLabel() }}</div>
                                @else
                                    <span class="text-muted">Belum ada</span>
                                @endif
                            </td>
                            <td><x-metronic.status-badge :status="$user->is_active ? 'active' : 'inactive'" :label="$user->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                            <td>{{ $user->last_login_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '-' }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.users.show', $user) }}" class="btn btn-sm btn-light">Detail</a>
                                @can('update', $user)
                                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-light-primary">Edit</a>
                                    @if ($user->is_active && ! auth()->user()?->is($user))
                                        <form method="POST" action="{{ route('admin.users.deactivate', $user) }}" class="d-inline" data-confirm="Nonaktifkan pengguna ini?">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-light-danger">Nonaktifkan</button>
                                        </form>
                                    @endif
                                @endcan
                                @can('admin.users.reset_password')
                                    <form method="POST" action="{{ route('admin.users.password-reset', $user) }}" class="d-inline" data-confirm="Kirim link reset password ke pengguna ini?">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-light">Reset Password</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-metronic.empty-state title="Belum ada pengguna" description="Data pengguna akan tampil di sini setelah dibuat." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $users->links() }}
    </x-metronic.card>
@endsection
