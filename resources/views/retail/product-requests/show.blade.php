@extends('layouts.metronic.app')

@section('title', $proposal->number)

@section('content')
    <x-metronic.page-title :title="$proposal->number" :description="$proposal->name.' · '.$proposal->branch?->name" />
    <div class="row g-5">
        <div class="col-lg-8">
            <x-metronic.card title="Data yang Diajukan">
                <div class="row g-4">
                    @if($proposal->photo_path)<div class="col-12"><img src="{{ Storage::disk('public')->url($proposal->photo_path) }}" alt="Foto {{ $proposal->name }}" class="rounded border" style="max-width:240px;max-height:240px;object-fit:contain"></div>@endif
                    <div class="col-md-6"><div class="text-muted">Nama</div><div class="fw-bold">{{ $proposal->name }}</div></div>
                    <div class="col-md-6"><div class="text-muted">SKU / Barcode</div><div>{{ $proposal->proposed_sku ?: 'SKU otomatis' }} / {{ $proposal->barcode ?: '-' }}</div></div>
                    <div class="col-md-6"><div class="text-muted">Kategori / Merek</div><div>{{ $proposal->category?->name }} / {{ $proposal->brand?->name ?: '-' }}</div></div>
                    <div class="col-md-6"><div class="text-muted">Satuan</div><div>{{ $proposal->baseUnit?->name }}</div></div>
                    <div class="col-md-6"><div class="text-muted">Harga beli</div><div>{{ App\Support\CurrencyFormatter::rupiah($proposal->purchase_cost) }}</div></div>
                    <div class="col-md-6"><div class="text-muted">Usulan harga jual toko</div><div>{{ App\Support\CurrencyFormatter::rupiah($proposal->proposed_selling_price) }}</div></div>
                    <div class="col-12"><div class="text-muted">Keterangan</div><div>{{ $proposal->description ?: '-' }}</div></div>
                </div>
            </x-metronic.card>
        </div>
        <div class="col-lg-4">
            <x-metronic.card title="Status">
                <div class="fs-5 fw-bold mb-3">{{ ['pending_approval' => 'Menunggu pemeriksaan master data', 'approved' => 'Disetujui dan aktif', 'rejected' => 'Ditolak'][$proposal->status] ?? $proposal->status }}</div>
                @if($proposal->createdProduct)<a href="{{ route('admin.products.show', $proposal->createdProduct) }}" class="btn btn-light-primary w-100 mb-3">Buka Produk {{ $proposal->createdProduct->sku }}</a>@endif
                @if($proposal->review_notes)<div class="alert alert-light">{{ $proposal->review_notes }}</div>@endif
                @can('approve', $proposal)
                    <form method="POST" action="{{ route('retail.product-requests.approve', $proposal) }}" class="mb-4">@csrf<textarea name="notes" class="form-control mb-2" placeholder="Catatan persetujuan"></textarea><button class="btn btn-success w-100">Setujui dan Buat Produk</button></form>
                    <form method="POST" action="{{ route('retail.product-requests.reject', $proposal) }}">@csrf<textarea name="notes" required class="form-control mb-2" placeholder="Alasan penolakan"></textarea><button class="btn btn-light-danger w-100">Tolak Pengajuan</button></form>
                @endcan
            </x-metronic.card>
        </div>
    </div>
@endsection
