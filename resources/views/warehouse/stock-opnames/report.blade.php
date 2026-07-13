@section('title', 'Laporan Stok Opname - ' . config('app.name'))
@extends('layouts.metronic.app')

@section('content')
    <style>@media print {.aside,.header,.toolbar,.btn{display:none!important}.card{border:0!important;box-shadow:none!important}}</style>
    <x-metronic.page-title :title="'Laporan ' . $opname->number" description="Berita acara stok opname dan koreksi stok.">
        <x-slot:actions><button onclick="window.print()" class="btn btn-primary">Print</button></x-slot:actions>
    </x-metronic.page-title>

    <x-metronic.card>
        <div class="d-flex justify-content-between mb-8">
            <div><h2 class="mb-1">Berita Acara Stok Opname</h2><div class="text-muted">{{ $opname->number }}</div></div>
            <x-metronic.status-badge :status="$opname->status" />
        </div>
        <div class="row mb-8">
            <div class="col-md-4"><div class="text-muted">Gudang/Cabang</div><div class="fw-bold">{{ $opname->workLocation?->name }}</div></div>
            <div class="col-md-4"><div class="text-muted">Scope</div><div>{{ $opname->warehouseLocation?->full_code ?: 'Semua bin' }} / {{ $opname->category?->name ?: 'Semua kategori' }}</div></div>
            <div class="col-md-4"><div class="text-muted">Periode</div><div>{{ $opname->scheduled_at?->format('d/m/Y') }} · Snapshot {{ $opname->started_at?->format('d/m/Y H:i') ?: '-' }}</div></div>
        </div>
        <div class="row mb-8">
            <div class="col-md-3"><div class="border rounded p-4"><div class="text-muted">Item</div><div class="fs-2 fw-bold">{{ $opname->items->count() }}</div></div></div>
            <div class="col-md-3"><div class="border rounded p-4"><div class="text-muted">Progress</div><div class="fs-2 fw-bold">{{ $opname->countedProgress() }}</div></div></div>
            <div class="col-md-3"><div class="border rounded p-4"><div class="text-muted">Selisih Qty</div><div class="fs-2 fw-bold">{{ number_format((float) $opname->total_difference_qty, 4, ',', '.') }}</div></div></div>
            <div class="col-md-3"><div class="border rounded p-4"><div class="text-muted">Nilai Selisih</div><div class="fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($opname->total_difference_value) }}</div></div></div>
        </div>
        <table class="table table-row-dashed">
            <thead><tr class="fw-bold text-muted"><th>Produk</th><th>Lokasi</th><th class="text-end">Sistem</th><th class="text-end">Fisik</th><th class="text-end">Selisih</th><th>Alasan</th></tr></thead>
            <tbody>
                @foreach($opname->items->sortByDesc(fn($item) => abs((float) $item->difference_qty))->take(50) as $item)
                    <tr><td>{{ $item->product_sku_snapshot }} — {{ $item->product_name_snapshot }}</td><td>{{ $item->warehouseLocation?->full_code ?: '-' }}</td><td class="text-end">{{ number_format((float) $item->system_qty_snapshot, 4, ',', '.') }}</td><td class="text-end">{{ number_format((float) $item->counted_qty, 4, ',', '.') }}</td><td class="text-end">{{ number_format((float) $item->difference_qty, 4, ',', '.') }}</td><td>{{ $item->reason?->label() ?: '-' }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <div class="row mt-10">
            <div class="col-md-4 text-center"><div style="height:80px"></div><div class="border-top pt-2">PIC: {{ $opname->pic?->name ?: '-' }}</div></div>
            <div class="col-md-4 text-center"><div style="height:80px"></div><div class="border-top pt-2">Approver: {{ $opname->approver?->name ?: '-' }}</div></div>
            <div class="col-md-4 text-center"><div style="height:80px"></div><div class="border-top pt-2">Owner/Audit</div></div>
        </div>
    </x-metronic.card>
@endsection

