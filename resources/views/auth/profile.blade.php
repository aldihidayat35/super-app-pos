@extends('layouts.metronic.app')

@section('title', 'Profil Saya - ' . config('app.name'))
@section('page_title', 'Profil Saya')

@section('content')
    <x-metronic.page-title title="Profil Saya" description="Kelola identitas akun dan keamanan sesi Anda." />

    <div class="row g-6">
        <div class="col-lg-8">
            <x-metronic.card title="Informasi Akun">
                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" novalidate>
                    @csrf
                    @method('PATCH')

                    <div class="d-flex align-items-center gap-5 mb-8">
                        <div class="symbol symbol-80px">
                            @if ($user->avatar_path)
                                <img src="{{ asset('storage/'.$user->avatar_path) }}" alt="{{ $user->name }}">
                            @else
                                <span class="symbol-label bg-light-primary text-primary fs-2 fw-bold">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                            @endif
                        </div>
                        <div>
                            <div class="fw-bold text-gray-900">{{ $user->name }}</div>
                            <div class="text-muted">{{ $user->email }}</div>
                            <span class="badge {{ $user->is_active ? 'badge-light-success' : 'badge-light-danger' }} mt-2">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <x-metronic.form-group name="name" label="Nama" required>
                                <input id="name" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                            </x-metronic.form-group>
                        </div>
                        <div class="col-md-6">
                            <x-metronic.form-group name="username" label="Username" required>
                                <input id="username" name="username" value="{{ old('username', $user->username) }}" class="form-control @error('username') is-invalid @enderror" autocomplete="username" required>
                            </x-metronic.form-group>
                        </div>
                        <div class="col-md-6">
                            <x-metronic.form-group name="email" label="Alamat Email" required>
                                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email" required>
                            </x-metronic.form-group>
                        </div>
                        <div class="col-md-6">
                            <x-metronic.form-group name="phone_number" label="Nomor WA">
                                <input id="phone_number" name="phone_number" value="{{ old('phone_number', $user->phone_number) }}" class="form-control @error('phone_number') is-invalid @enderror" autocomplete="tel">
                            </x-metronic.form-group>
                        </div>
                    </div>

                    <x-metronic.form-group name="avatar" label="Avatar" help="Format gambar, maksimal 2 MB.">
                        <input id="avatar" type="file" name="avatar" class="form-control @error('avatar') is-invalid @enderror" accept="image/*">
                    </x-metronic.form-group>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Simpan Profil</button>
                    </div>
                </form>
            </x-metronic.card>
        </div>

        <div class="col-lg-4">
            <x-metronic.card title="Keamanan">
                <div class="mb-6">
                    <div class="text-muted fs-7">Login terakhir</div>
                    <div class="fw-semibold">{{ $user->last_login_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Belum tercatat' }}</div>
                </div>

                <form method="POST" action="{{ route('profile.password.update') }}" novalidate>
                    @csrf
                    @method('PUT')

                    <x-metronic.form-group name="current_password" label="Kata Sandi Saat Ini" required>
                        <input id="current_password" type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password" required>
                    </x-metronic.form-group>
                    <x-metronic.form-group name="password" label="Kata Sandi Baru" required>
                        <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required>
                    </x-metronic.form-group>
                    <x-metronic.form-group name="password_confirmation" label="Konfirmasi Kata Sandi Baru" required>
                        <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                    </x-metronic.form-group>

                    <button type="submit" class="btn btn-light-primary w-100">Ubah Kata Sandi</button>
                </form>
            </x-metronic.card>
        </div>
    </div>
@endsection
