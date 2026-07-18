@extends('layouts.metronic.app')

@section('title', 'Detail Penerimaan - ' . config('app.name'))
@section('page_title', 'Detail Penerimaan Barang')

@section('toolbar_actions')
    <a href="{{ route('warehouse.goods-receipts.print', $receipt) }}" class="btn btn-light-success"><i class="ki-outline ki-printer"></i> Cetak</a>
    @can('update', $receipt)<a href="{{ route('warehouse.goods-receipts.edit', $receipt) }}" class="btn btn-light-primary">Edit Draft</a>@endcan
@endsection

@section('content')
    <div class="row g-5">
        <div class="col-lg-8">
            <x-metronic.card title="{{ $receipt->number }}">
                <div class="row g-4 mb-5">
                    <div class="col-md-4"><div class="text-muted">PO</div><a href="{{ route('purchasing.purchase-orders.show', $receipt->purchaseOrder) }}" class="fw-bold">{{ $receipt->purchaseOrder?->number }}</a></div>
                    <div class="col-md-4"><div class="text-muted">Supplier</div><div class="fw-bold">{{ $receipt->supplier?->name }}</div></div>
                    <div class="col-md-4"><div class="text-muted">Gudang</div><div class="fw-bold">{{ $receipt->warehouse?->name }}</div></div>
                    <div class="col-md-4"><div class="text-muted">Tanggal Datang</div><div class="fw-bold">{{ $receipt->received_at?->format('d/m/Y') }}</div></div>
                    <div class="col-md-4"><div class="text-muted">Surat Jalan</div><div class="fw-bold">{{ $receipt->delivery_note_number ?: '-' }}</div></div>
                    <div class="col-md-4"><div class="text-muted">Status</div><x-metronic.status-badge :status="$receipt->status" /></div>
                    <div class="col-md-4"><div class="text-muted">Penerima</div><div>{{ $receipt->receiver?->name }}</div></div>
                    <div class="col-md-4"><div class="text-muted">Posted</div><div>{{ $receipt->posted_at?->format('d/m/Y H:i') ?: '-' }}</div></div>
                    <div class="col-md-4"><div class="text-muted">Bukti</div><div>{{ $receipt->proof_path ?: '-' }}</div></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>QC</th><th>Lokasi/Batch</th><th>Harga</th><th>HPP</th></tr></thead>
                        <tbody>
                        @foreach($receipt->items as $item)
                            <tr>
                                <td>{{ $item->product_sku_snapshot }}<div class="text-muted">{{ $item->product_name_snapshot }} · {{ $item->unit_name_snapshot }} x {{ qty($item->conversion_factor_snapshot) }}</div></td>
                                <td>
                                    <div>Datang: {{ qty($item->quantity_received) }}</div>
                                    <div class="text-success">Accepted: {{ qty($item->quantity_accepted) }}</div>
                                    <div class="text-danger">Rejected: {{ qty($item->quantity_rejected) }}</div>
                                    <div class="text-warning">Damaged: {{ qty($item->quantity_damaged) }}</div>
                                    <div>Retur Supplier: {{ qty($item->quantity_returned_to_supplier) }}</div>
                                </td>
                                <td>{{ $item->warehouseLocation?->full_code ?: 'Default gudang' }}<div class="text-muted">{{ $item->batch_no ?: '-' }}</div><div>{{ $item->qc_notes ?: '-' }}</div></td>
                                <td>Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}<div class="text-muted">Landed Rp {{ number_format((float) $item->landed_cost_allocated, 0, ',', '.') }}</div></td>
                                <td>{{ $item->hpp_before ?: '-' }} → <span class="fw-bold">{{ $item->hpp_after ?: '-' }}</span><div class="text-muted">Incoming {{ $item->incoming_cost ?: '-' }}</div></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-metronic.card>

            <x-metronic.card title="Mutasi Stok Terbentuk" class="mt-5">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Waktu</th><th>Produk</th><th>Jenis</th><th>Before</th><th>Change</th><th>After</th><th>Actor</th></tr></thead>
                        <tbody>
                        @forelse($receipt->stockMutations as $mutation)
                            <tr><td>{{ $mutation->created_at->format('d/m/Y H:i') }}</td><td>{{ $mutation->product?->name }}</td><td>{{ $mutation->mutation_type }}</td><td>{{ qty($mutation->quantity_before) }}</td><td>{{ qty($mutation->quantity_change) }}</td><td>{{ qty($mutation->quantity_after) }}</td><td>{{ $mutation->actor?->name }}</td></tr>
                        @empty
                            <tr><td colspan="7"><x-metronic.empty-state title="Belum ada mutasi" description="Mutasi muncul setelah receipt di-posting." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-metronic.card>
        </div>

        <div class="col-lg-4">
            <x-metronic.card title="Aksi">
                @can('post', $receipt)
                    <form method="POST" action="{{ route('warehouse.goods-receipts.post', $receipt) }}">@csrf<button class="btn btn-primary w-100" data-confirm="Posting akan mengunci receipt, menambah stok, dan menghitung HPP. Lanjutkan?">Posting Receipt</button></form>
                @else
                    <div class="text-muted">Receipt posted bersifat read-only. Koreksi dilakukan lewat dokumen reversal/correction.</div>
                @endcan
            </x-metronic.card>

            <x-metronic.card title="Histori HPP" class="mt-5">
                @forelse($receipt->costHistories as $history)
                    <div class="mb-4">
                        <div class="fw-bold">{{ $history->product?->name }}</div>
                        <div>{{ $history->hpp_before }} → {{ $history->hpp_after }}</div>
                        <div class="text-muted">Qty {{ qty($history->qty_before) }} + {{ qty($history->qty_incoming) }} = {{ qty($history->qty_after) }}</div>
                    </div>
                @empty
                    <x-metronic.empty-state title="Belum ada HPP" description="Histori HPP dibuat untuk item accepted saat posting." />
                @endforelse
            </x-metronic.card>

            <x-metronic.card title="Status PO Setelah Receipt" class="mt-5">
                <x-metronic.status-badge :status="$receipt->purchaseOrder->status" />
                <div class="separator my-4"></div>
                @foreach($receipt->purchaseOrder->items as $item)
                    <div class="d-flex justify-content-between mb-2"><span>{{ $item->product_sku_snapshot }}</span><span>{{ qty($item->quantity_received) }} / {{ qty($item->quantity_ordered) }}</span></div>
                @endforeach
            </x-metronic.card>
        </div>
    </div>
@endsection
