@extends('layouts.metronic.app')

@section('title', 'Monitor Reserved Stock')
@section('page_title', 'Monitor Reserved Stock')

@section('content')
    <x-metronic.page-title title="Monitor Reserved Stock" description="Pantau stok yang dialokasikan untuk order B2B, expiry, release manual, dan konversi shipment.">
        <form method="POST" action="{{ route('warehouse.reservations.expire') }}">@csrf<button class="btn btn-light-warning">Proses Expired</button></form>
    </x-metronic.page-title>
    <form method="GET" class="card card-body mb-5">
        <div class="row g-3">
            <div class="col-md-5"><input name="q" value="{{ $filters['q'] }}" class="form-control form-control-solid" placeholder="Cari order/produk"></div>
            <div class="col-md-4"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
            <div class="col-md-3"><button class="btn btn-light-primary w-100">Filter</button></div>
        </div>
    </form>
    <x-metronic.card>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Order</th><th>Produk</th><th>Lokasi</th><th>Qty</th><th>Expiry</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse($reservations as $reservation)
                <tr>
                    <td><a href="{{ route('warehouse.b2b-orders.review', $reservation->order) }}" class="fw-bold text-gray-900 text-hover-primary">{{ $reservation->order?->number }}</a><div class="text-muted">{{ $reservation->order?->customer?->business_name }}</div></td>
                    <td>{{ $reservation->product?->name }}<div class="text-muted">{{ $reservation->product?->sku }}</div></td>
                    <td>{{ $reservation->workLocation?->name }}<div class="text-muted">{{ $reservation->warehouseLocation?->full_code ?: 'Tanpa bin' }}</div></td>
                    <td>Reserved {{ $reservation->quantity_reserved }}<div class="text-muted">Released {{ $reservation->quantity_released }} · Issued {{ $reservation->quantity_issued }}</div></td>
                    <td>{{ $reservation->expires_at?->format('d/m/Y H:i') ?: '-' }}</td>
                    <td><x-metronic.status-badge :status="$reservation->status->value" :label="$reservation->status->label()" /></td>
                    <td class="text-end">@if($reservation->status === App\Enums\StockReservationStatus::ACTIVE)<form method="POST" action="{{ route('warehouse.reservations.release', $reservation) }}" class="d-flex gap-2">@csrf<input name="reason" class="form-control form-control-sm" required placeholder="Alasan release"><button class="btn btn-sm btn-light-warning">Release</button></form>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7"><x-metronic.empty-state title="Belum ada reservation" description="Reservation order B2B akan tampil di sini." /></td></tr>
            @endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $reservations->links() }}</div>
    </x-metronic.card>
@endsection
