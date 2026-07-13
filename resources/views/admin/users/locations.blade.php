@extends('layouts.metronic.app')

@section('title', 'Lokasi Kerja Pengguna - ' . config('app.name'))
@section('page_title', 'Lokasi Kerja Pengguna')

@section('content')
    <x-metronic.page-title title="Penugasan Lokasi Kerja" description="Tentukan gudang atau cabang/toko yang dapat digunakan oleh pengguna." />

    <x-metronic.card title="{{ $user->name }}">
        @if ($errors->any())
            <div class="alert alert-danger">Periksa kembali pilihan lokasi kerja.</div>
        @endif

        <form method="POST" action="{{ route('admin.users.locations.update', $user) }}">
            @csrf
            @method('PUT')

            <div class="table-responsive">
                <table class="table table-row-dashed align-middle">
                    <thead>
                        <tr class="text-muted fw-bold text-uppercase fs-7">
                            <th>Dipilih</th>
                            <th>Lokasi</th>
                            <th>Tipe</th>
                            <th>Default</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($locations as $location)
                            <tr>
                                <td class="w-80px">
                                    <input class="form-check-input" type="checkbox" name="locations[]" value="{{ $location->id }}" @checked(collect(old('locations', $selectedLocations))->contains($location->id))>
                                </td>
                                <td>
                                    <div class="fw-bold">{{ $location->name }}</div>
                                    <div class="text-muted fs-7">{{ $location->code }}</div>
                                </td>
                                <td>{{ $location->typeLabel() }}</td>
                                <td class="w-100px">
                                    <input class="form-check-input" type="radio" name="default_location_id" value="{{ $location->id }}" @checked((int) old('default_location_id', $defaultLocationId) === $location->id)>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-metronic.empty-state title="Belum ada lokasi kerja" description="Seeder lokal dapat membuat contoh Gudang Utama dan Toko Utama." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @error('locations')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @error('default_location_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

            <div class="row mt-6">
                <div class="col-md-4">
                    <x-metronic.form-group name="effective_from" label="Berlaku Mulai">
                        <input id="effective_from" type="date" name="effective_from" value="{{ old('effective_from', $effectiveFrom) }}" class="form-control @error('effective_from') is-invalid @enderror">
                    </x-metronic.form-group>
                </div>
                <div class="col-md-4">
                    <x-metronic.form-group name="effective_until" label="Berlaku Sampai">
                        <input id="effective_until" type="date" name="effective_until" value="{{ old('effective_until', $effectiveUntil) }}" class="form-control @error('effective_until') is-invalid @enderror">
                    </x-metronic.form-group>
                </div>
                <div class="col-md-4">
                    <div class="pt-md-8">
                        <input type="hidden" name="is_active" value="0">
                        <label class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $assignmentIsActive))>
                            <span class="form-check-label fw-semibold">Assignment aktif</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-3 mt-6">
                <a href="{{ route('admin.users.show', $user) }}" class="btn btn-light">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Lokasi Kerja</button>
            </div>
        </form>
    </x-metronic.card>
@endsection
