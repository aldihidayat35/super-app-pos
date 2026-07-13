@php
    $isEdit = $user->exists;
@endphp

@if ($errors->any())
    <div class="alert alert-danger">Periksa kembali isian yang ditandai.</div>
@endif

<form method="POST" action="{{ $isEdit ? route('admin.users.update', $user) : route('admin.users.store') }}" enctype="multipart/form-data" novalidate>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="row g-6">
        <div class="col-lg-8">
            <x-metronic.card title="Informasi Pengguna">
                <div class="row">
                    <div class="col-md-6">
                        <x-metronic.form-group name="name" label="Nama" required>
                            <input id="name" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                        </x-metronic.form-group>
                    </div>
                    <div class="col-md-6">
                        <x-metronic.form-group name="username" label="Username" required>
                            <input id="username" name="username" value="{{ old('username', $user->username) }}" class="form-control @error('username') is-invalid @enderror" required>
                        </x-metronic.form-group>
                    </div>
                    <div class="col-md-6">
                        <x-metronic.form-group name="email" label="Alamat Email" required>
                            <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror" required>
                        </x-metronic.form-group>
                    </div>
                    <div class="col-md-6">
                        <x-metronic.form-group name="phone_number" label="Nomor WhatsApp">
                            <input id="phone_number" name="phone_number" value="{{ old('phone_number', $user->phone_number) }}" class="form-control @error('phone_number') is-invalid @enderror">
                        </x-metronic.form-group>
                    </div>
                </div>

                <x-metronic.form-group name="avatar" label="Avatar" help="Format gambar, maksimal 2 MB.">
                    <input id="avatar" type="file" name="avatar" class="form-control @error('avatar') is-invalid @enderror" accept="image/*">
                </x-metronic.form-group>

                <input type="hidden" name="is_active" value="0">
                <label class="form-check form-switch form-check-custom form-check-solid mb-0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                    <span class="form-check-label fw-semibold">Akun aktif</span>
                </label>
                @error('is_active')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </x-metronic.card>
        </div>

        <div class="col-lg-4">
            <x-metronic.card title="Keamanan dan Role">
                <x-metronic.form-group name="password" label="{{ $isEdit ? 'Kata Sandi Baru' : 'Kata Sandi' }}" :required="!$isEdit" help="{{ $isEdit ? 'Kosongkan jika tidak ingin mengubah kata sandi.' : null }}">
                    <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" @required(!$isEdit)>
                </x-metronic.form-group>

                <x-metronic.form-group name="password_confirmation" label="Konfirmasi Kata Sandi" :required="!$isEdit">
                    <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" @required(!$isEdit)>
                </x-metronic.form-group>

                <div class="mb-7">
                    <label class="form-label fw-semibold">Role</label>
                    <div class="border rounded p-4">
                        @forelse ($roles as $role)
                            <label class="form-check form-check-custom form-check-solid mb-3">
                                <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(collect(old('roles', $selectedRoles))->contains($role->id))>
                                <span class="form-check-label">{{ str_replace('_', ' ', $role->name) }}</span>
                            </label>
                        @empty
                            <div class="text-muted">Belum ada role.</div>
                        @endforelse
                    </div>
                    @error('roles')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </x-metronic.card>

            <x-metronic.card title="Lokasi Kerja" class="mt-6">
                <div class="mb-7">
                    <label class="form-label fw-semibold">Gudang/Cabang yang Diizinkan</label>
                    <div class="border rounded p-4 mh-300px overflow-auto">
                        @forelse ($locations as $location)
                            <label class="form-check form-check-custom form-check-solid mb-3">
                                <input class="form-check-input" type="checkbox" name="locations[]" value="{{ $location->id }}" @checked(collect(old('locations', $selectedLocations))->contains($location->id))>
                                <span class="form-check-label">
                                    {{ $location->name }}
                                    <span class="text-muted fs-8 d-block">{{ $location->code }} · {{ $location->typeLabel() }}</span>
                                </span>
                            </label>
                        @empty
                            <div class="text-muted">Belum ada lokasi kerja.</div>
                        @endforelse
                    </div>
                    @error('locations')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <x-metronic.form-group name="default_location_id" label="Lokasi Utama">
                    <select id="default_location_id" name="default_location_id" class="form-select @error('default_location_id') is-invalid @enderror">
                        <option value="">Pilih lokasi utama</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((int) old('default_location_id', $defaultLocationId) === $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </x-metronic.form-group>

                <div class="row">
                    <div class="col-md-6">
                        <x-metronic.form-group name="location_effective_from" label="Berlaku Mulai">
                            <input id="location_effective_from" type="date" name="location_effective_from" value="{{ old('location_effective_from', $locationEffectiveFrom) }}" class="form-control @error('location_effective_from') is-invalid @enderror">
                        </x-metronic.form-group>
                    </div>
                    <div class="col-md-6">
                        <x-metronic.form-group name="location_effective_until" label="Berlaku Sampai">
                            <input id="location_effective_until" type="date" name="location_effective_until" value="{{ old('location_effective_until', $locationEffectiveUntil) }}" class="form-control @error('location_effective_until') is-invalid @enderror">
                        </x-metronic.form-group>
                    </div>
                </div>

                <input type="hidden" name="location_is_active" value="0">
                <label class="form-check form-switch form-check-custom form-check-solid mb-0">
                    <input class="form-check-input" type="checkbox" name="location_is_active" value="1" @checked(old('location_is_active', $locationIsActive))>
                    <span class="form-check-label fw-semibold">Assignment lokasi aktif</span>
                </label>
            </x-metronic.card>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-3 mt-6">
        <a href="{{ $isEdit ? route('admin.users.show', $user) : route('admin.users.index') }}" class="btn btn-light">Batal</a>
        <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Pengguna' }}</button>
    </div>
</form>
