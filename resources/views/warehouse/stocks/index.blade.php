@extends('layouts.metronic.app')

@section('title', 'Saldo Stok - ' . config('app.name'))
@section('page_title', 'Saldo Stok per Lokasi')

@section('toolbar_actions')
    <a href="{{ route('warehouse.stocks.export', request()->query()) }}" class="btn btn-light-primary"><i class="ki-outline ki-file-down"></i> Export CSV</a>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" class="row g-3 mb-5">
            <div class="col-md-3"><select name="product_id" class="form-select form-select-solid"><option value="">Semua produk</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected(($filters['product_id'] ?? '') == $product->id)>{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="work_location_id" class="form-select form-select-solid"><option value="">Semua gudang/cabang</option>@foreach ($workLocations as $location)<option value="{{ $location->id }}" @selected(($filters['work_location_id'] ?? '') == $location->id)>{{ $location->typeLabel() }} — {{ $location->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="warehouse_location_id" class="form-select form-select-solid"><option value="">Semua zona/rak/bin</option>@foreach ($warehouseLocations as $location)<option value="{{ $location->id }}" @selected(($filters['warehouse_location_id'] ?? '') == $location->id)>{{ $location->full_code }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option><option value="critical" @selected(($filters['status'] ?? '') === 'critical')>Kritis</option><option value="empty" @selected(($filters['status'] ?? '') === 'empty')>Kosong</option></select></div>
            <div class="col-md-1"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Lokasi</th><th>On Hand</th><th>Reserved</th><th>Rusak</th><th>Available</th><th>Min/Safety</th><th>Nilai HPP</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                @forelse ($stocks as $stock)
                    <tr>
                        <td><span class="fw-bold">{{ $stock->product?->sku }}</span><div class="text-muted">{{ $stock->product?->name }}</div></td>
                        <td>{{ $stock->workLocation?->name }}<div class="text-muted">{{ $stock->warehouseLocation?->full_code ?: 'Tanpa bin' }}</div></td>
                        <td>{{ $stock->quantity_on_hand }}</td>
                        <td>{{ $stock->quantity_reserved }}</td>
                        <td>{{ $stock->quantity_damaged }}</td>
                        <td class="fw-bold">{{ $stock->available_quantity }}</td>
                        <td>{{ $stock->product?->minimum_stock }} / {{ $stock->product?->safety_stock }}</td>
                        <td>Rp {{ number_format((float) $stock->cost_value, 0, ',', '.') }}</td>
                        <td class="text-end"><a class="btn btn-sm btn-light" href="{{ route('warehouse.stock-card.index', ['product_id' => $stock->product_id, 'work_location_id' => $stock->work_location_id, 'warehouse_location_id' => $stock->warehouse_location_id]) }}">Kartu Stok</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-metronic.empty-state title="Belum ada saldo stok" description="Saldo akan tercipta otomatis saat InventoryService menerima mutasi pertama." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $stocks->links() }}
    </x-metronic.card>
@endsection
