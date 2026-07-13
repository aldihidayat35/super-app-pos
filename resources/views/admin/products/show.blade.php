@extends('layouts.metronic.app')
@section('title', $product->name)
@section('page_title', 'Detail Produk')
@section('toolbar_actions')
    @can('printBarcode', App\Models\Product::class)<a href="{{ route('admin.products.barcodes.index', ['product_id' => $product->id]) }}" class="btn btn-light">Cetak Barcode</a>@endcan
    @can('update', $product)<a href="{{ route('admin.products.edit', $product) }}" class="btn btn-primary">Edit Produk</a>@endcan
@endsection
@section('content')
<div class="row g-6">
    <div class="col-lg-4">
        <x-metronic.card title="Ringkasan">
            <div class="fw-bold fs-4">{{ $product->name }}</div>
            <div class="text-muted mb-4">{{ $product->sku }}</div>
            <div class="mb-2">Kategori: <span class="fw-semibold">{{ $product->category?->name }}</span></div>
            <div class="mb-2">Subkategori: <span class="fw-semibold">{{ $product->subcategory?->name ?: '-' }}</span></div>
            <div class="mb-2">Merek: <span class="fw-semibold">{{ $product->brand?->name ?: '-' }}</span></div>
            <div class="mb-2">Satuan Dasar: <span class="fw-semibold">{{ $product->baseUnit?->name }}</span></div>
            <div class="mb-2">Lokasi Default: <span class="fw-semibold">{{ $product->defaultWarehouse?->name ?: '-' }}</span></div>
            <x-metronic.status-badge :status="$product->status->value" :label="$product->status->label()" />
        </x-metronic.card>
    </div>
    <div class="col-lg-8">
        <ul class="nav nav-tabs nav-line-tabs mb-5 fs-6">
            @foreach(['info'=>'Informasi','foto'=>'Foto','unit'=>'Satuan/Konversi','barcode'=>'Barcode','stok'=>'Stok per Lokasi','kartu'=>'Kartu Stok','batch'=>'Batch','hpp'=>'HPP/Histori Pembelian','harga'=>'Harga/Ring','supplier'=>'Supplier','penjualan'=>'Penjualan','retur'=>'Retur','audit'=>'Audit'] as $key => $label)
                <li class="nav-item"><a class="nav-link @if($loop->first) active @endif" data-bs-toggle="tab" href="#tab_{{ $key }}">{{ $label }}</a></li>
            @endforeach
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab_info"><x-metronic.card><div class="row"><div class="col-md-6">Model: {{ $product->model ?: '-' }}</div><div class="col-md-6">Ukuran: {{ $product->size ?: '-' }}</div><div class="col-md-6">Warna: {{ $product->color ?: '-' }}</div><div class="col-md-6">Material: {{ $product->material ?: '-' }}</div><div class="col-12 mt-3">Deskripsi: {{ $product->description ?: '-' }}</div></div></x-metronic.card></div>
            <div class="tab-pane fade" id="tab_foto"><x-metronic.card>@forelse($product->images as $image)<div>{{ $image->path }} @if($image->is_primary)<span class="badge badge-light-primary">Utama</span>@endif</div>@empty<x-metronic.empty-state title="Belum ada foto" description="Foto utama dapat diunggah dari form produk." />@endforelse</x-metronic.card></div>
            <div class="tab-pane fade" id="tab_unit"><x-metronic.card><table class="table"><thead><tr><th>Satuan</th><th>Faktor</th><th>Dasar</th><th>Jual</th><th>Status</th></tr></thead><tbody>@foreach($product->units as $unit)<tr><td>{{ $unit->unit?->name }}</td><td>{{ $unit->conversion_factor }}</td><td>{{ $unit->is_base ? 'Ya' : 'Tidak' }}</td><td>{{ $unit->is_sellable ? 'Ya' : 'Tidak' }}</td><td>{{ $unit->is_active ? 'Aktif' : 'Nonaktif' }}</td></tr>@endforeach</tbody></table></x-metronic.card></div>
            <div class="tab-pane fade" id="tab_barcode"><x-metronic.card>@forelse($product->barcodes as $barcode)<div class="mb-2"><span class="fw-bold">{{ $barcode->code }}</span> · {{ strtoupper($barcode->type) }}</div>@empty<x-metronic.empty-state title="Belum ada barcode" description="Barcode dapat ditambahkan dari form produk." />@endforelse</x-metronic.card></div>
            @foreach(['stok'=>'Stok per lokasi akan tersedia setelah modul stok dibuat.','kartu'=>'Kartu stok akan membaca stock_mutations append-only.','batch'=>'Batch/serial akan ditambahkan saat modul inventory batch aktif.','hpp'=>'Histori HPP akan dihitung dari penerimaan/pembelian.','harga'=>'Harga dan price ring akan dibuat pada modul harga.','supplier'=>'Relasi supplier akan dibuat pada modul purchasing.','penjualan'=>'Ringkasan penjualan muncul setelah POS/B2B aktif.','retur'=>'Histori retur muncul setelah modul retur aktif.','audit'=>'Audit log tercatat melalui activity log.'] as $key => $text)
                <div class="tab-pane fade" id="tab_{{ $key }}"><x-metronic.card><x-metronic.empty-state title="{{ $text }}" description="Tab ini disiapkan agar struktur detail produk siap dipakai modul berikutnya." /></x-metronic.card></div>
            @endforeach
        </div>
    </div>
</div>
@endsection
