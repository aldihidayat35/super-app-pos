@extends('layouts.metronic.app')
@section('title', 'Cetak Barcode/QR')
@section('page_title', 'Cetak Barcode/QR')
@section('content')
<x-metronic.card title="Pengaturan Cetak">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-5"><label class="form-label">Produk</label><select name="product_id" class="form-select"><option value="">Semua Produk Aktif</option>@foreach(App\Models\Product::query()->orderBy('name')->limit(200)->get() as $option)<option value="{{ $option->id }}" @selected((int) $selectedProductId === $option->id)>{{ $option->sku }} · {{ $option->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Jumlah Label</label><input type="number" min="1" max="100" name="label_count" value="{{ $labelCount }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Ukuran Kertas</label><select name="paper_size" class="form-select"><option value="A4" @selected($paperSize === 'A4')>A4</option><option value="thermal" @selected($paperSize === 'thermal')>Thermal</option></select></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Preview</button></div>
    </form>
</x-metronic.card>
<x-metronic.card title="Preview Label" class="mt-6">
    <div class="row g-4">
        @forelse($products as $product)
            @php($barcode = $product->barcodes->first())
            <div class="col-md-3"><div class="border rounded p-4 text-center"><div class="fw-bold">{{ $product->name }}</div><div class="text-muted small">{{ $product->sku }}</div><div class="my-3 d-flex justify-content-center">@if($barcode?->type === 'qr'){!! (new Milon\Barcode\DNS2D())->getBarcodeHTML($barcode->code, 'QRCODE', 3, 3) !!}@else{!! (new Milon\Barcode\DNS1D())->getBarcodeHTML($barcode?->code ?: $product->sku, 'C128', 1.5, 45) !!}@endif</div><div class="font-monospace">{{ $barcode?->code ?: $product->sku }}</div><div class="text-muted small">{{ $product->baseUnit?->symbol }}</div></div></div>
        @empty
            <x-metronic.empty-state title="Tidak ada produk" description="Pilih produk yang memiliki barcode atau tambahkan barcode di detail produk." />
        @endforelse
    </div>
    <a href="{{ route('admin.products.barcodes.pdf', ['product_id' => $selectedProductId, 'label_count' => $labelCount, 'paper_size' => $paperSize]) }}" class="btn btn-light-primary mt-4">Cetak PDF</a>
</x-metronic.card>
@endsection
