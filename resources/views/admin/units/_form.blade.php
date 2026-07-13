@php($isEdit = $unit->exists)
<form method="POST" action="{{ $isEdit ? route('admin.units.update', $unit) : route('admin.units.store') }}">
@csrf @if($isEdit) @method('PUT') @endif
<x-metronic.card title="Informasi Satuan">
    <div class="row">
        <div class="col-md-3"><x-metronic.form-group name="code" label="Kode" required><input name="code" value="{{ old('code', $unit->code) }}" class="form-control @error('code') is-invalid @enderror" @readonly($unit->has_transactions)></x-metronic.form-group></div>
        <div class="col-md-5"><x-metronic.form-group name="name" label="Nama" required><input name="name" value="{{ old('name', $unit->name) }}" class="form-control @error('name') is-invalid @enderror"></x-metronic.form-group></div>
        <div class="col-md-2"><x-metronic.form-group name="symbol" label="Simbol" required><input name="symbol" value="{{ old('symbol', $unit->symbol) }}" class="form-control @error('symbol') is-invalid @enderror"></x-metronic.form-group></div>
        <div class="col-md-2"><x-metronic.form-group name="precision" label="Presisi" required><input type="number" name="precision" min="0" max="4" value="{{ old('precision', $unit->precision) }}" class="form-control"></x-metronic.form-group></div>
    </div>
    <input type="hidden" name="is_active" value="0"><label class="form-check form-switch form-check-custom form-check-solid"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $unit->is_active ?? true))><span class="form-check-label">Satuan aktif</span></label>
</x-metronic.card>
<div class="d-flex justify-content-end gap-3 mt-6"><a href="{{ route('admin.units.index') }}" class="btn btn-light">Batal</a><button class="btn btn-primary">{{ $isEdit ? 'Simpan Satuan' : 'Buat Satuan' }}</button></div>
</form>
