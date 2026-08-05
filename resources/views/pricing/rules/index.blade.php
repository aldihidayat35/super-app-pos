@extends('layouts.metronic.app')

@section('title', 'Aturan Harga dan Margin - '.config('app.name'))
@section('page_title', 'Aturan Harga dan Margin')

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('price-rule-form');
    if (!form) return;

    const method = form.querySelector('[name="margin_method"]');
    const percentGroup = form.querySelector('[data-margin-group="percent"]');
    const nominalGroup = form.querySelector('[data-margin-group="nominal"]');
    const percentInput = form.querySelector('[name="minimum_margin_percent"]');
    const nominalInput = form.querySelector('[name="minimum_margin_amount"]');
    const title = document.getElementById('price-rule-form-title');
    const cancel = document.getElementById('cancel-rule-edit');

    const syncMargin = (clearInactive = false) => {
        const isPercent = method.value === 'percent';
        percentGroup.classList.toggle('d-none', !isPercent);
        nominalGroup.classList.toggle('d-none', isPercent);
        percentInput.disabled = !isPercent;
        nominalInput.disabled = isPercent;
        percentInput.required = isPercent;
        nominalInput.required = !isPercent;
        if (clearInactive) (isPercent ? nominalInput : percentInput).value = '';
    };

    method.addEventListener('change', () => syncMargin(true));
    syncMargin(false);

    document.querySelectorAll('[data-edit-price-rule]').forEach(button => {
        button.addEventListener('click', () => {
            const rule = JSON.parse(button.dataset.rule);
            Object.entries(rule).forEach(([key, value]) => {
                const input = form.querySelector(`[name="${key}"]`);
                if (!input) return;
                input.value = value ?? '';
                if (input.tagName === 'SELECT' && window.jQuery) window.jQuery(input).val(value ?? '').trigger('change');
            });
            title.textContent = 'Ubah Aturan Harga';
            cancel.classList.remove('d-none');
            syncMargin(false);
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    cancel.addEventListener('click', () => {
        form.reset();
        form.querySelector('[name="id"]').value = '';
        if (window.jQuery) window.jQuery(form.querySelector('[name="branch_id"]')).val('').trigger('change');
        title.textContent = 'Tambah Aturan Harga';
        cancel.classList.add('d-none');
        syncMargin(false);
    });

    form.addEventListener('submit', () => {
        syncMargin(true);
        const submit = form.querySelector('[type="submit"]');
        submit.disabled = true;
        submit.setAttribute('data-kt-indicator', 'on');
    });
});
</script>
@endpush

@section('content')
    <div class="row g-6">
        <div class="col-lg-4">
            <x-metronic.card>
                <div class="d-flex justify-content-between align-items-center mb-6">
                    <h3 class="card-title mb-0" id="price-rule-form-title">{{ old('id') ? 'Ubah Aturan Harga' : 'Tambah Aturan Harga' }}</h3>
                    <button type="button" id="cancel-rule-edit" class="btn btn-sm btn-light d-none">Batal Edit</button>
                </div>
                <form method="POST" action="{{ route('pricing.rules.store') }}" id="price-rule-form">
                    @csrf
                    <input type="hidden" name="id" value="{{ old('id') }}">
                    <x-metronic.form-group name="name" label="Nama Aturan" required><input name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Contoh: Retail Umum" required></x-metronic.form-group>
                    <div class="row">
                        <div class="col-md-6"><x-metronic.form-group name="channel" label="Channel" required><select name="channel" class="form-select" data-control="select2" data-hide-search="true">@foreach($channels as $value => $label)<option value="{{ $value }}" @selected(old('channel', 'all') === $value)>{{ $label }}</option>@endforeach</select></x-metronic.form-group></div>
                        <div class="col-md-6"><x-metronic.form-group name="margin_method" label="Metode Margin" required><select name="margin_method" class="form-select" data-control="select2" data-hide-search="true"><option value="percent" @selected(old('margin_method', 'percent') === 'percent')>Persentase</option><option value="nominal" @selected(old('margin_method') === 'nominal')>Nominal Tetap</option></select></x-metronic.form-group></div>
                    </div>
                    <x-metronic.form-group name="branch_id" label="Cabang" help="Kosongkan untuk menerapkan aturan ke semua cabang.">
                        <select name="branch_id" class="form-select" data-control="select2" data-placeholder="Pilih cabang" data-allow-clear="true"><option value=""></option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->code }} — {{ $branch->name }}</option>@endforeach</select>
                    </x-metronic.form-group>
                    <x-metronic.form-group name="customer_category" label="Kategori Pelanggan"><input name="customer_category" value="{{ old('customer_category') }}" class="form-control" placeholder="Contoh: grosir atau vip"></x-metronic.form-group>
                    <div data-margin-group="percent"><x-metronic.form-group name="minimum_margin_percent" label="Margin Minimum" required help="Persentase dihitung dari HPP produk."><div class="input-group"><input type="number" step="0.01" min="0" name="minimum_margin_percent" value="{{ old('minimum_margin_percent', 20) }}" class="form-control"><span class="input-group-text">%</span></div></x-metronic.form-group></div>
                    <div data-margin-group="nominal" class="d-none"><x-metronic.form-group name="minimum_margin_amount" label="Margin Minimum" required help="Nominal tetap yang ditambahkan ke HPP produk."><div class="input-group"><span class="input-group-text">Rp</span><input type="number" step="0.01" min="0" name="minimum_margin_amount" value="{{ old('minimum_margin_amount') }}" class="form-control"></div></x-metronic.form-group></div>
                    <div class="row">
                        <div class="col-md-6"><x-metronic.form-group name="overpricing_tolerance_percent" label="Toleransi Maksimum (%)"><input type="number" step="0.01" min="0" name="overpricing_tolerance_percent" value="{{ old('overpricing_tolerance_percent', 50) }}" class="form-control"></x-metronic.form-group></div>
                        <div class="col-md-6"><x-metronic.form-group name="max_discount_percent" label="Diskon Maksimum (%)"><input type="number" step="0.01" min="0" max="100" name="max_discount_percent" value="{{ old('max_discount_percent', 10) }}" class="form-control"></x-metronic.form-group></div>
                    </div>
                    <x-metronic.form-group name="approval_threshold_amount" label="Batas Nominal Approval"><div class="input-group"><span class="input-group-text">Rp</span><input type="number" step="0.01" min="0" name="approval_threshold_amount" value="{{ old('approval_threshold_amount', 0) }}" class="form-control"></div></x-metronic.form-group>
                    <div class="row"><div class="col-md-4"><x-metronic.form-group name="priority" label="Prioritas"><input type="number" min="1" name="priority" value="{{ old('priority', 100) }}" class="form-control"></x-metronic.form-group></div><div class="col-md-4"><x-metronic.form-group name="starts_at" label="Mulai"><input type="date" name="starts_at" value="{{ old('starts_at') }}" class="form-control"></x-metronic.form-group></div><div class="col-md-4"><x-metronic.form-group name="ends_at" label="Selesai"><input type="date" name="ends_at" value="{{ old('ends_at') }}" class="form-control"></x-metronic.form-group></div></div>
                    <x-metronic.form-group name="notes" label="Catatan"><textarea name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea></x-metronic.form-group>
                    <button class="btn btn-primary" type="submit" @cannot('prices.update') disabled @endcannot><span class="indicator-label"><i class="ki-outline ki-check fs-5"></i> Simpan Aturan</span><span class="indicator-progress">Menyimpan... <span class="spinner-border spinner-border-sm ms-2"></span></span></button>
                </form>
            </x-metronic.card>
        </div>
        <div class="col-lg-8">
            <x-metronic.card title="Daftar Aturan">
                <div class="alert alert-info">Urutan resolusi: harga khusus pelanggan → ring kategori → ring cabang → ring channel/global → HPP ditambah margin minimum.</div>
                <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Aturan</th><th>Scope</th><th>Margin</th><th>Batas</th><th>Periode</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
                    @forelse($rules as $rule)
                        @php($rulePayload = ['id'=>$rule->id,'name'=>$rule->name,'channel'=>$rule->channel,'branch_id'=>$rule->branch_id,'customer_category'=>$rule->customer_category,'margin_method'=>$rule->margin_method,'minimum_margin_percent'=>$rule->minimum_margin_percent,'minimum_margin_amount'=>$rule->minimum_margin_amount,'overpricing_tolerance_percent'=>$rule->overpricing_tolerance_percent,'max_discount_percent'=>$rule->max_discount_percent,'approval_threshold_amount'=>$rule->approval_threshold_amount,'priority'=>$rule->priority,'starts_at'=>$rule->starts_at?->toDateString(),'ends_at'=>$rule->ends_at?->toDateString(),'notes'=>$rule->notes])
                        <tr><td class="fw-semibold">{{ $rule->name }}<div class="text-muted">Prioritas {{ $rule->priority }}</div></td><td>{{ strtoupper($rule->channel) }}<div class="text-muted">{{ $rule->branch?->name ?? 'Semua cabang' }} · {{ $rule->customer_category ?: 'Semua kategori' }}</div></td><td>{{ $rule->margin_method === 'percent' ? $rule->minimum_margin_percent.'%' : App\Support\CurrencyFormatter::rupiah($rule->minimum_margin_amount) }}</td><td>Over {{ $rule->overpricing_tolerance_percent }}%<div class="text-muted">Diskon maks {{ $rule->max_discount_percent }}%</div></td><td>{{ $rule->starts_at?->format('d/m/Y') ?? 'Sekarang' }} – {{ $rule->ends_at?->format('d/m/Y') ?? 'Tanpa batas' }}</td><td><x-metronic.status-badge :status="$rule->is_active ? 'active' : 'inactive'" :label="$rule->is_active ? 'Aktif' : 'Nonaktif'" /></td><td class="text-end">@can('update', $rule)<button type="button" class="btn btn-sm btn-light-primary" data-edit-price-rule data-rule="{{ json_encode($rulePayload) }}"><i class="ki-outline ki-pencil fs-5"></i> Edit</button>@endcan</td></tr>
                    @empty<tr><td colspan="7"><x-metronic.empty-state title="Belum ada aturan harga" description="Sistem memakai fallback margin minimum sampai aturan dibuat." /></td></tr>@endforelse
                </tbody></table></div>{{ $rules->links() }}
            </x-metronic.card>
        </div>
    </div>
@endsection
