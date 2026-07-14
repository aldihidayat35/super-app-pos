@extends('layouts.metronic.app')

@section('title', 'Simulasi Margin - ' . config('app.name'))
@section('page_title', 'Simulasi Margin')

@section('content')
    @php($canSensitive = auth()->user()?->can('margins.view_sensitive'))
    <div class="row g-6">
        <div class="col-lg-5">
            <x-metronic.card title="Parameter Simulasi">
                <form method="GET" action="{{ route('pricing.simulator.index') }}">
                    <x-metronic.form-group name="product_id" label="Produk" required>
                        <select name="product_id" class="form-select"><option value="">Pilih produk</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(($filters['product_id'] ?? '') == $product->id)>{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select>
                    </x-metronic.form-group>
                    <div class="row">
                        <div class="col-md-6"><x-metronic.form-group name="channel" label="Channel" required><select name="channel" class="form-select"><option value="retail" @selected(($filters['channel'] ?? '') === 'retail')>Retail</option><option value="b2b" @selected(($filters['channel'] ?? '') === 'b2b')>B2B</option><option value="pos" @selected(($filters['channel'] ?? '') === 'pos')>POS</option></select></x-metronic.form-group></div>
                        <div class="col-md-6"><x-metronic.form-group name="quantity" label="Qty" required><input type="number" step="0.0001" min="0.0001" name="quantity" value="{{ $filters['quantity'] ?? 1 }}" class="form-control"></x-metronic.form-group></div>
                    </div>
                    <x-metronic.form-group name="branch_id" label="Cabang"><select name="branch_id" class="form-select"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') == $branch->id)>{{ $branch->name }}</option>@endforeach</select></x-metronic.form-group>
                    <x-metronic.form-group name="customer_id" label="Pelanggan"><select name="customer_id" class="form-select"><option value="">Umum</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(($filters['customer_id'] ?? '') == $customer->id)>{{ $customer->business_name }}</option>@endforeach</select></x-metronic.form-group>
                    <div class="row">
                        <div class="col-md-6"><x-metronic.form-group name="requested_price" label="Harga Uji"><input type="number" step="0.01" min="0" name="requested_price" value="{{ $filters['requested_price'] ?? '' }}" class="form-control"></x-metronic.form-group></div>
                        <div class="col-md-6"><x-metronic.form-group name="discount_percent" label="Diskon (%)"><input type="number" step="0.01" min="0" max="100" name="discount_percent" value="{{ $filters['discount_percent'] ?? 0 }}" class="form-control"></x-metronic.form-group></div>
                    </div>
                    <button class="btn btn-primary">Hitung Simulasi</button>
                </form>
            </x-metronic.card>
        </div>
        <div class="col-lg-7">
            <x-metronic.card title="Hasil Simulasi">
                @if($result)
                    <div class="row g-4 mb-6">
                        @if($canSensitive)<div class="col-md-4"><div class="border rounded p-4"><div class="text-muted">HPP</div><div class="fs-4 fw-bold">Rp {{ number_format((float) $result['hpp_base'], 0, ',', '.') }}</div></div></div>@endif
                        <div class="col-md-4"><div class="border rounded p-4"><div class="text-muted">Minimum</div><div class="fs-4 fw-bold">Rp {{ number_format((float) $result['minimum_price'], 0, ',', '.') }}</div></div></div>
                        <div class="col-md-4"><div class="border rounded p-4"><div class="text-muted">Harga Terpilih</div><div class="fs-4 fw-bold">Rp {{ number_format((float) $result['selected_price'], 0, ',', '.') }}</div></div></div>
                        <div class="col-md-4"><div class="border rounded p-4"><div class="text-muted">Setelah Diskon</div><div class="fs-4 fw-bold">Rp {{ number_format((float) $result['discounted_price'], 0, ',', '.') }}</div></div></div>
                    </div>
                    <div class="alert {{ $result['approval_required'] ? 'alert-warning' : 'alert-success' }}">{{ $result['reason'] }} @if($result['approval_required']) — Butuh approval: {{ implode(', ', $result['approval_reasons']) }} @endif</div>
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle">
                            <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Source</th><th>Harga</th><th>Prioritas</th><th>Alasan</th></tr></thead>
                            <tbody>
                            @forelse($result['candidates'] as $candidate)
                                <tr><td>{{ $candidate['source'] }}</td><td>Rp {{ number_format((float) $candidate['price'], 0, ',', '.') }}</td><td>{{ $candidate['priority'] }}</td><td>{{ $candidate['reason'] }}</td></tr>
                            @empty
                                <tr><td colspan="4">Tidak ada kandidat eksplisit, memakai fallback minimum.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    <x-metronic.empty-state title="Belum ada simulasi" description="Pilih produk dan channel untuk melihat minimum price, kandidat harga, margin, dan kebutuhan approval." />
                @endif
            </x-metronic.card>
        </div>
    </div>
@endsection
