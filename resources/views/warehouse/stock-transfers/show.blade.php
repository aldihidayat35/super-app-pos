@extends('layouts.metronic.app')

@section('title', 'Detail Transfer - ' . config('app.name'))
@section('page_title', 'Detail Transfer dan Timeline')

@section('toolbar_actions')
    <a href="{{ route('warehouse.stock-transfers.print', $transfer) }}" class="btn btn-light-success"><i class="ki-outline ki-printer"></i> Surat Jalan</a>
    @can('pack', $transfer)<a href="{{ route('warehouse.stock-transfers.packing', $transfer) }}" class="btn btn-light-primary">Packing</a>@endcan
    @can('ship', $transfer)<a href="{{ route('warehouse.stock-transfers.ship-form', $transfer) }}" class="btn btn-light-info">Kirim</a>@endcan
    @can('receive', $transfer)<a href="{{ route('retail.stock-transfers.receive-form', $transfer) }}" class="btn btn-primary">Terima di Cabang</a>@endcan
@endsection

@section('content')
    <div class="row g-5">
        <div class="col-lg-8">
            <x-metronic.card title="{{ $transfer->number }}">
                <div class="row g-4 mb-5">
                    <div class="col-md-3"><div class="text-muted">Sumber</div><div class="fw-bold">{{ $transfer->sourceWorkLocation?->name }}</div></div>
                    <div class="col-md-3"><div class="text-muted">Tujuan</div><div class="fw-bold">{{ $transfer->destinationWorkLocation?->name }}</div></div>
                    <div class="col-md-3"><div class="text-muted">Tanggal</div><div>{{ $transfer->transfer_date?->format('d/m/Y') }}</div></div>
                    <div class="col-md-3"><div class="text-muted">Status</div><x-metronic.status-badge :status="$transfer->status" /></div>
                    <div class="col-md-3"><div class="text-muted">Request Asal</div><div>{{ $transfer->restockRequest?->number ?: '-' }}</div></div>
                    <div class="col-md-3"><div class="text-muted">Pengirim</div><div>{{ $transfer->shipper?->name ?: '-' }}</div></div>
                    <div class="col-md-3"><div class="text-muted">Penerima</div><div>{{ $transfer->receiver?->name ?: '-' }}</div></div>
                    <div class="col-md-3"><div class="text-muted">Resi/Kendaraan</div><div>{{ $transfer->tracking_number ?: $transfer->vehicle_number ?: '-' }}</div></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Request</th><th>Approved</th><th>Picked/Short</th><th>Shipped</th><th>Received</th><th>Rusak</th><th>Discrepancy</th><th>In Transit</th></tr></thead>
                        <tbody>@foreach($transfer->items as $item)<tr><td>{{ $item->product_sku_snapshot }}<div class="text-muted">{{ $item->product_name_snapshot }}</div></td><td>{{ qty($item->quantity_requested) }}</td><td>{{ qty($item->quantity_approved) }}</td><td>{{ qty($item->quantity_picked) }} / {{ qty($item->quantity_short) }}</td><td>{{ qty($item->quantity_shipped) }}</td><td>{{ qty($item->quantity_received) }}</td><td>{{ qty($item->quantity_damaged) }}</td><td>{{ qty($item->quantity_discrepancy) }}</td><td class="fw-bold">{{ qty($item->inTransitQuantity()) }}</td></tr>@endforeach</tbody>
                    </table>
                </div>
            </x-metronic.card>

            <x-metronic.card title="Mutasi Stok" class="mt-5">
                <div class="table-responsive"><table class="table align-middle"><thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Waktu</th><th>Produk</th><th>Jenis</th><th>On Hand</th><th>Reserved</th><th>Lokasi</th></tr></thead><tbody>@forelse($transfer->stockMutations as $mutation)<tr><td>{{ $mutation->occurred_at?->format('d/m/Y H:i') }}</td><td>{{ $mutation->product?->sku }}</td><td>{{ $mutation->mutation_type->label() }}</td><td>{{ qty($mutation->quantity_on_hand_change) }}</td><td>{{ qty($mutation->quantity_reserved_change) }}</td><td>{{ $mutation->workLocation?->name }}</td></tr>@empty<tr><td colspan="6"><x-metronic.empty-state title="Belum ada mutasi" description="Reserve, ship, dan receive akan membuat mutasi stok." /></td></tr>@endforelse</tbody></table></div>
            </x-metronic.card>
        </div>
        <div class="col-lg-4">
            <x-metronic.card title="Aksi Dokumen">
                <div class="d-grid gap-3">
                    @can('approve', $transfer)
                        <form method="POST" action="{{ route('warehouse.stock-transfers.approve', $transfer) }}">@csrf @foreach($transfer->items as $item)<input type="hidden" name="items[{{ $item->id }}][quantity_approved]" value="{{ qty_input($item->quantity_approved) }}">@endforeach<button class="btn btn-success">Approve & Reserve</button></form>
                    @endcan
                    @can('complete', $transfer)<form method="POST" action="{{ route('warehouse.stock-transfers.complete', $transfer) }}">@csrf<button class="btn btn-primary">Selesaikan Transfer</button></form>@endcan
                    @can('cancel', $transfer)<form method="POST" action="{{ route('warehouse.stock-transfers.cancel', $transfer) }}">@csrf<input name="reason" class="form-control mb-2" placeholder="Alasan batal" required><button class="btn btn-light-danger">Cancel Transfer</button></form>@endcan
                </div>
            </x-metronic.card>
            <x-metronic.card title="Timeline" class="mt-5">
                @forelse($timeline as $history)<div class="border-start border-3 ps-4 mb-4"><div class="fw-bold">{{ ucfirst(str_replace('_', ' ', $history->to_status)) }}</div><div class="text-muted">{{ $history->created_at->format('d/m/Y H:i') }} oleh {{ $history->actor?->name ?: '-' }}</div><div>{{ $history->notes ?: '-' }}</div></div>@empty<x-metronic.empty-state title="Belum ada timeline" description="Status transfer akan tercatat di sini." />@endforelse
            </x-metronic.card>
            <x-metronic.card title="Paket dan Bukti" class="mt-5">
                @forelse($transfer->packages as $package)<div class="mb-3"><div class="fw-bold">{{ $package->package_no }}</div><div class="text-muted">Checker {{ $package->checker?->name ?: '-' }}</div><div>{{ $package->notes ?: '-' }}</div></div>@empty<div class="text-muted">Belum ada paket.</div>@endforelse
            </x-metronic.card>
        </div>
    </div>
@endsection
