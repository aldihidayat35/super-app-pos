@extends('layouts.metronic.app')

@section('title', 'Batch/Lot Stok - ' . config('app.name'))
@section('page_title', 'Batch/Lot Stok')

@section('content')
    <x-metronic.card>
        <form method="GET" class="row g-3 mb-5">
            <div class="col-md-4"><input name="q" value="{{ $search }}" class="form-control form-control-solid" placeholder="Cari batch/lot"></div>
            <div class="col-md-3"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option><option value="active" @selected($status === 'active')>Aktif</option><option value="expired" @selected($status === 'expired')>Expired</option><option value="closed" @selected($status === 'closed')>Ditutup</option></select></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Batch</th><th>Produk</th><th>Supplier</th><th>Tanggal Masuk</th><th>Expired</th><th>HPP Batch</th><th>Qty</th><th>Lokasi</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($batches as $batch)
                    <tr>
                        <td class="fw-bold">{{ $batch->batch_no }}</td>
                        <td>{{ $batch->product?->sku }}<div class="text-muted">{{ $batch->product?->name }}</div></td>
                        <td>{{ $batch->supplier?->name ?: '-' }}</td>
                        <td>{{ $batch->received_at?->format('d/m/Y') ?: '-' }}</td>
                        <td>{{ $batch->expires_at?->format('d/m/Y') ?: '-' }}</td>
                        <td>Rp {{ number_format((float) $batch->cost_price, 0, ',', '.') }}</td>
                        <td>{{ $batch->quantity_on_hand }}<div class="text-muted">Reserved: {{ $batch->quantity_reserved }}</div></td>
                        <td>{{ $batch->stock?->warehouseLocation?->full_code ?: $batch->stock?->workLocation?->name ?: '-' }}</td>
                        <td><x-metronic.status-badge :status="$batch->status" :label="ucfirst($batch->status)" /></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-metronic.empty-state title="Belum ada batch/lot" description="Batch akan diisi oleh penerimaan barang pada fase berikutnya." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $batches->links() }}
    </x-metronic.card>
@endsection
