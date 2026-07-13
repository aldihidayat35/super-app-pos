@php($isEdit = $warehouse->exists)

@if ($errors->any())
    <div class="alert alert-danger">Periksa kembali isian gudang.</div>
@endif

<form method="POST" action="{{ $isEdit ? route('admin.warehouses.update', $warehouse) : route('admin.warehouses.store') }}" novalidate>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-metronic.card title="Informasi Gudang">
        <div class="row">
            <div class="col-md-4">
                <x-metronic.form-group name="code" label="Kode Gudang" required>
                    <input id="code" name="code" value="{{ old('code', $warehouse->code) }}" class="form-control @error('code') is-invalid @enderror" @readonly($warehouse->has_transactions) required>
                </x-metronic.form-group>
            </div>
            <div class="col-md-8">
                <x-metronic.form-group name="name" label="Nama Gudang" required>
                    <input id="name" name="name" value="{{ old('name', $warehouse->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                </x-metronic.form-group>
            </div>
            <div class="col-md-6">
                <x-metronic.form-group name="city" label="Kota">
                    <input id="city" name="city" value="{{ old('city', $warehouse->city) }}" class="form-control @error('city') is-invalid @enderror">
                </x-metronic.form-group>
            </div>
            <div class="col-md-6">
                <x-metronic.form-group name="phone_number" label="Nomor Telepon">
                    <input id="phone_number" name="phone_number" value="{{ old('phone_number', $warehouse->phone_number) }}" class="form-control @error('phone_number') is-invalid @enderror">
                </x-metronic.form-group>
            </div>
            <div class="col-md-6">
                <x-metronic.form-group name="manager_user_id" label="Kepala Gudang">
                    <select id="manager_user_id" name="manager_user_id" class="form-select @error('manager_user_id') is-invalid @enderror">
                        <option value="">Belum ditentukan</option>
                        @foreach ($managers as $manager)
                            <option value="{{ $manager->id }}" @selected((int) old('manager_user_id', $warehouse->manager_user_id) === $manager->id)>{{ $manager->name }} · {{ $manager->email }}</option>
                        @endforeach
                    </select>
                </x-metronic.form-group>
            </div>
            <div class="col-md-6">
                <x-metronic.form-group name="capacity" label="Kapasitas">
                    <input id="capacity" type="number" step="0.0001" min="0" name="capacity" value="{{ old('capacity', $warehouse->capacity) }}" class="form-control @error('capacity') is-invalid @enderror">
                </x-metronic.form-group>
            </div>
        </div>

        <x-metronic.form-group name="address" label="Alamat">
            <textarea id="address" name="address" rows="3" class="form-control @error('address') is-invalid @enderror">{{ old('address', $warehouse->address) }}</textarea>
        </x-metronic.form-group>

        <x-metronic.form-group name="service_area" label="Area Layanan">
            <input id="service_area" name="service_area" value="{{ old('service_area', $warehouse->service_area) }}" class="form-control @error('service_area') is-invalid @enderror">
        </x-metronic.form-group>

        <input type="hidden" name="is_active" value="0">
        <label class="form-check form-switch form-check-custom form-check-solid">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $warehouse->is_active ?? true))>
            <span class="form-check-label fw-semibold">Gudang aktif</span>
        </label>
    </x-metronic.card>

    <div class="d-flex justify-content-end gap-3 mt-6">
        <a href="{{ $isEdit ? route('admin.warehouses.show', $warehouse) : route('admin.warehouses.index') }}" class="btn btn-light">Batal</a>
        <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Simpan Gudang' : 'Buat Gudang' }}</button>
    </div>
</form>
