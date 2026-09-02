@extends('layouts.metronic.app')

@section('title', $invoice->number)
@section('page_title', 'Detail Invoice')

@section('content')
    <x-metronic.page-title :title="$invoice->number" description="Detail tagihan, pembayaran, dan PDF.">
        <a href="{{ route('invoices.index') }}" class="btn btn-light">Kembali</a>
        <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-light-primary">
            <i class="ki-outline ki-file-pdf fs-5 me-1"></i>PDF
        </a>
        @if($invoice->outstanding_amount > 0)
        <a href="{{ route('payments.create', ['invoice_id' => $invoice->id]) }}" class="btn btn-primary">
            <i class="ki-outline ki-cash fs-5 me-1"></i>Bayar / Input Pembayaran
        </a>
        @endif
    </x-metronic.page-title>

    {{-- KPI Summary Cards --}}
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <x-metronic.kpi-card
                title="Status Invoice"
                :value="$invoice->status->label()"
                icon="ki-outline ki-circle-mark"
                :color="$invoice->status === \App\Enums\InvoiceStatus::PAID ? 'success' : ($invoice->status === \App\Enums\InvoiceStatus::OVERDUE ? 'danger' : 'primary')"
            />
        </div>
        <div class="col-md-3">
            <x-metronic.kpi-card
                title="Total Invoice"
                value="{{ App\Support\CurrencyFormatter::rupiah($invoice->total_amount) }}"
                icon="ki-outline ri-outline ki-cash"
                color="primary"
            />
        </div>
        <div class="col-md-3">
            <x-metronic.kpi-card
                title="Sudah Dibayar"
                value="{{ App\Support\CurrencyFormatter::rupiah($invoice->paid_amount) }}"
                icon="ki-outline ki-check-circle"
                color="success"
            />
        </div>
        <div class="col-md-3">
            <x-metronic.kpi-card
                title="Sisa Tagihan"
                value="{{ App\Support\CurrencyFormatter::rupiah($invoice->outstanding_amount) }}"
                icon="ki-outline ki-warning"
                :color="$invoice->outstanding_amount > 0 ? 'danger' : 'success'"
            />
        </div>
    </div>

    <div class="row g-5 mb-5">
        {{-- Left Column --}}
        <div class="col-lg-8">
            <x-metronic.card title="Informasi Invoice" class="mb-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="text-muted fs-7 mb-1">Nomor Invoice</div>
                        <div class="fw-bold fs-5">{{ $invoice->number }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted fs-7 mb-1">Status</div>
                        <x-metronic.status-badge :status="$invoice->status->value" :label="$invoice->status->label()" />
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted fs-7 mb-1">Tanggal Terbit</div>
                        <div class="fw-semibold">{{ $invoice->issue_date?->format('d/m/Y') ?: '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted fs-7 mb-1">Jatuh Tempo</div>
                        <div class="fw-semibold">{{ $invoice->due_date?->format('d/m/Y') ?: '-' }}</div>
                    </div>
                    @if($invoice->order)
                    <div class="col-md-6">
                        <div class="text-muted fs-7 mb-1">Order Terkait</div>
                        <a href="{{ route('langganan.orders.show', $invoice->order) }}" class="fw-semibold text-primary text-hover-none">
                            {{ $invoice->order->number }}
                        </a>
                    </div>
                    @endif
                </div>
            </x-metronic.card>

            <x-metronic.card title="Item Invoice" class="mb-4">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Deskripsi</th>
                                <th>Qty</th>
                                <th>Harga</th>
                                <th>Diskon</th>
                                <th>Pajak</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->items as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td>{{ qty($item->quantity) }} {{ $item->unit_name_snapshot }}</td>
                                <td>{{ App\Support\CurrencyFormatter::rupiah($item->unit_price) }}</td>
                                <td>{{ App\Support\CurrencyFormatter::rupiah($item->discount_amount) }}</td>
                                <td>{{ App\Support\CurrencyFormatter::rupiah($item->tax_amount) }}</td>
                                <td class="text-end fw-bold">{{ App\Support\CurrencyFormatter::rupiah($item->line_total) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-top">
                                <td colspan="5" class="text-end fw-bold">Subtotal</td>
                                <td class="text-end fw-bold">{{ App\Support\CurrencyFormatter::rupiah($invoice->subtotal_amount) }}</td>
                            </tr>
                            @if($invoice->discount_amount > 0)
                            <tr>
                                <td colspan="5" class="text-end text-muted">Diskon</td>
                                <td class="text-end text-muted">- {{ App\Support\CurrencyFormatter::rupiah($invoice->discount_amount) }}</td>
                            </tr>
                            @endif
                            @if($invoice->shipping_amount > 0)
                            <tr>
                                <td colspan="5" class="text-end text-muted">Ongkos Kirim</td>
                                <td class="text-end text-muted">{{ App\Support\CurrencyFormatter::rupiah($invoice->shipping_amount) }}</td>
                            </tr>
                            @endif
                            @if($invoice->tax_amount > 0)
                            <tr>
                                <td colspan="5" class="text-end text-muted">Pajak</td>
                                <td class="text-end text-muted">{{ App\Support\CurrencyFormatter::rupiah($invoice->tax_amount) }}</td>
                            </tr>
                            @endif
                            <tr class="border-top">
                                <td colspan="5" class="text-end fw-bold fs-5">Total</td>
                                <td class="text-end fw-bold fs-5 text-primary">{{ App\Support\CurrencyFormatter::rupiah($invoice->total_amount) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-metronic.card>

            <x-metronic.card title="Riwayat Pembayaran">
                @forelse($invoice->allocations as $allocation)
                <div class="border-bottom py-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-bold fs-7">{{ $allocation->payment?->number ?? '-' }}</span>
                        <span class="fw-bold text-success">{{ App\Support\CurrencyFormatter::rupiah($allocation->amount) }}</span>
                    </div>
                    <div class="text-muted fs-7">
                        {{ $allocation->payment?->payment_date?->format('d/m/Y') }} ·
                        {{ $allocation->payment?->method?->label() }} ·
                        <x-metronic.status-badge :status="$allocation->payment?->status" :label="$allocation->payment?->status?->label()" />
                    </div>
                </div>
                @empty
                <x-metronic.empty-state title="Belum ada pembayaran" description="Pembayaran yang dialokasikan ke invoice ini akan tampil di sini." />
                @endforelse
            </x-metronic.card>
        </div>

        {{-- Right Column --}}
        <div class="col-lg-4">
            <x-metronic.card title="Pelanggan" class="mb-4">
                @if($invoice->customer)
                <div class="text-gray-700">
                    <div class="fw-bold fs-5 mb-1">{{ $invoice->customer->business_name }}</div>
                    <div class="text-muted fs-7 mb-2">{{ $invoice->customer->code }}</div>
                    <a href="{{ route('admin.customers.show', $invoice->customer) }}" class="btn btn-sm btn-light w-100">Lihat Detail</a>
                </div>
                @else
                <div class="text-muted">-</div>
                @endif
            </x-metronic.card>

            <x-metronic.card title="Ringkasan Pembayaran" class="mb-4">
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Total Invoice</span>
                        <span class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($invoice->total_amount) }}</span>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Sudah Dibayar</span>
                        <span class="fw-bold text-success">{{ App\Support\CurrencyFormatter::rupiah($invoice->paid_amount) }}</span>
                    </div>
                </div>
                <div class="border-top pt-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Sisa Tagihan</span>
                        <span class="fw-bold fs-5 {{ $invoice->outstanding_amount > 0 ? 'text-danger' : 'text-success' }}">
                            {{ App\Support\CurrencyFormatter::rupiah($invoice->outstanding_amount) }}
                        </span>
                    </div>
                </div>
                @if($invoice->outstanding_amount > 0)
                <div class="progress mt-3" style="height: 6px;">
                    <div class="progress-bar bg-success" role="progressbar"
                         style="width: {{ ($invoice->paid_amount / $invoice->total_amount) * 100 }}%"
                         aria-valuenow="{{ ($invoice->paid_amount / $invoice->total_amount) * 100 }}"
                         aria-valuemin="0" aria-valuemax="100">
                    </div>
                </div>
                <div class="text-muted fs-7 text-end mt-1">
                    {{ number_format(($invoice->paid_amount / $invoice->total_amount) * 100, 1) }}% terbayar
                </div>
                @endif
            </x-metronic.card>
        </div>
    </div>
@endsection
