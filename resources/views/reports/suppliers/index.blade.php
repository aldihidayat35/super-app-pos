@extends('layouts.metronic.app')

@section('title', 'Performa Supplier - ' . config('app.name'))
@section('page_title', 'Performa Supplier')

@section('content')
    <x-metronic.card>
        <form method="GET" class="row g-3 mb-6">
            <div class="col-md-3"><select name="supplier_id" class="form-select form-select-solid"><option value="">Semua supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(($filters['supplier_id'] ?? '') == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="product_id" class="form-select form-select-solid"><option value="">Semua produk</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(($filters['product_id'] ?? '') == $product->id)>{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-solid"></div>
            <div class="col-md-2"><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-solid"></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>

        <div class="row g-5 mb-5">
            <div class="col-md-4"><div class="border rounded p-4"><div class="text-muted">Receipt Dinilai</div><div class="fs-2 fw-bold">{{ $scores->total() }}</div></div></div>
            <div class="col-md-4"><div class="border rounded p-4"><div class="text-muted">Fokus Evaluasi</div><div class="fw-bold">Qty, kualitas, retur, harga</div></div></div>
            <div class="col-md-4"><div class="border rounded p-4"><div class="text-muted">Rekomendasi</div><div class="fw-bold">Review supplier dengan skor kualitas rendah</div></div></div>
        </div>

        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Supplier</th><th>Receipt</th><th>Qty</th><th>Kualitas</th><th>Retur/Rusak</th><th>Harga Terakhir</th><th>Total</th><th>Rekomendasi</th><th>Tanggal</th></tr></thead>
                <tbody>
                @forelse($scores as $score)
                    @php
                        $line = $score->goodsReceipt?->items->firstWhere('product_id', (int) ($filters['product_id'] ?? 0)) ?? $score->goodsReceipt?->items->first();
                        $recommendation = (float) $score->quality_score < 80 ? 'Evaluasi kualitas/retur' : 'Pertahankan, pantau tren harga';
                    @endphp
                    <tr>
                        <td class="fw-bold">{{ $score->supplier?->name }}</td>
                        <td><a href="{{ route('warehouse.goods-receipts.show', $score->goodsReceipt) }}">{{ $score->goodsReceipt?->number }}</a></td>
                        <td>{{ qty($score->quantity_accepted) }} / {{ qty($score->quantity_received) }}</td>
                        <td><span class="badge badge-light-success">{{ $score->quality_score }}%</span></td>
                        <td>{{ qty($score->quantity_rejected) }} rejected · {{ qty($score->quantity_damaged) }} rusak</td>
                        <td>Rp {{ number_format((float) ($line?->unit_price ?? 0), 0, ',', '.') }}<div class="text-muted">Skor harga {{ $score->price_score }}%</div></td>
                        <td><span class="badge badge-light-primary">{{ $score->total_score }}%</span></td>
                        <td>{{ $recommendation }}</td>
                        <td>{{ $score->received_at?->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-metronic.empty-state title="Belum ada skor supplier" description="Skor dibuat otomatis saat goods receipt di-posting." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $scores->links() }}
    </x-metronic.card>
@endsection
