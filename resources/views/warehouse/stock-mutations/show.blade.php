@extends('layouts.metronic.app')

@section('title', 'Detail Mutasi Stok - ' . config('app.name'))
@section('page_title', 'Detail Mutasi Stok')

@section('content')
    <x-metronic.card title="{{ $mutation->mutation_type->label() }}">
        <div class="row g-5">
            <div class="col-md-4"><div class="text-muted">Waktu</div><div class="fw-bold">{{ $mutation->occurred_at?->format('d/m/Y H:i:s') }}</div></div>
            <div class="col-md-4"><div class="text-muted">Produk</div><div class="fw-bold">{{ $mutation->product?->sku }} — {{ $mutation->product?->name }}</div></div>
            <div class="col-md-4"><div class="text-muted">Satuan Dasar</div><div class="fw-bold">{{ $mutation->product?->baseUnit?->name ?: '-' }}</div></div>
            <div class="col-md-4"><div class="text-muted">Lokasi Kerja</div><div class="fw-bold">{{ $mutation->workLocation?->name }}</div></div>
            <div class="col-md-4"><div class="text-muted">Zona/Rak/Bin</div><div class="fw-bold">{{ $mutation->warehouseLocation?->full_code ?: '-' }}</div></div>
            <div class="col-md-4"><div class="text-muted">Actor</div><div class="fw-bold">{{ $mutation->actor?->name ?: '-' }}</div></div>
            <div class="col-md-4"><div class="text-muted">On Hand Before / Change / After</div><div class="fw-bold">{{ $mutation->quantity_on_hand_before }} / {{ $mutation->quantity_on_hand_change }} / {{ $mutation->quantity_on_hand_after }}</div></div>
            <div class="col-md-4"><div class="text-muted">Reserved Before / Change / After</div><div class="fw-bold">{{ $mutation->quantity_reserved_before }} / {{ $mutation->quantity_reserved_change }} / {{ $mutation->quantity_reserved_after }}</div></div>
            <div class="col-md-4"><div class="text-muted">Damaged Before / Change / After</div><div class="fw-bold">{{ $mutation->quantity_damaged_before }} / {{ $mutation->quantity_damaged_change }} / {{ $mutation->quantity_damaged_after }}</div></div>
            <div class="col-md-4"><div class="text-muted">Referensi</div><div class="fw-bold">{{ $mutation->reference_type ?: '-' }} / {{ $mutation->reference_no ?: '-' }}</div></div>
            <div class="col-md-4"><div class="text-muted">Idempotency Key</div><div class="fw-bold">{{ $mutation->idempotency_key ?: '-' }}</div></div>
            <div class="col-md-4"><div class="text-muted">Catatan</div><div class="fw-bold">{{ $mutation->reason ?: '-' }}</div></div>
        </div>
        <div class="separator my-6"></div>
        <h4>Metadata Audit</h4>
        <pre class="bg-light p-4 rounded">{{ json_encode($mutation->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        <a href="{{ route('warehouse.stock-card.index', ['product_id' => $mutation->product_id]) }}" class="btn btn-light">Kembali ke Kartu Stok</a>
    </x-metronic.card>
@endsection
