@extends('layouts.metronic.app')

@section('title', 'Kelola Harga Produk - '.config('app.name'))
@section('page_title', 'Kelola Harga Produk')

@section('toolbar_actions')
    <a href="{{ route('pricing.product-prices.index') }}" class="btn btn-light"><i class="ki-outline ki-arrow-left fs-5"></i> Kembali</a>
@endsection

@section('content')
    @if($errors->any())
        <div class="alert alert-danger"><div class="fw-bold mb-1">Perubahan belum dapat disimpan.</div>{{ $errors->first() }}</div>
    @endif

    <div class="row g-6">
        <div class="col-xl-4">
            <x-metronic.card title="Harga Saat Ini">
                <div class="fw-bold fs-4">{{ $price->product?->name }}</div>
                <div class="text-muted mb-5">{{ $price->product?->sku }}</div>
                <div class="d-flex justify-content-between border-bottom py-3"><span>Harga</span><strong>{{ App\Support\CurrencyFormatter::rupiah($price->recommended_price) }}</strong></div>
                <div class="d-flex justify-content-between border-bottom py-3"><span>Channel / Ring</span><strong>{{ strtoupper($price->channel) }} / {{ $price->price_ring }}</strong></div>
                <div class="d-flex justify-content-between border-bottom py-3"><span>Cabang</span><strong>{{ $price->branch?->name ?? 'Semua cabang' }}</strong></div>
                <div class="d-flex justify-content-between border-bottom py-3"><span>Periode</span><strong>{{ $price->starts_at?->format('d/m/Y') ?? 'Sekarang' }} – {{ $price->ends_at?->format('d/m/Y') ?? 'Tanpa batas' }}</strong></div>
                <div class="d-flex justify-content-between py-3"><span>Status</span><x-metronic.status-badge :status="$price->status" /></div>
                <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-4 mt-4">
                    <i class="ki-outline ki-information-5 text-primary fs-2 me-3"></i>
                    <div class="text-gray-700 fs-7">Harga aktif tidak diubah langsung. Buat revisi baru, selesaikan approval bila diminta, lalu akhiri harga lama.</div>
                </div>
            </x-metronic.card>

            @if($price->status !== App\Enums\ProductPriceStatus::DRAFT)
                <x-metronic.card title="Akhiri Harga Lama" class="mt-6">
                    <form method="POST" action="{{ route('pricing.product-prices.end', $price) }}" id="end-product-price-{{ $price->id }}">
                        @csrf
                        <x-metronic.form-group name="ends_at" label="Tanggal Selesai" required>
                            <input type="date" name="ends_at" value="{{ old('ends_at', $price->ends_at?->toDateString() ?? now()->toDateString()) }}" class="form-control" required>
                        </x-metronic.form-group>
                        <x-metronic.form-group name="reason" label="Alasan" required>
                            <textarea name="reason" class="form-control" rows="3" required>{{ old('reason') }}</textarea>
                        </x-metronic.form-group>
                        <button class="btn btn-light-danger w-100" data-confirm data-confirm-form="end-product-price-{{ $price->id }}" data-confirm-title="Akhiri masa berlaku harga?" data-confirm-text="Harga tidak digunakan setelah tanggal selesai, tetapi historinya tetap tersimpan." data-confirm-button="Ya, akhiri" type="submit"><i class="ki-outline ki-calendar fs-5"></i> Akhiri Masa Berlaku</button>
                    </form>
                </x-metronic.card>
            @endif
        </div>

        <div class="col-xl-8">
            <x-metronic.card :title="$price->status === App\Enums\ProductPriceStatus::DRAFT ? 'Edit Draft Harga' : 'Buat Revisi Harga Baru'">
                <form method="POST" action="{{ $price->status === App\Enums\ProductPriceStatus::DRAFT ? route('pricing.product-prices.update', $price) : route('pricing.product-prices.revise', $price) }}" class="row g-4">
                    @csrf
                    @if($price->status === App\Enums\ProductPriceStatus::DRAFT) @method('PUT') @endif
                    <input type="hidden" name="product_id" value="{{ $price->product_id }}">
                    <div class="col-md-4"><x-metronic.form-group name="channel" label="Channel" required><select name="channel" class="form-select" data-control="select2" data-hide-search="true">@foreach(['retail'=>'Retail','pos'=>'POS','b2b'=>'B2B','all'=>'Semua'] as $value=>$label)<option value="{{ $value }}" @selected(old('channel', $price->channel) === $value)>{{ $label }}</option>@endforeach</select></x-metronic.form-group></div>
                    <div class="col-md-4"><x-metronic.form-group name="price_ring" label="Ring Harga" required><input name="price_ring" value="{{ old('price_ring', $price->price_ring) }}" class="form-control" required></x-metronic.form-group></div>
                    <div class="col-md-4"><x-metronic.form-group name="branch_id" label="Cabang"><select name="branch_id" class="form-select" data-control="select2" data-placeholder="Semua cabang"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id', $price->branch_id) === (string) $branch->id)>{{ $branch->code }} — {{ $branch->name }}</option>@endforeach</select></x-metronic.form-group></div>
                    <div class="col-md-4"><x-metronic.form-group name="customer_category" label="Kategori Pelanggan"><input name="customer_category" value="{{ old('customer_category', $price->customer_category) }}" class="form-control"></x-metronic.form-group></div>
                    <div class="col-md-4"><x-metronic.form-group name="min_price" label="Harga Minimum"><input type="number" step="0.01" min="0" name="min_price" value="{{ old('min_price', $price->min_price) }}" class="form-control"></x-metronic.form-group></div>
                    <div class="col-md-4"><x-metronic.form-group name="recommended_price" label="Harga Rekomendasi" required><input type="number" step="0.01" min="0" name="recommended_price" value="{{ old('recommended_price', $price->recommended_price) }}" class="form-control" required></x-metronic.form-group></div>
                    <div class="col-md-4"><x-metronic.form-group name="max_price" label="Harga Maksimum"><input type="number" step="0.01" min="0" name="max_price" value="{{ old('max_price', $price->max_price) }}" class="form-control"></x-metronic.form-group></div>
                    <div class="col-md-4"><x-metronic.form-group name="minimum_qty" label="Minimum Qty"><input type="number" step="1" min="0" name="minimum_qty" value="{{ old('minimum_qty', qty_input($price->minimum_qty)) }}" class="form-control"></x-metronic.form-group></div>
                    <div class="col-md-4"><x-metronic.form-group name="priority" label="Prioritas"><input type="number" min="1" name="priority" value="{{ old('priority', $price->priority) }}" class="form-control"></x-metronic.form-group></div>
                    <div class="col-md-6"><x-metronic.form-group name="starts_at" label="Mulai Berlaku"><input type="date" name="starts_at" value="{{ old('starts_at', $price->status === App\Enums\ProductPriceStatus::DRAFT ? $price->starts_at?->toDateString() : now()->toDateString()) }}" class="form-control"></x-metronic.form-group></div>
                    <div class="col-md-6"><x-metronic.form-group name="ends_at" label="Selesai Berlaku"><input type="date" name="ends_at" value="{{ old('ends_at', $price->status === App\Enums\ProductPriceStatus::DRAFT ? $price->ends_at?->toDateString() : '') }}" class="form-control"></x-metronic.form-group></div>
                    <div class="col-12"><x-metronic.form-group name="notes" label="Alasan / Catatan" required><textarea name="notes" rows="3" class="form-control" required>{{ old('notes', $price->status === App\Enums\ProductPriceStatus::DRAFT ? $price->notes : '') }}</textarea></x-metronic.form-group></div>
                    <div class="col-12 text-end"><button class="btn btn-primary"><i class="ki-outline ki-check fs-5"></i> {{ $price->status === App\Enums\ProductPriceStatus::DRAFT ? 'Simpan Draft' : 'Buat Revisi Harga' }}</button></div>
                </form>
            </x-metronic.card>
        </div>
    </div>
@endsection
