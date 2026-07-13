@php($isEdit = $category->exists)
<form method="POST" action="{{ $isEdit ? route('admin.product-categories.update', $category) : route('admin.product-categories.store') }}" novalidate>
    @csrf
    @if ($isEdit) @method('PUT') @endif
    <x-metronic.card title="Informasi Kategori">
        <div class="row">
            <div class="col-md-4"><x-metronic.form-group name="code" label="Kode" required><input name="code" value="{{ old('code', $category->code) }}" class="form-control @error('code') is-invalid @enderror" required></x-metronic.form-group></div>
            <div class="col-md-8"><x-metronic.form-group name="name" label="Nama" required><input name="name" value="{{ old('name', $category->name) }}" class="form-control @error('name') is-invalid @enderror" required></x-metronic.form-group></div>
            <div class="col-md-6">
                <x-metronic.form-group name="parent_id" label="Parent">
                    <select name="parent_id" class="form-select @error('parent_id') is-invalid @enderror"><option value="">Kategori Utama</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected((int) old('parent_id', $category->parent_id) === $parent->id)>{{ $parent->name }}</option>@endforeach</select>
                </x-metronic.form-group>
            </div>
            <div class="col-md-3"><x-metronic.form-group name="sort_order" label="Urutan"><input type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order) }}" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-3"><x-metronic.form-group name="icon" label="Ikon"><input name="icon" value="{{ old('icon', $category->icon) }}" class="form-control" placeholder="ki-outline ki-box"></x-metronic.form-group></div>
        </div>
        <input type="hidden" name="is_active" value="0">
        <label class="form-check form-switch form-check-custom form-check-solid"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true))><span class="form-check-label fw-semibold">Kategori aktif</span></label>
    </x-metronic.card>
    <div class="d-flex justify-content-end gap-3 mt-6"><a href="{{ route('admin.product-categories.index') }}" class="btn btn-light">Batal</a><button class="btn btn-primary">{{ $isEdit ? 'Simpan Kategori' : 'Buat Kategori' }}</button></div>
</form>
