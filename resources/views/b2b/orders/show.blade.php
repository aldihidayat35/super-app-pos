@extends('layouts.metronic.app')

@section('title', $order->number)
@section('page_title', 'Detail Order Langganan')

@section('content')
    <x-metronic.page-title :title="$order->number" description="Detail order pelanggan langganan.">
        <a href="{{ route('langganan.orders.index') }}" class="btn btn-light">Kembali</a>
        @if($order->invoices->isNotEmpty())
            <a href="{{ route('invoices.show', $order->invoices->first()) }}" class="btn btn-light-primary">Invoice</a>
        @endif
        @if($order->shipments->isNotEmpty())
            <a href="{{ route('langganan.shipments.show', $order->shipments->last()) }}" class="btn btn-primary">Tracking Shipment</a>
        @endif
    </x-metronic.page-title>

    {{-- KPI Summary Cards --}}
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <x-metronic.kpi-card
                title="Status Order"
                :value="$order->status->label()"
                :icon="'ki-outline ki-' . ($order->status === \App\Enums\B2bOrderStatus::COMPLETED ? 'check-circle' : ($order->status === \App\Enums\B2bOrderStatus::SHIPPED ? 'truck' : 'box'))"
                :color="$order->status === \App\Enums\B2bOrderStatus::COMPLETED ? 'success' : ($order->status === \App\Enums\B2bOrderStatus::SHIPPED ? 'info' : 'primary')"
                :href="route('langganan.orders.index')"
            />
        </div>
        <div class="col-md-3">
            <x-metronic.kpi-card
                title="Total Order"
                value="{{ App\Support\CurrencyFormatter::rupiah($order->grand_total_amount) }}"
                icon="ki-outline ri-outline ki-cash"
                color="success"
            />
        </div>
        <div class="col-md-3">
            <x-metronic.kpi-card
                title="Tanggal Submit"
                :value="$order->submitted_at?->format('d/m/Y')"
                icon="ki-outline ki-calendar"
                color="warning"
            />
        </div>
        <div class="col-md-3">
            <x-metronic.kpi-card
                title="Pelanggan"
                :value="$order->customer?->business_name ?? '-'"
                icon="ki-outline ki-profile"
                color="primary"
            />
        </div>
    </div>

    <div class="row g-5 mb-5">
        {{-- Left Column: Order Info & Actions --}}
        <div class="col-lg-8">
            <x-metronic.card title="Informasi Order" class="mb-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="text-muted fs-7 mb-1">Nomor Order</div>
                        <div class="fw-bold fs-5">{{ $order->number }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted fs-7 mb-1">Status</div>
                        <x-metronic.status-badge :status="$order->status->value" :label="$order->status->label()" />
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted fs-7 mb-1">Tanggal Submit</div>
                        <div class="fw-semibold">{{ $order->submitted_at?->format('d/m/Y H:i') }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted fs-7 mb-1">Tanggal Pengiriman</div>
                        <div class="fw-semibold">{{ $order->requested_delivery_date?->format('d/m/Y') ?: '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted fs-7 mb-1">Metode Pembayaran</div>
                        <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $order->payment_preference)) }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted fs-7 mb-1">Metode Pengiriman</div>
                        <div class="fw-semibold">{{ ucfirst($order->delivery_method) }}{{ $order->courier_name ? ' · ' . $order->courier_name : '' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted fs-7 mb-1">Sales Person</div>
                        <div class="fw-semibold">{{ $order->sales?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted fs-7 mb-1">Diminta Oleh</div>
                        <div class="fw-semibold">{{ $order->requester?->name ?? '-' }}</div>
                    </div>
                    @if($order->notes)
                    <div class="col-12">
                        <div class="text-muted fs-7 mb-1">Catatan</div>
                        <div class="fw-semibold">{{ $order->notes }}</div>
                    </div>
                    @endif
                </div>
            </x-metronic.card>

            <x-metronic.card title="Item Order" class="mb-4">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>SKU</th>
                                <th>Qty Request</th>
                                <th>Qty Approved</th>
                                <th>Reserved</th>
                                <th>Issued</th>
                                <th>Harga</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $item->product_name_snapshot }}</div>
                                </td>
                                <td><span class="badge bg-light">{{ $item->sku_snapshot }}</span></td>
                                <td>{{ qty($item->quantity) }}</td>
                                <td>{{ $item->approved_quantity ? qty($item->approved_quantity) : '-' }}</td>
                                <td>{{ qty($item->reserved_quantity) }}</td>
                                <td>{{ qty($item->issued_quantity) }}</td>
                                <td>{{ App\Support\CurrencyFormatter::rupiah($item->selected_price) }}</td>
                                <td class="text-end fw-bold">{{ App\Support\CurrencyFormatter::rupiah($item->line_total) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-top">
                                <td colspan="7" class="text-end fw-bold">Subtotal</td>
                                <td class="text-end fw-bold">{{ App\Support\CurrencyFormatter::rupiah($order->subtotal_amount) }}</td>
                            </tr>
                            @if($order->discount_amount > 0)
                            <tr>
                                <td colspan="7" class="text-end text-muted">Diskon</td>
                                <td class="text-end text-muted">- {{ App\Support\CurrencyFormatter::rupiah($order->discount_amount) }}</td>
                            </tr>
                            @endif
                            @if($order->tax_amount > 0)
                            <tr>
                                <td colspan="7" class="text-end text-muted">Pajak</td>
                                <td class="text-end text-muted">{{ App\Support\CurrencyFormatter::rupiah($order->tax_amount) }}</td>
                            </tr>
                            @endif
                            <tr class="border-top">
                                <td colspan="7" class="text-end fw-bold fs-5">Grand Total</td>
                                <td class="text-end fw-bold fs-5 text-primary">{{ App\Support\CurrencyFormatter::rupiah($order->grand_total_amount) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-metronic.card>

            <x-metronic.card title="Timeline Status" class="mb-4">
                <div class="timeline timeline-border-dashed" id="kt_order_timeline">
                    @foreach($order->statusHistories as $index => $history)
                    <div class="timeline-item">
                        <div class="timeline-badge bg-{{ match($order->statusHistories->count() - 1 - $index) {
                            0 => 'success',
                            1 => 'primary',
                            default => 'light'
                        } }}">
                            <i class="fa fa-genderless text-white fs-2"></i>
                        </div>
                        <div class="timeline-content d-flex flex-column flex-md-row justify-content-between">
                            <div class="fw-bold fs-6 text-gray-800 mb-1">{{ $history->to_status }}</div>
                            <div class="text-muted fs-7 mb-1">{{ $history->created_at->format('d/m/Y H:i') }}</div>
                        </div>
                        <div class="timeline-content d-flex flex-column flex-md-row justify-content-between">
                            <div class="text-muted fs-7">{{ $history->note }} · {{ $history->actor?->name ?: 'Sistem' }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </x-metronic.card>

            <x-metronic.card title="Pesan" class="mb-4">
                @forelse($order->messages->whereIn('visibility', ['customer', 'public']) as $message)
                <div class="border-bottom py-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-bold fs-7">{{ $message->user?->name ?: 'Sistem' }}</span>
                        <span class="text-muted fs-7">{{ $message->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="text-gray-700">{{ $message->message }}</div>
                </div>
                @empty
                <x-metronic.empty-state title="Belum ada pesan" description="Pesan dari gudang akan tampil di sini." />
                @endforelse
            </x-metronic.card>
        </div>

        {{-- Right Column: Address & Credit --}}
        <div class="col-lg-4">
            <x-metronic.card title="Alamat Kirim" class="mb-4">
                @if($order->address)
                <div class="text-gray-700">
                    <div class="fw-bold mb-1">{{ $order->address->label }}</div>
                    <div class="mb-1">{{ $order->address->address }}</div>
                    <div class="text-muted fs-7">{{ $order->address->recipient_name }} · {{ $order->address->phone_number }}</div>
                </div>
                @else
                <div class="text-muted">Alamat usaha/utama pelanggan.</div>
                @endif
            </x-metronic.card>

            <x-metronic.card title="Kredit Snapshot" class="mb-4">
                <div class="mb-2">
                    <div class="text-muted fs-7">Limit Kredit</div>
                    <div class="fw-bold fs-5">{{ App\Support\CurrencyFormatter::rupiah($order->credit_limit_snapshot) }}</div>
                </div>
                <div class="mb-2">
                    <div class="text-muted fs-7">Piutang Saat Order</div>
                    <div class="fw-bold fs-5">{{ App\Support\CurrencyFormatter::rupiah($order->receivable_balance_snapshot) }}</div>
                </div>
            </x-metronic.card>

            @if($order->status->canCustomerCancel())
            <x-metronic.card title="Aksi" class="mb-4">
                <form method="POST" action="{{ route('langganan.orders.cancel', $order) }}" class="mb-3">
                    @csrf
                    <input name="reason" class="form-control form-control-sm mb-2" required placeholder="Alasan pembatalan">
                    <button class="btn btn-sm btn-light-danger w-100">Batalkan Order</button>
                </form>
            </x-metronic.card>
            @endif

            @if($order->status === \App\Enums\B2bOrderStatus::SHIPPED)
            <x-metronic.card title="Aksi" class="mb-4">
                <form method="POST" action="{{ route('langganan.orders.receive', $order) }}">
                    @csrf
                    <button class="btn btn-success w-100">Konfirmasi Diterima</button>
                </form>
            </x-metronic.card>
            @endif
        </div>
    </div>
@endsection
