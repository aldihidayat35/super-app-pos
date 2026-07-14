@extends('layouts.metronic.app')

@section('title', 'Review Order B2B')
@section('page_title', 'Review Order B2B')

@section('content')
    <x-metronic.page-title :title="$order->number" description="Review, revisi qty, reserve stock, packing, dan shipment order B2B.">
        <a href="{{ route('warehouse.b2b-orders.index') }}" class="btn btn-light">Kembali</a>
        @if($order->invoices->isNotEmpty())
            <a href="{{ route('invoices.show', $order->invoices->first()) }}" class="btn btn-light-primary">Lihat Invoice</a>
        @elseif(in_array($order->status, [App\Enums\B2bOrderStatus::RESERVED, App\Enums\B2bOrderStatus::INVOICE_READY], true))
            <form method="POST" action="{{ route('invoices.issue-b2b', $order) }}" class="d-inline">@csrf<button class="btn btn-light-primary">Terbitkan Invoice</button></form>
        @endif
        @if(in_array($order->status, [App\Enums\B2bOrderStatus::APPROVED_CREDIT, App\Enums\B2bOrderStatus::PACKING], true))
            <a href="{{ route('shipments.create', ['order_id' => $order->id]) }}" class="btn btn-primary">Buat Shipment</a>
        @endif
    </x-metronic.page-title>
    <div class="row g-5 mb-5">
        <div class="col-lg-4"><x-metronic.card title="Pelanggan">
            <div class="fw-bold fs-5">{{ $order->customer?->business_name }}</div>
            <div class="text-muted mb-3">{{ $order->customer?->code }} · {{ $order->customer?->price_category }}</div>
            <div>Limit: {{ App\Support\CurrencyFormatter::rupiah($order->customer?->credit_limit ?? 0) }}</div>
            <div>Piutang: {{ App\Support\CurrencyFormatter::rupiah($order->customer?->receivable_balance ?? 0) }}</div>
            <div>Status: <x-metronic.status-badge :status="$order->status->value" :label="$order->status->label()" /></div>
        </x-metronic.card></div>
        <div class="col-lg-4"><x-metronic.card title="Pengiriman">
            <div>Metode: {{ ucfirst($order->delivery_method) }}</div>
            <div>Kurir: {{ $order->courier_name ?: '-' }}</div>
            <div>Harapan: {{ $order->requested_delivery_date?->format('d/m/Y') ?: '-' }}</div>
            <div>Alamat: {{ $order->address?->address ?: 'Alamat utama/usaha' }}</div>
        </x-metronic.card></div>
        <div class="col-lg-4"><x-metronic.card title="Total">
            <div>Subtotal: {{ App\Support\CurrencyFormatter::rupiah($order->subtotal_amount) }}</div>
            <div>Ongkir: {{ App\Support\CurrencyFormatter::rupiah($order->shipping_cost_amount) }}</div>
            <div class="fs-4 fw-bold">Grand Total: {{ App\Support\CurrencyFormatter::rupiah($order->grand_total_amount) }}</div>
            <div>Payment: {{ ucfirst($order->payment_preference) }}</div>
        </x-metronic.card></div>
    </div>
    <x-metronic.card title="Review dan Reserve">
        <form method="POST" action="{{ route('warehouse.b2b-orders.reserve', $order) }}">
            @csrf
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Produk</th><th>Qty Request</th><th>Qty Approved</th><th>Harga</th><th>Reserved</th><th>Shortage</th></tr></thead><tbody>
                @foreach($order->items as $item)
                    <tr><td><div class="fw-bold">{{ $item->product_name_snapshot }}</div><div class="text-muted">{{ $item->sku_snapshot }}</div></td><td>{{ $item->quantity }} {{ $item->unit_name_snapshot }}<div class="text-muted">Base: {{ $item->base_quantity }}</div></td><td><input type="number" step="0.0001" min="0.0001" name="approved_quantities[{{ $item->id }}]" value="{{ old('approved_quantities.'.$item->id, $item->approved_quantity ?: $item->quantity) }}" class="form-control"></td><td>{{ App\Support\CurrencyFormatter::rupiah($item->selected_price) }}</td><td>{{ $item->reserved_quantity }}</td><td>{{ $item->shortage_quantity }}</td></tr>
                @endforeach
            </tbody></table></div>
            <div class="row g-3 mt-3">
                <div class="col-md-3"><label class="form-label">Expiry Reservation</label><input type="datetime-local" name="reservation_expires_at" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Biaya Kirim</label><input type="number" step="0.01" min="0" name="shipping_cost_amount" value="{{ $order->shipping_cost_amount }}" class="form-control"></div>
                <div class="col-md-3 d-flex align-items-end"><label class="form-check form-check-custom form-check-solid"><input type="hidden" name="allow_partial" value="0"><input class="form-check-input" type="checkbox" name="allow_partial" value="1"><span class="form-check-label">Izinkan partial/backorder</span></label></div>
                <div class="col-md-3"><label class="form-label">Catatan Internal</label><input name="internal_note" class="form-control" value="{{ $order->internal_note }}"></div>
            </div>
            <div class="d-flex flex-wrap gap-3 mt-5">
                @if(in_array($order->status, [App\Enums\B2bOrderStatus::PENDING_CONFIRMATION, App\Enums\B2bOrderStatus::WAREHOUSE_VALIDATION], true))<button class="btn btn-primary">Reserve Stock</button>@endif
                @if($order->status === App\Enums\B2bOrderStatus::RESERVED)<button form="pack-form" class="btn btn-light-primary">Mulai Packing</button>@endif
                @if($order->status === App\Enums\B2bOrderStatus::PACKING)<button form="ship-form" class="btn btn-success">Kirim Order</button>@endif
            </div>
        </form>
        <form id="pack-form" method="POST" action="{{ route('warehouse.b2b-orders.pack', $order) }}">@csrf<input type="hidden" name="internal_note" value="Mulai packing dari halaman review."></form>
        <form id="ship-form" method="POST" action="{{ route('warehouse.b2b-orders.ship', $order) }}">@csrf<input type="hidden" name="internal_note" value="Order dikirim dari halaman review."></form>
    </x-metronic.card>
    <div class="row g-5 mt-1">
        <div class="col-lg-6"><x-metronic.card title="Reject Order">
            <form method="POST" action="{{ route('warehouse.b2b-orders.reject', $order) }}">@csrf<input name="reason" class="form-control mb-3" required placeholder="Alasan reject / minta revisi customer"><button class="btn btn-light-danger">Reject / Release</button></form>
        </x-metronic.card></div>
        <div class="col-lg-6"><x-metronic.card title="Timeline">
            @foreach($order->statusHistories as $history)<div class="border-bottom py-2"><div class="fw-bold">{{ $history->to_status }}</div><div class="text-muted">{{ $history->created_at->format('d/m/Y H:i') }} · {{ $history->actor?->name ?: 'Sistem' }} · {{ $history->note }}</div></div>@endforeach
        </x-metronic.card></div>
    </div>
@endsection
