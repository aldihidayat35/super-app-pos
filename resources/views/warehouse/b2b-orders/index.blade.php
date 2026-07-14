@extends('layouts.metronic.app')

@section('title', 'Antrian Order B2B')
@section('page_title', 'Antrian Order B2B')

@section('content')
    <x-metronic.page-title title="Antrian Order Gudang" description="Validasi order pelanggan, stok, limit, pembayaran, dan prioritas fulfillment." />
    <form method="GET" class="card card-body mb-5">
        <div class="row g-3">
            <div class="col-md-4"><input name="q" value="{{ $filters['q'] }}" class="form-control form-control-solid" placeholder="Cari nomor/customer"></div>
            <div class="col-md-4"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
            <div class="col-md-2"><a href="{{ route('warehouse.reservations.index') }}" class="btn btn-light w-100">Reservations</a></div>
        </div>
    </form>
    <x-metronic.card>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Order</th><th>Pelanggan</th><th>Ring/Limit</th><th>Total</th><th>Payment</th><th>Status</th><th>Umur</th><th></th></tr></thead><tbody>
            @forelse($orders as $order)
                <tr>
                    <td><div class="fw-bold">{{ $order->number }}</div><div class="text-muted">{{ $order->submitted_at?->format('d/m/Y H:i') }}</div></td>
                    <td>{{ $order->customer?->business_name }}<div class="text-muted">{{ $order->requester?->name }}</div></td>
                    <td>{{ $order->customer?->price_category }}<div class="text-muted">{{ App\Support\CurrencyFormatter::rupiah($order->customer?->credit_limit ?? 0) }}</div></td>
                    <td class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($order->grand_total_amount) }}</td>
                    <td>{{ ucfirst($order->payment_preference) }}<div class="text-muted">{{ ucfirst($order->delivery_method) }}</div></td>
                    <td><x-metronic.status-badge :status="$order->status->value" :label="$order->status->label()" /></td>
                    <td>{{ $order->created_at->diffForHumans() }}</td>
                    <td class="text-end"><a href="{{ route('warehouse.b2b-orders.review', $order) }}" class="btn btn-sm btn-light-primary">Review</a></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-metronic.empty-state title="Belum ada order B2B" description="Order dari portal langganan akan tampil di sini." /></td></tr>
            @endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $orders->links() }}</div>
    </x-metronic.card>
@endsection
