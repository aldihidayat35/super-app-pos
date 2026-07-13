@php($isEdit = $branch->exists)

@if ($errors->any())
    <div class="alert alert-danger">Periksa kembali isian cabang.</div>
@endif

<form method="POST" action="{{ $isEdit ? route('admin.branches.update', $branch) : route('admin.branches.store') }}" novalidate>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-metronic.card title="Informasi Cabang/Toko">
        <div class="row">
            <div class="col-md-4">
                <x-metronic.form-group name="code" label="Kode Cabang" required>
                    <input id="code" name="code" value="{{ old('code', $branch->code) }}" class="form-control @error('code') is-invalid @enderror" @readonly($branch->has_transactions) required>
                </x-metronic.form-group>
            </div>
            <div class="col-md-8">
                <x-metronic.form-group name="name" label="Nama Toko" required>
                    <input id="name" name="name" value="{{ old('name', $branch->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                </x-metronic.form-group>
            </div>
            <div class="col-md-6">
                <x-metronic.form-group name="primary_warehouse_id" label="Gudang Pemasok Utama" required>
                    <select id="primary_warehouse_id" name="primary_warehouse_id" class="form-select @error('primary_warehouse_id') is-invalid @enderror" required>
                        <option value="">Pilih gudang</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((int) old('primary_warehouse_id', $branch->primary_warehouse_id) === $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </x-metronic.form-group>
            </div>
            <div class="col-md-6">
                <x-metronic.form-group name="manager_user_id" label="Kepala Toko">
                    <select id="manager_user_id" name="manager_user_id" class="form-select @error('manager_user_id') is-invalid @enderror">
                        <option value="">Belum ditentukan</option>
                        @foreach ($managers as $manager)
                            <option value="{{ $manager->id }}" @selected((int) old('manager_user_id', $branch->manager_user_id) === $manager->id)>{{ $manager->name }} · {{ $manager->email }}</option>
                        @endforeach
                    </select>
                </x-metronic.form-group>
            </div>
            <div class="col-md-6">
                <x-metronic.form-group name="phone_number" label="Nomor Telepon">
                    <input id="phone_number" name="phone_number" value="{{ old('phone_number', $branch->phone_number) }}" class="form-control @error('phone_number') is-invalid @enderror">
                </x-metronic.form-group>
            </div>
            <div class="col-md-6">
                <x-metronic.form-group name="sales_target" label="Target Penjualan">
                    <input id="sales_target" type="number" step="0.01" min="0" name="sales_target" value="{{ old('sales_target', $branch->sales_target) }}" class="form-control @error('sales_target') is-invalid @enderror">
                </x-metronic.form-group>
            </div>
            <div class="col-md-6">
                <x-metronic.form-group name="price_configuration" label="Konfigurasi Harga" required>
                    <input id="price_configuration" name="price_configuration" value="{{ old('price_configuration', $branch->price_configuration) }}" class="form-control @error('price_configuration') is-invalid @enderror" required>
                </x-metronic.form-group>
            </div>
            <div class="col-md-6">
                <x-metronic.form-group name="closing_configuration" label="Konfigurasi Closing" required>
                    <input id="closing_configuration" name="closing_configuration" value="{{ old('closing_configuration', $branch->closing_configuration) }}" class="form-control @error('closing_configuration') is-invalid @enderror" required>
                </x-metronic.form-group>
            </div>
        </div>

        <x-metronic.form-group name="address" label="Alamat">
            <textarea id="address" name="address" rows="3" class="form-control @error('address') is-invalid @enderror">{{ old('address', $branch->address) }}</textarea>
        </x-metronic.form-group>

        <div class="d-flex flex-wrap gap-8">
            <input type="hidden" name="is_closing_required" value="0">
            <label class="form-check form-switch form-check-custom form-check-solid">
                <input class="form-check-input" type="checkbox" name="is_closing_required" value="1" @checked(old('is_closing_required', $branch->is_closing_required ?? true))>
                <span class="form-check-label fw-semibold">Closing wajib</span>
            </label>
            <input type="hidden" name="is_active" value="0">
            <label class="form-check form-switch form-check-custom form-check-solid">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch->is_active ?? true))>
                <span class="form-check-label fw-semibold">Cabang aktif</span>
            </label>
        </div>
    </x-metronic.card>

    <div class="d-flex justify-content-end gap-3 mt-6">
        <a href="{{ $isEdit ? route('admin.branches.show', $branch) : route('admin.branches.index') }}" class="btn btn-light">Batal</a>
        <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Simpan Cabang' : 'Buat Cabang' }}</button>
    </div>
</form>
