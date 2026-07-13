@extends('layouts.metronic.app')

@section('title', 'Konfigurasi Umum Sistem - ' . config('app.name'))
@section('page_title', 'Konfigurasi Umum')

@section('content')
    <form method="POST" action="{{ route('admin.settings.general.update') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')
        <div class="row g-6">
            <div class="col-lg-6">
                <x-metronic.card title="Identitas Perusahaan">
                    <x-metronic.form-group name="company_name" label="Nama Perusahaan" required>
                        <input name="company_name" value="{{ old('company_name', $settings['company_name']) }}" class="form-control @error('company_name') is-invalid @enderror" required>
                    </x-metronic.form-group>
                    <x-metronic.form-group name="company_address" label="Alamat Perusahaan">
                        <textarea name="company_address" rows="3" class="form-control @error('company_address') is-invalid @enderror">{{ old('company_address', $settings['company_address']) }}</textarea>
                    </x-metronic.form-group>
                    <x-metronic.form-group name="company_phone" label="Telepon Perusahaan">
                        <input name="company_phone" value="{{ old('company_phone', $settings['company_phone']) }}" class="form-control @error('company_phone') is-invalid @enderror">
                    </x-metronic.form-group>
                    <x-metronic.form-group name="logo" label="Logo" help="Format gambar, maksimal 2 MB.">
                        <input type="file" name="logo" class="form-control @error('logo') is-invalid @enderror" accept="image/*">
                    </x-metronic.form-group>
                </x-metronic.card>
            </div>
            <div class="col-lg-6">
                <x-metronic.card title="Preferensi Sistem">
                    <div class="row">
                        <div class="col-md-6">
                            <x-metronic.form-group name="timezone" label="Timezone" required>
                                <input name="timezone" value="{{ old('timezone', $settings['timezone']) }}" class="form-control @error('timezone') is-invalid @enderror" required>
                            </x-metronic.form-group>
                        </div>
                        <div class="col-md-3">
                            <x-metronic.form-group name="locale" label="Locale" required>
                                <input name="locale" value="{{ old('locale', $settings['locale']) }}" class="form-control @error('locale') is-invalid @enderror" required>
                            </x-metronic.form-group>
                        </div>
                        <div class="col-md-3">
                            <x-metronic.form-group name="currency" label="Mata Uang" required>
                                <input name="currency" value="{{ old('currency', $settings['currency']) }}" class="form-control @error('currency') is-invalid @enderror" required>
                            </x-metronic.form-group>
                        </div>
                    </div>
                    <x-metronic.form-group name="upload_limit_mb" label="Batas Upload (MB)" required>
                        <input type="number" min="1" max="100" name="upload_limit_mb" value="{{ old('upload_limit_mb', $settings['upload_limit_mb']) }}" class="form-control @error('upload_limit_mb') is-invalid @enderror" required>
                    </x-metronic.form-group>
                    <x-metronic.form-group name="default_minimum_margin_percent" label="Default Margin Minimum (%)" required>
                        <input type="number" step="0.01" min="0" max="100" name="default_minimum_margin_percent" value="{{ old('default_minimum_margin_percent', $settings['default_minimum_margin_percent']) }}" class="form-control @error('default_minimum_margin_percent') is-invalid @enderror" required>
                    </x-metronic.form-group>
                    <x-metronic.form-group name="overpricing_tolerance_percent" label="Toleransi Overpricing (%)" required>
                        <input type="number" step="0.01" min="0" max="100" name="overpricing_tolerance_percent" value="{{ old('overpricing_tolerance_percent', $settings['overpricing_tolerance_percent']) }}" class="form-control @error('overpricing_tolerance_percent') is-invalid @enderror" required>
                    </x-metronic.form-group>
                    <x-metronic.form-group name="invoice_template" label="Template Invoice" required>
                        <input name="invoice_template" value="{{ old('invoice_template', $settings['invoice_template']) }}" class="form-control @error('invoice_template') is-invalid @enderror" required>
                    </x-metronic.form-group>
                    <x-metronic.form-group name="receipt_template" label="Template Struk" required>
                        <input name="receipt_template" value="{{ old('receipt_template', $settings['receipt_template']) }}" class="form-control @error('receipt_template') is-invalid @enderror" required>
                    </x-metronic.form-group>
                </x-metronic.card>
            </div>
        </div>
        <div class="d-flex justify-content-end mt-6">
            <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
        </div>
    </form>
@endsection
