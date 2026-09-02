@extends('layouts.metronic.app')

@section('title', 'Pengaturan Pajak')

@section('page_title', 'Pengaturan Pajak')
@section('page_description', 'Profil PKP, aturan efektif, dan klasifikasi objek pajak.')

@section('page_guide')
    <x-metronic.page-guide id="tax-settings" title="Panduan Pengaturan Pajak">
        <x-slot:function>Mengatur identitas perusahaan, tarif bertanggal efektif, klasifikasi produk, dan identitas pajak lawan transaksi.</x-slot:function>
        <x-slot:workflow>Buat aturan → simpan profil PKP → pilih aturan default → klasifikasikan produk dan mitra → aktifkan kalkulasi.</x-slot:workflow>
        <x-slot:parts>Profil pajak, master aturan, klasifikasi produk, customer, dan supplier.</x-slot:parts>
        <x-slot:impacts>Mengaktifkan kalkulasi memengaruhi transaksi POS dan B2B baru. Transaksi lama tidak dihitung ulang otomatis.</x-slot:impacts>
        <x-slot:warnings>Pastikan tarif, faktor DPP, serta tanggal efektif telah diverifikasi oleh penanggung jawab pajak perusahaan.</x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-6">
        <a href="{{ route('tax.index') }}" class="btn btn-light"><i class="ki-outline ki-arrow-left fs-5"></i> Kembali ke Laporan</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <x-metronic.card title="Profil Pajak Perusahaan" class="mb-6">
        <form method="POST" action="{{ route('tax.profile.store') }}" class="row g-4">@csrf
            <div class="col-md-4"><label class="form-label required">Nama Legal</label><input name="legal_name" value="{{ old('legal_name', $profile?->legal_name ?? config('app.name')) }}" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">NPWP</label><input name="tax_number" value="{{ old('tax_number', $profile?->tax_number) }}" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">NITKU</label><input name="nitku" value="{{ old('nitku', $profile?->nitku) }}" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Efektif PKP</label><input type="date" name="pkp_effective_date" value="{{ old('pkp_effective_date', $profile?->pkp_effective_date?->format('Y-m-d')) }}" class="form-control"></div>
            <div class="col-md-2 d-flex align-items-end"><label class="form-check form-check-custom mb-3"><input type="hidden" name="is_pkp" value="0"><input type="checkbox" name="is_pkp" value="1" class="form-check-input" @checked(old('is_pkp', $profile?->is_pkp))><span class="form-check-label">PKP</span></label></div>
            <div class="col-md-6"><label class="form-label">Alamat Pajak</label><textarea name="tax_address" rows="2" class="form-control">{{ old('tax_address', $profile?->tax_address) }}</textarea></div>
            <div class="col-md-3"><label class="form-label">Penandatangan</label><input name="signatory_name" value="{{ old('signatory_name', $profile?->signatory_name) }}" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">NPWP/NIK Penandatangan</label><input name="signatory_tax_number" value="{{ old('signatory_tax_number', $profile?->signatory_tax_number) }}" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Aturan Keluaran Default</label><select name="default_output_tax_rule_id" class="form-select"><option value="">Tidak ada</option>@foreach($rules->whereIn('direction', ['output', 'both']) as $rule)<option value="{{ $rule->id }}" @selected(old('default_output_tax_rule_id', $profile?->default_output_tax_rule_id) == $rule->id)>{{ $rule->code }} — {{ $rule->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Aturan Masukan Default</label><select name="default_input_tax_rule_id" class="form-select"><option value="">Tidak ada</option>@foreach($rules->whereIn('direction', ['input', 'both']) as $rule)<option value="{{ $rule->id }}" @selected(old('default_input_tax_rule_id', $profile?->default_input_tax_rule_id) == $rule->id)>{{ $rule->code }} — {{ $rule->name }}</option>@endforeach</select></div>
            <div class="col-md-2 d-flex align-items-end"><label class="form-check form-check-custom mb-3"><input type="hidden" name="calculation_enabled" value="0"><input type="checkbox" name="calculation_enabled" value="1" class="form-check-input" @checked(old('calculation_enabled', $profile?->calculation_enabled))><span class="form-check-label">Aktifkan kalkulasi</span></label></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Simpan Profil</button></div>
            <div class="col-12"><div class="alert alert-light-warning mb-0">Kalkulasi hanya berjalan jika status PKP dan “Aktifkan kalkulasi” dicentang, aturan default aktif tersedia, dan produk ditandai kena pajak.</div></div>
        </form>
    </x-metronic.card>

    <x-metronic.card title="Master Aturan Pajak Bertanggal Efektif" class="mb-6">
        <form method="POST" action="{{ route('tax.rules.store') }}" class="row g-3 mb-6">@csrf
            <div class="col-md-2"><label class="form-label required">Kode</label><input name="code" placeholder="PPN-NONMEWAH" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label required">Nama</label><input name="name" placeholder="PPN BKP/JKP Nonmewah" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Jenis</label><select name="tax_type" class="form-select">@foreach($taxTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Arah</label><select name="direction" class="form-select"><option value="both">Masukan & keluaran</option><option value="output">Keluaran</option><option value="input">Masukan</option></select></div>
            <div class="col-md-1"><label class="form-label">Tarif %</label><input type="number" step="0.0001" name="rate" value="12" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Faktor DPP</label><input type="number" step="0.00000001" min="0.00000001" max="1" name="dpp_factor" value="0.91666667" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">PPnBM %</label><input type="number" step="0.0001" name="luxury_tax_rate" value="0" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Mulai Berlaku</label><input type="date" name="effective_from" value="{{ now()->toDateString() }}" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Akhir Berlaku</label><input type="date" name="effective_until" class="form-control"></div>
            <div class="col-md-2 d-flex align-items-end"><label class="form-check form-check-custom mb-3"><input type="hidden" name="is_creditable" value="0"><input type="checkbox" name="is_creditable" value="1" class="form-check-input" checked><span class="form-check-label">Dapat dikreditkan</span></label></div>
            <div class="col-md-2 d-flex align-items-end"><label class="form-check form-check-custom mb-3"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="form-check-input" checked><span class="form-check-label">Aktif</span></label></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Tambah Aturan</button></div>
        </form>
        <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold"><th>Kode/Nama</th><th>Jenis/Arah</th><th>Tarif</th><th>DPP</th><th>Periode</th><th>Status</th></tr></thead><tbody>
            @forelse($rules as $rule)<tr><td class="fw-semibold">{{ $rule->code }}<div class="text-muted">{{ $rule->name }}</div></td><td>{{ $rule->tax_type->label() }}<div class="text-muted">{{ $rule->direction }}</div></td><td>{{ $rule->rate }}%<div class="text-muted">PPnBM {{ $rule->luxury_tax_rate }}%</div></td><td>{{ $rule->dpp_factor }}</td><td>{{ $rule->effective_from->format('d/m/Y') }} – {{ $rule->effective_until?->format('d/m/Y') ?? 'seterusnya' }}</td><td><span class="badge badge-light-{{ $rule->is_active ? 'success' : 'secondary' }}">{{ $rule->is_active ? 'Aktif' : 'Nonaktif' }}</span></td></tr>
            @empty<tr><td colspan="6"><x-metronic.empty-state title="Belum ada aturan pajak" description="Tambahkan aturan sebelum mengaktifkan kalkulasi." /></td></tr>@endforelse
        </tbody></table></div>
    </x-metronic.card>

    <div class="row g-6 mb-6">
        <div class="col-lg-6">
            <x-metronic.card title="Klasifikasi Produk">
                <form method="POST" action="" id="product-tax-form" class="row g-3">@csrf @method('PUT')
                    <div class="col-12"><label class="form-label">Produk</label><select id="tax-product" class="form-select" required><option value="">Pilih produk</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }}{{ $product->is_taxable ? ' (kena pajak)' : '' }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">Aturan Pajak</label><select name="tax_rule_id" class="form-select"><option value="">Gunakan aturan default</option>@foreach($rules as $rule)<option value="{{ $rule->id }}">{{ $rule->code }} — {{ $rule->name }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label">Kode Kategori</label><input name="tax_category_code" class="form-control" placeholder="BKP-NONMEWAH"></div>
                    <div class="col-md-2 d-flex align-items-end"><label class="form-check form-check-custom mb-3"><input type="hidden" name="is_taxable" value="0"><input type="checkbox" name="is_taxable" value="1" class="form-check-input"><span class="form-check-label">Kena pajak</span></label></div>
                    <div class="col-12"><button class="btn btn-primary">Simpan Klasifikasi</button></div>
                </form>
            </x-metronic.card>
        </div>
        <div class="col-lg-6">
            <x-metronic.card title="Identitas Pajak Lawan Transaksi">
                <form method="POST" action="{{ route('tax.counterparties.update') }}" class="row g-3">@csrf @method('PUT')
                    <div class="col-md-4"><label class="form-label">Tipe</label><select name="party_type" id="party-type" class="form-select"><option value="customer">Customer</option><option value="supplier">Supplier</option></select></div>
                    <div class="col-md-8"><label class="form-label">Lawan Transaksi</label><select name="party_id" id="party-id" class="form-select" required><optgroup label="Customer" id="customer-options">@foreach($customers as $customer)<option value="{{ $customer->id }}" data-type="customer">{{ $customer->code }} — {{ $customer->business_name }}</option>@endforeach</optgroup><optgroup label="Supplier" id="supplier-options">@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" data-type="supplier">{{ $supplier->code }} — {{ $supplier->name }}</option>@endforeach</optgroup></select></div>
                    <div class="col-md-4"><label class="form-label">NPWP/NIK</label><input name="tax_number" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label">Jenis Identitas</label><select name="tax_identity_type" class="form-select"><option value="npwp">NPWP</option><option value="nik">NIK</option><option value="passport">Paspor</option><option value="other">Lainnya</option></select></div>
                    <div class="col-md-3"><label class="form-label">Alamat Pajak</label><input name="tax_address" class="form-control"></div>
                    <div class="col-md-2 d-flex align-items-end"><label class="form-check form-check-custom mb-3"><input type="hidden" name="is_pkp" value="0"><input type="checkbox" name="is_pkp" value="1" class="form-check-input"><span class="form-check-label">PKP</span></label></div>
                    <div class="col-12"><button class="btn btn-primary">Simpan Identitas</button></div>
                </form>
            </x-metronic.card>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const product = document.getElementById('tax-product');
    const productForm = document.getElementById('product-tax-form');
    product?.addEventListener('change', function () {
        productForm.action = @json(url('/tax/settings/products')) + '/' + this.value;
    });

    const type = document.getElementById('party-type');
    const party = document.getElementById('party-id');
    const filterParty = function () {
        const wanted = type.value;
        Array.from(party.options).forEach(function (option) {
            if (!option.dataset.type) return;
            option.hidden = option.dataset.type !== wanted;
            option.disabled = option.dataset.type !== wanted;
        });
        const first = Array.from(party.options).find(option => option.dataset.type === wanted);
        if (first) party.value = first.value;
    };
    type?.addEventListener('change', filterParty);
    filterParty();
});
</script>
@endpush
