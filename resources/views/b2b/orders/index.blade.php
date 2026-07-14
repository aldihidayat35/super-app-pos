@extends('layouts.metronic.app')

@section('title', 'Riwayat Order')
@section('page_title', 'Riwayat Order')

@section('content')
    <x-metronic.page-title title="Riwayat Order Langganan" description="Hanya order milik {{ $customer->business_name }} yang ditampilkan.">
        <a href="{{ route('langganan.katalog.index') }}" class="btn btn-primary">Buat Order</a>
    </x-metronic.page-title>
    <form method="GET" class="card card-body mb-5">
        <div class="row g-3">
            <div class="col-md-4"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-3"><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-solid"></div>
            <div class="col-md-3"><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-solid"></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </div>
    </form>
    <x-metronic.card>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nomor</th><th>Tanggal</th><th>Status</th><th>Pembayaran</th><th>Pengiriman</th><th>Item</th><th>Total</th><th></th></tr></thead><tbody>
            @forelse($orders as $order)
                <tr><td class="fw-bold">{{ $order->number }}</td><td>{{ $order->submitted_at?->format('d/m/Y H:i') ?: '-' }}</td><td><x-metronic.status-badge :status="$order->status->value" :label="$order->status->label()" /></td><td>{{ ucfirst(str_replace('_', ' ', $order->payment_preference)) }}</td><td>{{ ucfirst($order->delivery_method) }}</td><td>{{ $order->items_count }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($order->grand_total_amount) }}</td><td class="text-end"><a href="{{ route('langganan.orders.show', $order) }}" class="btn btn-sm btn-light">Detail</a><button class="btn btn-sm btn-light" disabled>Invoice</button></td></tr>
            @empty
                <tr><td colspan="8"><x-metronic.empty-state title="Belum ada order" description="Order yang diajukan dari keranjang akan muncul di sini." /></td></tr>
            @endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $orders->links() }}</div>
    </x-metronic.card>
@endsection
