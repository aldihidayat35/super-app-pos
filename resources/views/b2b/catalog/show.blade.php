@extends('layouts.metronic.app')

@section('title', $product->name)
@section('page_title', 'Detail Produk B2B')

@section('content')
    <x-metronic.page-title :title="$product->name" :description="$product->sku">
        <a href="{{ route('langganan.katalog.index') }}" class="btn btn-light">Kembali</a>
    </x-metronic.page-title>
    <div class="row g-5">
        <div class="col-lg-5"><x-metronic.card>
            <div class="ratio ratio-1x1 bg-light rounded d-flex align-items-center justify-content-center">
                @if($product->main_image_path)<img src="{{ Storage::url($product->main_image_path) }}" class="rounded object-fit-cover w-100 h-100" alt="{{ $product->name }}">@else<span class="text-muted">Tidak ada foto</span>@endif
            </div>
        </x-metronic.card></div>
        <div class="col-lg-7"><x-metronic.card title="Informasi Produk">
            <div class="mb-4">{{ $product->description ?: 'Deskripsi produk belum tersedia.' }}</div>
            <div class="row g-3 mb-5">
                <div class="col-md-6"><div class="text-muted">Kategori</div><div class="fw-bold">{{ $product->category?->name ?: '-' }}</div></div>
                <div class="col-md-6"><div class="text-muted">Merek</div><div class="fw-bold">{{ $product->brand?->name ?: '-' }}</div></div>
                <div class="col-md-6"><div class="text-muted">Minimum Qty</div><div class="fw-bold">{{ $product->minimum_order }} {{ $product->baseUnit?->symbol }}</div></div>
                <div class="col-md-6"><div class="text-muted">Stok</div><span class="badge badge-light-{{ $availability === 'available' ? 'success' : ($availability === 'limited' ? 'warning' : 'danger') }}">{{ $availability === 'available' ? 'Tersedia' : ($availability === 'limited' ? 'Terbatas' : 'Kosong') }}</span></div>
                <div class="col-md-6"><div class="text-muted">Berat</div><div class="fw-bold">{{ $product->weight ?: '-' }}</div></div>
                <div class="col-md-6"><div class="text-muted">Volume</div><div class="fw-bold">{{ $product->volume ?: '-' }}</div></div>
            </div>
            <h4 class="mb-3">Harga yang Bisa Dilihat</h4>
            <div class="table-responsive mb-5"><table class="table"><thead><tr><th>Sumber</th><th>Harga</th><th>Minimum</th></tr></thead><tbody>@foreach($prices as $price)<tr><td>{{ $price['reason'] }}</td><td class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($price['selected_price']) }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($price['minimum_price']) }}</td></tr>@endforeach</tbody></table></div>
            <form method="POST" action="{{ route('langganan.keranjang.add') }}" class="row g-3">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <div class="col-md-4"><label class="form-label">Satuan</label><select name="unit_id" class="form-select">@forelse($product->units->where('is_active', true)->where('is_sellable', true) as $unit)<option value="{{ $unit->unit_id }}">{{ $unit->unit?->name }} (x{{ $unit->conversion_factor }})</option>@empty<option value="{{ $product->base_unit_id }}">{{ $product->baseUnit?->name }}</option>@endforelse</select></div>
                <div class="col-md-4"><label class="form-label">Qty</label><input type="number" step="0.0001" min="{{ max(1, (float) $product->minimum_order) }}" name="quantity" value="{{ max(1, (float) $product->minimum_order) }}" class="form-control"></div>
                <div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary w-100">Tambah Keranjang</button></div>
            </form>
        </x-metronic.card></div>
    </div>
@endsection
