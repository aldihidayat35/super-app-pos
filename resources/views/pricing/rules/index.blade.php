@extends('layouts.metronic.app')

@section('title', 'Aturan Harga dan Margin - ' . config('app.name'))
@section('page_title', 'Aturan Harga dan Margin')

@section('content')
    <div class="row g-6">
        <div class="col-lg-4">
            <x-metronic.card title="Tambah / Ubah Aturan">
                <form method="POST" action="{{ route('pricing.rules.store') }}">
                    @csrf
                    <x-metronic.form-group name="name" label="Nama Aturan" required>
                        <input name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Default Retail">
                    </x-metronic.form-group>
                    <div class="row">
                        <div class="col-md-6">
                            <x-metronic.form-group name="channel" label="Channel" required>
                                <select name="channel" class="form-select">
                                    @foreach($channels as $value => $label)
                                        <option value="{{ $value }}" @selected(old('channel', 'all') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </x-metronic.form-group>
                        </div>
                        <div class="col-md-6">
                            <x-metronic.form-group name="margin_method" label="Metode" required>
                                <select name="margin_method" class="form-select">
                                    <option value="percent">Persen</option>
                                    <option value="nominal">Nominal</option>
                                </select>
                            </x-metronic.form-group>
                        </div>
                    </div>
                    <x-metronic.form-group name="branch_id" label="Cabang">
                        <select name="branch_id" class="form-select"><option value="">Semua Cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                    </x-metronic.form-group>
                    <x-metronic.form-group name="customer_category" label="Kategori Customer">
                        <input name="customer_category" value="{{ old('customer_category') }}" class="form-control" placeholder="retail / grosir / vip">
                    </x-metronic.form-group>
                    <div class="row">
                        <div class="col-md-6"><x-metronic.form-group name="minimum_margin_percent" label="Margin Min (%)"><input type="number" step="0.01" min="0" name="minimum_margin_percent" value="{{ old('minimum_margin_percent', 20) }}" class="form-control"></x-metronic.form-group></div>
                        <div class="col-md-6"><x-metronic.form-group name="minimum_margin_amount" label="Margin Min (Rp)"><input type="number" step="0.01" min="0" name="minimum_margin_amount" value="{{ old('minimum_margin_amount', 0) }}" class="form-control"></x-metronic.form-group></div>
                        <div class="col-md-6"><x-metronic.form-group name="overpricing_tolerance_percent" label="Toleransi Overpricing (%)"><input type="number" step="0.01" min="0" name="overpricing_tolerance_percent" value="{{ old('overpricing_tolerance_percent', 50) }}" class="form-control"></x-metronic.form-group></div>
                        <div class="col-md-6"><x-metronic.form-group name="max_discount_percent" label="Diskon Maks (%)"><input type="number" step="0.01" min="0" max="100" name="max_discount_percent" value="{{ old('max_discount_percent', 10) }}" class="form-control"></x-metronic.form-group></div>
                    </div>
                    <x-metronic.form-group name="approval_threshold_amount" label="Threshold Approval">
                        <input type="number" step="0.01" min="0" name="approval_threshold_amount" value="{{ old('approval_threshold_amount', 0) }}" class="form-control">
                    </x-metronic.form-group>
                    <div class="row">
                        <div class="col-md-4"><x-metronic.form-group name="priority" label="Prioritas"><input type="number" min="1" name="priority" value="{{ old('priority', 100) }}" class="form-control"></x-metronic.form-group></div>
                        <div class="col-md-4"><x-metronic.form-group name="starts_at" label="Mulai"><input type="date" name="starts_at" value="{{ old('starts_at') }}" class="form-control"></x-metronic.form-group></div>
                        <div class="col-md-4"><x-metronic.form-group name="ends_at" label="Selesai"><input type="date" name="ends_at" value="{{ old('ends_at') }}" class="form-control"></x-metronic.form-group></div>
                    </div>
                    <x-metronic.form-group name="notes" label="Catatan">
                        <textarea name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                    </x-metronic.form-group>
                    <button class="btn btn-primary" @cannot('prices.update') disabled @endcannot>Simpan Aturan</button>
                </form>
            </x-metronic.card>
        </div>
        <div class="col-lg-8">
            <x-metronic.card title="Daftar Aturan">
                <div class="alert alert-info">Urutan resolusi harga: harga khusus customer approved → ring kategori customer → ring cabang → ring channel/global → fallback HPP + margin minimum.</div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Aturan</th><th>Scope</th><th>Margin</th><th>Batas</th><th>Periode</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($rules as $rule)
                            <tr>
                                <td class="fw-semibold">{{ $rule->name }}<div class="text-muted">Prioritas {{ $rule->priority }}</div></td>
                                <td>{{ strtoupper($rule->channel) }}<div class="text-muted">{{ $rule->branch?->name ?? 'Semua cabang' }} · {{ $rule->customer_category ?: 'Semua kategori' }}</div></td>
                                <td>{{ $rule->margin_method === 'percent' ? $rule->minimum_margin_percent.'%' : 'Rp '.number_format((float) $rule->minimum_margin_amount, 0, ',', '.') }}</td>
                                <td>Over {{ $rule->overpricing_tolerance_percent }}%<div class="text-muted">Diskon maks {{ $rule->max_discount_percent }}%</div></td>
                                <td>{{ $rule->starts_at?->format('d/m/Y') ?? 'Sekarang' }} - {{ $rule->ends_at?->format('d/m/Y') ?? 'Tanpa batas' }}</td>
                                <td><x-metronic.status-badge :status="$rule->is_active ? 'active' : 'inactive'" :label="$rule->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-metronic.empty-state title="Belum ada aturan harga" description="Sistem akan memakai fallback margin minimum sampai aturan dibuat." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $rules->links() }}
            </x-metronic.card>
        </div>
    </div>
@endsection
