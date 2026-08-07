@php
    $isEdit = $customer->exists;
    $documentRows = old('documents', [
        ['type' => 'nib'],
        ['type' => 'npwp'],
        ['type' => 'owner_id_card'],
    ]);
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.customers.update', $customer) : route('admin.customers.store') }}" enctype="multipart/form-data">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <x-metronic.card title="Identitas Pelanggan">
        <div class="row">
            <div class="col-md-3">
                <x-metronic.form-group name="type" label="Tipe" required>
                    <select name="type" class="form-select">
                        @foreach($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $customer->type?->value ?? 'general') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-metronic.form-group>
            </div>
            <div class="col-md-3">
                @if($isEdit)
                    <x-metronic.form-group name="code" label="Kode Pelanggan" required help="Kode pelanggan dibuat otomatis saat pendaftaran. Field ini dipertahankan untuk histori dan integrasi.">
                        <input name="code" value="{{ old('code', $customer->code) }}" class="form-control @error('code') is-invalid @enderror" readonly>
                    </x-metronic.form-group>
                @else
                    <x-metronic.form-group name="customer_code_preview" label="Kode Pelanggan" help="Dibuat otomatis saat disimpan dengan format PREFIX-YYYYMMDD-SEQUENCE.">
                        <input value="Otomatis saat disimpan" class="form-control" readonly>
                    </x-metronic.form-group>
                @endif
            </div>
            <div class="col-md-6">
                <x-metronic.form-group name="business_name" label="Nama Usaha/Pelanggan" required>
                    <input name="business_name" value="{{ old('business_name', $customer->business_name) }}" class="form-control @error('business_name') is-invalid @enderror">
                </x-metronic.form-group>
            </div>
            <div class="col-md-4">
                <x-metronic.form-group name="owner_name" label="Pemilik">
                    <input name="owner_name" value="{{ old('owner_name', $customer->owner_name) }}" class="form-control">
                </x-metronic.form-group>
            </div>
            <div class="col-md-4">
                <x-metronic.form-group name="pic_name" label="PIC / Penanggung Jawab" help="Orang yang menjadi kontak utama atau penanggung jawab dari pelanggan.">
                    <input name="pic_name" value="{{ old('pic_name', $customer->pic_name) }}" class="form-control">
                </x-metronic.form-group>
            </div>
            <div class="col-md-4">
                <x-metronic.form-group name="city" label="Kota">
                    <input name="city" value="{{ old('city', $customer->city) }}" class="form-control">
                </x-metronic.form-group>
            </div>
            <div class="col-md-4">
                <x-metronic.form-group name="whatsapp_number" label="Nomor WA">
                    <input name="whatsapp_number" value="{{ old('whatsapp_number', $customer->whatsapp_number) }}" class="form-control @error('whatsapp_number') is-invalid @enderror">
                </x-metronic.form-group>
            </div>
            <div class="col-md-4">
                <x-metronic.form-group name="email" label="Email">
                    <input name="email" value="{{ old('email', $customer->email) }}" class="form-control @error('email') is-invalid @enderror">
                </x-metronic.form-group>
            </div>
            <div class="col-md-4">
                <x-metronic.form-group name="price_category" label="Ring Harga" help="Kategori harga yang menentukan kelompok harga pelanggan.">
                    <select name="price_category" class="form-select">
                        @foreach($priceCategories as $value => $label)
                            <option value="{{ $value }}" @selected(old('price_category', $customer->price_category ?? 'retail') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-metronic.form-group>
            </div>
            <div class="col-md-4">
                <x-metronic.form-group name="minimum_order" label="Minimum Order">
                    <input type="number" step="0.01" min="0" name="minimum_order" value="{{ old('minimum_order', $customer->minimum_order ?? 0) }}" class="form-control">
                </x-metronic.form-group>
            </div>
            <div class="col-md-4">
                <x-metronic.form-group name="payment_term_days" label="Tempo Pembayaran" help="Jumlah hari yang diberikan untuk melakukan pembayaran setelah invoice diterbitkan.">
                    <input type="number" min="0" max="365" name="payment_term_days" value="{{ old('payment_term_days', $customer->payment_term_days ?? 0) }}" class="form-control">
                </x-metronic.form-group>
            </div>
            <div class="col-md-4">
                <x-metronic.form-group name="credit_limit" label="Batas Maksimum Kredit" help="Jumlah maksimum utang pelanggan yang diperbolehkan.">
                    <input type="number" step="0.01" min="0" name="credit_limit" value="{{ old('credit_limit', $customer->credit_limit ?? 0) }}" class="form-control">
                </x-metronic.form-group>
            </div>
            <div class="col-md-6">
                <x-metronic.form-group name="verification_status" label="Status Verifikasi">
                    <select name="verification_status" class="form-select">
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('verification_status', $customer->verification_status?->value ?? 'pending_verification') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-metronic.form-group>
            </div>
            <div class="col-md-6">
                <x-metronic.form-group name="account_status" label="Status Akun">
                    <select name="account_status" class="form-select">
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('account_status', $customer->account_status?->value ?? 'pending_verification') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-metronic.form-group>
            </div>
        </div>
        <x-metronic.form-group name="business_address" label="Alamat Usaha">
            <textarea name="business_address" rows="3" class="form-control">{{ old('business_address', $customer->business_address) }}</textarea>
        </x-metronic.form-group>
        <x-metronic.form-group name="notes" label="Catatan">
            <textarea name="notes" rows="3" class="form-control">{{ old('notes', $customer->notes) }}</textarea>
        </x-metronic.form-group>
        <input type="hidden" name="is_active" value="0">
        <label class="form-check form-switch form-check-custom form-check-solid">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer->is_active ?? true))>
            <span class="form-check-label">Pelanggan aktif</span>
        </label>
    </x-metronic.card>

    @unless($isEdit)
        <x-metronic.card title="Dokumen Usaha / Dokumen Pendukung" class="mt-6">
            <div class="alert alert-info">
                Dokumen bersifat opsional pada tahap pendaftaran. Jika satu baris dokumen diisi, file dokumen wajib diunggah agar data tersimpan lengkap.
            </div>
            @foreach($documentRows as $index => $document)
                <div class="border rounded p-4 mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <x-metronic.form-group name="documents.{{ $index }}.type" label="Jenis Dokumen">
                                <select name="documents[{{ $index }}][type]" class="form-select">
                                    <option value="">Pilih jenis dokumen</option>
                                    @foreach($documentTypes as $value => $label)
                                        <option value="{{ $value }}" @selected(($document['type'] ?? '') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </x-metronic.form-group>
                        </div>
                        <div class="col-md-4">
                            <x-metronic.form-group name="documents.{{ $index }}.name" label="Nama Dokumen">
                                <input name="documents[{{ $index }}][name]" value="{{ $document['name'] ?? '' }}" class="form-control" placeholder="Contoh: NPWP PT Maju Jaya">
                            </x-metronic.form-group>
                        </div>
                        <div class="col-md-4">
                            <x-metronic.form-group name="documents.{{ $index }}.document_number" label="Nomor Dokumen">
                                <input name="documents[{{ $index }}][document_number]" value="{{ $document['document_number'] ?? '' }}" class="form-control">
                            </x-metronic.form-group>
                        </div>
                        <div class="col-md-3">
                            <x-metronic.form-group name="documents.{{ $index }}.issued_at" label="Tanggal Terbit">
                                <input type="date" name="documents[{{ $index }}][issued_at]" value="{{ $document['issued_at'] ?? '' }}" class="form-control">
                            </x-metronic.form-group>
                        </div>
                        <div class="col-md-3">
                            <x-metronic.form-group name="documents.{{ $index }}.expires_at" label="Tanggal Kedaluwarsa / Masa Berlaku">
                                <input type="date" name="documents[{{ $index }}][expires_at]" value="{{ $document['expires_at'] ?? '' }}" class="form-control">
                            </x-metronic.form-group>
                        </div>
                        <div class="col-md-6">
                            <x-metronic.form-group name="documents.{{ $index }}.file" label="Upload File" help="Format PDF/JPG/PNG, maksimal 4 MB.">
                                <input type="file" name="documents[{{ $index }}][file]" class="form-control @error('documents.'.$index.'.file') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png">
                            </x-metronic.form-group>
                        </div>
                        <div class="col-md-12">
                            <x-metronic.form-group name="documents.{{ $index }}.notes" label="Catatan">
                                <input name="documents[{{ $index }}][notes]" value="{{ $document['notes'] ?? '' }}" class="form-control">
                            </x-metronic.form-group>
                        </div>
                    </div>
                </div>
            @endforeach
        </x-metronic.card>
    @endunless

    <div class="d-flex justify-content-end gap-3 mt-6">
        @unless($isEdit)
            <a href="{{ route('admin.customers.registration-form') }}" target="_blank" class="btn btn-light-info">
                <i class="ki-outline ki-printer fs-4 me-1"></i>Cetak Formulir Pendaftaran
            </a>
        @endunless
        <a href="{{ $isEdit ? route('admin.customers.show', $customer) : route('admin.customers.index') }}" class="btn btn-light">Batal</a>
        <button class="btn btn-primary">{{ $isEdit ? 'Simpan Pelanggan' : 'Simpan Pelanggan' }}</button>
    </div>
</form>
