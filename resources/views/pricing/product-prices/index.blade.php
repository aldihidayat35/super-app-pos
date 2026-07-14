@extends('layouts.metronic.app')

@section('title', 'Harga Produk per Ring/Cabang - ' . config('app.name'))
@section('page_title', 'Harga Produk per Ring/Cabang')

@section('toolbar_actions')
    <a href="{{ route('pricing.product-prices.export', request()->query()) }}" class="btn btn-light-success"><i class="ki-outline ki-file-down"></i> Export Harga</a>
@endsection

@section('content')
    @php($canSensitive = auth()->user()?->can('margins.view_sensitive'))
    <x-metronic.card title="Input Ring Harga">
        <form method="POST" action="{{ route('pricing.product-prices.store') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-4"><x-metronic.form-group name="product_ids" label="Produk" required help="Pilih lebih dari satu produk untuk assignment massal."><select name="product_ids[]" class="form-select" multiple size="5">@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="channel" label="Channel" required><select name="channel" class="form-select"><option value="retail">Retail</option><option value="b2b">B2B</option><option value="pos">POS</option><option value="all">Semua</option></select></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="price_ring" label="Ring" required><input name="price_ring" value="ring_1" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-4"><x-metronic.form-group name="branch_id" label="Cabang"><select name="branch_id" class="form-select"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="customer_category" label="Kategori"><input name="customer_category" class="form-control" placeholder="grosir"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="min_price" label="Harga Min"><input type="number" step="0.01" min="0" name="min_price" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="recommended_price" label="Harga Rekomendasi" required><input type="number" step="0.01" min="0" name="recommended_price" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="max_price" label="Harga Maks"><input type="number" step="0.01" min="0" name="max_price" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="minimum_qty" label="Min Qty"><input type="number" step="0.0001" min="0" name="minimum_qty" value="1" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="priority" label="Prioritas"><input type="number" min="1" name="priority" value="100" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="starts_at" label="Mulai"><input type="date" name="starts_at" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="ends_at" label="Selesai"><input type="date" name="ends_at" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-12"><x-metronic.form-group name="notes" label="Alasan/Catatan"><textarea name="notes" rows="2" class="form-control"></textarea></x-metronic.form-group></div>
            <div class="col-md-12"><button class="btn btn-primary" @cannot('prices.update') disabled @endcannot>Simpan Harga</button></div>
        </form>
    </x-metronic.card>

    <x-metronic.card title="Daftar Harga Produk" class="mt-6">
        <form method="GET" class="row g-3 mb-6">
            <div class="col-md-5"><select name="product_id" class="form-select"><option value="">Semua produk</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(($filters['product_id'] ?? '') == $product->id)>{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="channel" class="form-select"><option value="">Semua channel</option><option value="retail" @selected(($filters['channel'] ?? '') === 'retail')>Retail</option><option value="b2b" @selected(($filters['channel'] ?? '') === 'b2b')>B2B</option><option value="pos" @selected(($filters['channel'] ?? '') === 'pos')>POS</option></select></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th>@if($canSensitive)<th>HPP</th>@endif<th>Scope</th><th>Ring</th><th>Min / Rekomendasi / Maks</th><th>Periode</th><th>Status</th><th>Warning</th></tr></thead>
                <tbody>
                @forelse($prices as $price)
                    @php($resolved = $resolver->resolve($price->product, branch: $price->branch, channel: $price->channel === 'all' ? 'retail' : $price->channel, user: auth()->user(), requestedPrice: $price->recommended_price))
                    <tr>
                        <td>{{ $price->product?->sku }}<div class="text-muted">{{ $price->product?->name }}</div></td>
                        @if($canSensitive)<td>Rp {{ number_format((float) $resolved['hpp_base'], 0, ',', '.') }}</td>@endif
                        <td>{{ strtoupper($price->channel) }}<div class="text-muted">{{ $price->branch?->name ?? 'Semua cabang' }} · {{ $price->customer_category ?: 'Semua kategori' }}</div></td>
                        <td>{{ $price->price_ring }}<div class="text-muted">Prioritas {{ $price->priority }}</div></td>
                        <td>Rp {{ number_format((float) $price->min_price, 0, ',', '.') }} / <strong>Rp {{ number_format((float) $price->recommended_price, 0, ',', '.') }}</strong> / Rp {{ number_format((float) $price->max_price, 0, ',', '.') }}</td>
                        <td>{{ $price->starts_at?->format('d/m/Y') ?? 'Sekarang' }} - {{ $price->ends_at?->format('d/m/Y') ?? 'Tanpa batas' }}</td>
                        <td><x-metronic.status-badge :status="$price->status" /></td>
                        <td>@if($resolved['approval_required'])<span class="badge badge-light-danger">{{ implode(', ', $resolved['approval_reasons']) }}</span>@else<span class="badge badge-light-success">Aman</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $canSensitive ? 8 : 7 }}"><x-metronic.empty-state title="Belum ada harga produk" description="Tambahkan ring harga untuk retail, POS, atau B2B." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $prices->links() }}
    </x-metronic.card>
@endsection
