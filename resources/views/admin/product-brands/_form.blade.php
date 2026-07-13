@php($isEdit = $brand->exists)
<form method="POST" enctype="multipart/form-data" action="{{ $isEdit ? route('admin.product-brands.update', $brand) : route('admin.product-brands.store') }}">
@csrf @if($isEdit) @method('PUT') @endif
<x-metronic.card title="Informasi Merek">
    <div class="row"><div class="col-md-4"><x-metronic.form-group name="code" label="Kode" required><input name="code" value="{{ old('code', $brand->code) }}" class="form-control @error('code') is-invalid @enderror"></x-metronic.form-group></div><div class="col-md-8"><x-metronic.form-group name="name" label="Nama" required><input name="name" value="{{ old('name', $brand->name) }}" class="form-control @error('name') is-invalid @enderror"></x-metronic.form-group></div></div>
    <x-metronic.form-group name="description" label="Deskripsi"><textarea name="description" rows="3" class="form-control">{{ old('description', $brand->description) }}</textarea></x-metronic.form-group>
    <x-metronic.form-group name="logo" label="Logo Opsional"><input type="file" name="logo" accept="image/*" class="form-control @error('logo') is-invalid @enderror"><div class="form-text">Format gambar, maksimal 2 MB.</div></x-metronic.form-group>
    <input type="hidden" name="is_active" value="0"><label class="form-check form-switch form-check-custom form-check-solid"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $brand->is_active ?? true))><span class="form-check-label">Merek aktif</span></label>
</x-metronic.card>
<div class="d-flex justify-content-end gap-3 mt-6"><a href="{{ route('admin.product-brands.index') }}" class="btn btn-light">Batal</a><button class="btn btn-primary">{{ $isEdit ? 'Simpan Merek' : 'Buat Merek' }}</button></div>
</form>
