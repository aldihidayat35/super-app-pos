@extends('layouts.metronic.app')

@section('title', 'Order Saya')
@section('page_title', 'Order Saya')
@section('content')
    <x-metronic.page-title title="Order Saya" description="Order B2B yang tercatat atas tanggung jawab Anda."><x-slot:actions><a href="{{ route('sales.orders.create') }}" class="btn btn-primary">Buat Order</a></x-slot:actions></x-metronic.page-title>
    <form method="GET" class="card card-body mb-5"><div class="row g-3">
        <div class="col-md-4"><input name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-solid" placeholder="Nomor order atau customer"></div>
        <div class="col-md-3"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-2"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-solid"></div>
        <div class="col-md-2"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-solid"></div>
        <div class="col-md-1"><button class="btn btn-light-primary w-100">Filter</button></div>
    </div></form>
    <x-metronic.card><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nomor Order</th><th>Customer</th><th>Tanggal</th><th>Total</th><th>Status</th><th>Status Pengiriman</th></tr></thead><tbody>
        @forelse($orders as $order)<tr><td><a href="{{ route('sales.orders.show', $order) }}" class="fw-bold">{{ $order->number }}</a></td><td>{{ $order->customer?->business_name }}</td><td>{{ $order->submitted_at?->format('d/m/Y H:i') }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($order->grand_total_amount) }}</td><td><x-metronic.status-badge :status="$order->status->value" :label="$order->status->label()" /></td><td>{{ $order->latestShipment?->status?->label() ?? '-' }}</td></tr>
        @empty<tr><td colspan="6"><x-metronic.empty-state title="Belum ada order" description="Order yang Anda buat akan tampil di sini." /></td></tr>@endforelse
    </tbody></table></div><div class="mt-4">{{ $orders->links() }}</div></x-metronic.card>
@endsection
