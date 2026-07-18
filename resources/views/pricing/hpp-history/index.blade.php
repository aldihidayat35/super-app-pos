@extends('layouts.metronic.app')

@section('title', 'Histori HPP - ' . config('app.name'))
@section('page_title', 'Histori HPP dan Harga Supplier')

@section('toolbar_actions')
    <a href="{{ route('pricing.hpp-history.export', request()->query()) }}" class="btn btn-light-success"><i class="ki-outline ki-file-down"></i> Export</a>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" class="row g-3 mb-6">
            <div class="col-md-3"><select name="product_id" class="form-select form-select-solid"><option value="">Semua produk</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(($filters['product_id'] ?? '') == $product->id)>{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="supplier_id" class="form-select form-select-solid"><option value="">Semua supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(($filters['supplier_id'] ?? '') == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-solid"></div>
            <div class="col-md-2"><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-solid"></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>

        @php
            $chartRows = $histories->getCollection()->take(8);
            $maxHpp = max(1, $chartRows->max(fn ($history) => (float) $history->hpp_after) ?: 1);
        @endphp
        <div class="alert alert-info d-flex align-items-center gap-3">
            <i class="ki-outline ki-chart-line-up fs-2"></i>
            <div>Metode HPP aktif: <strong>moving weighted average</strong>. Grafik bar di bawah memakai data pada halaman aktif.</div>
        </div>
        <div class="border rounded p-4 mb-6">
            <div class="fw-bold mb-3">Grafik Tren HPP</div>
            @forelse($chartRows as $history)
                @php($width = max(4, ((float) $history->hpp_after / $maxHpp) * 100))
                <div class="mb-3">
                    <div class="d-flex justify-content-between small"><span>{{ $history->product?->sku }} · {{ $history->effective_at?->format('d/m') }}</span><span>{{ $history->hpp_after }}</span></div>
                    <div class="bg-light rounded h-8px"><div class="bg-primary rounded h-8px" style="width: {{ $width }}%"></div></div>
                </div>
            @empty
                <div class="text-muted">Belum ada data tren.</div>
            @endforelse
        </div>

        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Supplier</th><th>Receipt</th><th>Metode</th><th>Qty</th><th>Komponen Biaya</th><th>HPP</th><th>Tanggal</th></tr></thead>
                <tbody>
                @forelse($histories as $history)
                    <tr>
                        <td>{{ $history->product?->sku }}<div class="text-muted">{{ $history->product?->name }}</div></td>
                        <td>{{ $history->supplier?->name }}</td>
                        <td><a href="{{ route('warehouse.goods-receipts.show', $history->goodsReceipt) }}">{{ $history->goodsReceipt?->number }}</a></td>
                        <td>{{ str_replace('_', ' ', $history->method) }}</td>
                        <td>{{ qty($history->qty_before) }} + {{ qty($history->qty_incoming) }} = <span class="fw-bold">{{ qty($history->qty_after) }}</span></td>
                        <td>Incoming Rp {{ number_format((float) $history->incoming_cost, 0, ',', '.') }}<div class="text-muted">Landed Rp {{ number_format((float) $history->landed_cost_allocated, 0, ',', '.') }}</div></td>
                        <td>{{ $history->hpp_before }} → <span class="fw-bold">{{ $history->hpp_after }}</span></td>
                        <td>{{ $history->effective_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-metronic.empty-state title="Belum ada histori HPP" description="Histori akan terbentuk saat receipt accepted di-posting." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $histories->links() }}
    </x-metronic.card>
@endsection
