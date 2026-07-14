@extends('layouts.metronic.app')

@section('title', 'Katalog Langganan')
@section('page_title', 'Katalog Langganan')

@section('content')
    <x-metronic.page-title title="Katalog Langganan" description="Harga tampil sesuai ring dan kontrak {{ $customer->business_name }}.">
        <a href="{{ route('langganan.keranjang.index') }}" class="btn btn-primary">Keranjang</a>
    </x-metronic.page-title>
    <form method="GET" class="card card-body mb-5">
        <div class="row g-3">
            <div class="col-md-5"><input name="q" value="{{ $filters['q'] }}" class="form-control form-control-solid" placeholder="Cari SKU atau nama produk"></div>
            <div class="col-md-3"><select name="category_id" class="form-select form-select-solid"><option value="">Semua kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="sort" class="form-select form-select-solid"><option value="name" @selected($filters['sort'] === 'name')>Nama</option><option value="newest" @selected($filters['sort'] === 'newest')>Terbaru</option></select></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </div>
    </form>
    <div class="row g-5">
        @forelse($cards as $card)
            @php($product = $card['product'])
            <div class="col-md-6 col-xl-4">
                <x-metronic.card>
                    <div class="ratio ratio-16x9 bg-light rounded mb-4 d-flex align-items-center justify-content-center">
                        @if($product->main_image_path)<img src="{{ Storage::url($product->main_image_path) }}" class="rounded object-fit-cover w-100 h-100" alt="{{ $product->name }}">@else<span class="text-muted">Tidak ada foto</span>@endif
                    </div>
                    <div class="text-muted">{{ $product->sku }} · {{ $product->category?->name }}</div>
                    <a href="{{ route('langganan.katalog.show', $product) }}" class="fs-5 fw-bold text-gray-900 text-hover-primary">{{ $product->name }}</a>
                    <div class="mt-3 d-flex justify-content-between"><span>{{ $product->baseUnit?->symbol }}</span><span class="fw-bold">{{ $card['price'] }}</span></div>
                    <div class="mt-2"><span class="badge badge-light-{{ $card['availability'] === 'available' ? 'success' : ($card['availability'] === 'limited' ? 'warning' : 'danger') }}">{{ $card['availability'] === 'available' ? 'Tersedia' : ($card['availability'] === 'limited' ? 'Terbatas' : 'Kosong') }}</span></div>
                    <form method="POST" action="{{ route('langganan.keranjang.add') }}" class="row g-2 mt-3">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <input type="hidden" name="unit_id" value="{{ $product->base_unit_id }}">
                        <div class="col-5"><input type="number" step="0.0001" min="{{ max(1, (float) $product->minimum_order) }}" name="quantity" value="{{ max(1, (float) $product->minimum_order) }}" class="form-control form-control-sm"></div>
                        <div class="col-7"><button class="btn btn-sm btn-primary w-100">Tambah</button></div>
                    </form>
                </x-metronic.card>
            </div>
        @empty
            <div class="col-12"><x-metronic.empty-state title="Produk tidak ditemukan" description="Coba ubah pencarian atau filter kategori." /></div>
        @endforelse
    </div>
    <div class="mt-5">{{ $products->links() }}</div>
@endsection
