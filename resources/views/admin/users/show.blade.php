@extends('layouts.metronic.app')

@section('title', 'Detail Pengguna - ' . config('app.name'))
@section('page_title', 'Detail Pengguna')

@section('toolbar_actions')
    @can('admin.users.reset_password')
        <form method="POST" action="{{ route('admin.users.password-reset', $user) }}">
            @csrf
            <button type="submit" class="btn btn-light"><i class="ki-outline ki-key"></i> Reset Password</button>
        </form>
    @endcan
    @can('update', $user)
        @if ($user->is_active && ! auth()->user()?->is($user))
            <form method="POST" action="{{ route('admin.users.deactivate', $user) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-light-danger"><i class="ki-outline ki-cross-circle"></i> Nonaktifkan</button>
            </form>
        @endif
    @endcan
    @can('assignLocations', $user)
        <a href="{{ route('admin.users.locations.edit', $user) }}" class="btn btn-light-primary"><i class="ki-outline ki-geolocation"></i> Lokasi Kerja</a>
    @endcan
    @can('update', $user)
        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary"><i class="ki-outline ki-pencil"></i> Edit</a>
    @endcan
@endsection

@section('content')
    <div class="row g-6">
        <div class="col-lg-4">
            <x-metronic.card title="Profil">
                <div class="d-flex align-items-center gap-5 mb-7">
                    <div class="symbol symbol-80px">
                        @if ($user->avatar_path)
                            <img src="{{ asset('storage/'.$user->avatar_path) }}" alt="{{ $user->name }}">
                        @else
                            <span class="symbol-label bg-light-primary text-primary fs-2 fw-bold">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                        @endif
                    </div>
                    <div>
                        <div class="fw-bold fs-4">{{ $user->name }}</div>
                        <div class="text-muted">{{ $user->username }}</div>
                        <x-metronic.status-badge class="mt-2" :status="$user->is_active ? 'active' : 'inactive'" :label="$user->is_active ? 'Aktif' : 'Nonaktif'" />
                    </div>
                </div>
                <div class="mb-4"><div class="text-muted fs-7">Email</div><div class="fw-semibold">{{ $user->email }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Nomor WhatsApp</div><div class="fw-semibold">{{ $user->phone_number ?: '-' }}</div></div>
                <div><div class="text-muted fs-7">Login Terakhir</div><div class="fw-semibold">{{ $user->last_login_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '-' }}</div></div>
            </x-metronic.card>
        </div>

        <div class="col-lg-8">
            <x-metronic.card title="Role">
                @forelse ($user->roles as $role)
                    <div class="d-flex align-items-center justify-content-between border rounded p-4 mb-3">
                        <div>
                            <a href="{{ route('admin.roles.show', $role) }}" class="fw-bold text-gray-900 text-hover-primary">{{ $role->label ?: str_replace('_', ' ', $role->name) }}</a>
                            <div class="text-muted fs-7">{{ $role->permissions->count() }} permission</div>
                        </div>
                        <span class="badge badge-light-primary">{{ $role->guard_name }}</span>
                    </div>
                @empty
                    <x-metronic.empty-state title="Belum ada role" description="Pengguna ini belum memiliki role." />
                @endforelse
            </x-metronic.card>

            <x-metronic.card title="Lokasi Kerja" class="mt-6">
                @forelse ($user->workLocations as $location)
                    <div class="d-flex align-items-center justify-content-between border rounded p-4 mb-3">
                        <div>
                            <div class="fw-bold">{{ $location->name }}</div>
                            <div class="text-muted fs-7">
                                {{ $location->code }} · {{ $location->typeLabel() }}
                                · {{ $location->pivot->is_active ? 'Aktif' : 'Nonaktif' }}
                                · {{ $location->pivot->effective_from ?: 'tanpa tanggal mulai' }} - {{ $location->pivot->effective_until ?: 'tanpa tanggal akhir' }}
                            </div>
                        </div>
                        @if ($location->pivot->is_default)
                            <span class="badge badge-light-success">Default</span>
                        @endif
                    </div>
                @empty
                    <x-metronic.empty-state title="Belum ada lokasi kerja" description="Tambahkan lokasi kerja agar konteks operasional pengguna jelas." />
                @endforelse
            </x-metronic.card>

            <x-metronic.card title="Permission Efektif" class="mt-6">
                <div class="d-flex flex-wrap gap-2">
                    @forelse ($user->getAllPermissions()->sortBy('name') as $permission)
                        <span class="badge badge-light">{{ $permission->name }}</span>
                    @empty
                        <span class="text-muted">Belum ada permission efektif.</span>
                    @endforelse
                </div>
            </x-metronic.card>

            <x-metronic.card title="Aktivitas Terakhir" class="mt-6">
                @forelse ($recentActivities as $activity)
                    <div class="border-bottom pb-3 mb-3">
                        <div class="fw-semibold">{{ $activity->description }}</div>
                        <div class="text-muted fs-7">{{ $activity->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</div>
                    </div>
                @empty
                    <x-metronic.empty-state title="Belum ada aktivitas" description="Aktivitas pengguna akan tampil setelah tercatat." />
                @endforelse
            </x-metronic.card>

            <x-metronic.card title="Approval yang Dibuat" class="mt-6">
                <x-metronic.empty-state title="Belum ada data approval" description="Modul approval akan mengisi bagian ini pada fase berikutnya." />
            </x-metronic.card>
        </div>
    </div>
@endsection
