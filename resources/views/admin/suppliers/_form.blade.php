@php($isEdit = $supplier->exists)
<form method="POST" action="{{ $isEdit ? route('admin.suppliers.update', $supplier) : route('admin.suppliers.store') }}">
@csrf @if($isEdit) @method('PUT') @endif
<x-metronic.card title="Identitas Supplier">
    <div class="row">
        <div class="col-md-3"><x-metronic.form-group name="code" label="Kode" required><input name="code" value="{{ old('code', $supplier->code) }}" class="form-control @error('code') is-invalid @enderror"></x-metronic.form-group></div>
        <div class="col-md-5"><x-metronic.form-group name="name" label="Nama Supplier" required><input name="name" value="{{ old('name', $supplier->name) }}" class="form-control @error('name') is-invalid @enderror"></x-metronic.form-group></div>
        <div class="col-md-4"><x-metronic.form-group name="contact_name" label="PIC/Kontak"><input name="contact_name" value="{{ old('contact_name', $supplier->contact_name) }}" class="form-control"></x-metronic.form-group></div>
        <div class="col-md-3"><x-metronic.form-group name="phone_number" label="Telepon"><input name="phone_number" value="{{ old('phone_number', $supplier->phone_number) }}" class="form-control"></x-metronic.form-group></div>
        <div class="col-md-3"><x-metronic.form-group name="whatsapp_number" label="Nomor WA"><input name="whatsapp_number" value="{{ old('whatsapp_number', $supplier->whatsapp_number) }}" class="form-control @error('whatsapp_number') is-invalid @enderror"></x-metronic.form-group></div>
        <div class="col-md-3"><x-metronic.form-group name="email" label="Email"><input name="email" value="{{ old('email', $supplier->email) }}" class="form-control @error('email') is-invalid @enderror"></x-metronic.form-group></div>
        <div class="col-md-3"><x-metronic.form-group name="city" label="Kota"><input name="city" value="{{ old('city', $supplier->city) }}" class="form-control"></x-metronic.form-group></div>
        <div class="col-md-4"><x-metronic.form-group name="tax_number" label="NPWP Opsional"><input name="tax_number" value="{{ old('tax_number', $supplier->tax_number) }}" class="form-control"></x-metronic.form-group></div>
        <div class="col-md-4"><x-metronic.form-group name="payment_term_days" label="Termin"><input type="number" min="0" max="365" name="payment_term_days" value="{{ old('payment_term_days', $supplier->payment_term_days ?? 0) }}" class="form-control"></x-metronic.form-group></div>
        <div class="col-md-4"><x-metronic.form-group name="bank_name" label="Bank"><input name="bank_name" value="{{ old('bank_name', $supplier->bank_name) }}" class="form-control"></x-metronic.form-group></div>
        <div class="col-md-6"><x-metronic.form-group name="bank_account_name" label="Nama Rekening"><input name="bank_account_name" value="{{ old('bank_account_name', $supplier->bank_account_name) }}" class="form-control"></x-metronic.form-group></div>
        <div class="col-md-6"><x-metronic.form-group name="bank_account_number" label="Nomor Rekening"><input name="bank_account_number" value="{{ old('bank_account_number', $supplier->bank_account_number) }}" class="form-control"></x-metronic.form-group></div>
    </div>
    <x-metronic.form-group name="address" label="Alamat"><textarea name="address" rows="3" class="form-control">{{ old('address', $supplier->address) }}</textarea></x-metronic.form-group>
    <x-metronic.form-group name="notes" label="Catatan"><textarea name="notes" rows="3" class="form-control">{{ old('notes', $supplier->notes) }}</textarea></x-metronic.form-group>
    <input type="hidden" name="is_active" value="0"><label class="form-check form-switch form-check-custom form-check-solid"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier->is_active ?? true))><span class="form-check-label">Supplier aktif</span></label>
</x-metronic.card>
<div class="d-flex justify-content-end gap-3 mt-6"><a href="{{ $isEdit ? route('admin.suppliers.show', $supplier) : route('admin.suppliers.index') }}" class="btn btn-light">Batal</a><button class="btn btn-primary">{{ $isEdit ? 'Simpan Supplier' : 'Buat Supplier' }}</button></div>
</form>
