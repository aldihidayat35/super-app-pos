@extends('layouts.metronic.app')

@section('title', 'Detail Penjualan POS - ' . config('app.name'))
@section('page_title', 'Detail Penjualan dan Struk')

@section('toolbar_actions')
    <a href="{{ route('retail.sales.print', $sale) }}" class="btn btn-light-primary"><i class="ki-outline ki-printer"></i> Cetak Struk</a>
    @can('void', $sale)<a href="{{ route('retail.sales.void', $sale) }}" class="btn btn-light-danger">Void</a>@endcan
    @can('return', $sale)<a href="{{ route('retail.sales.return', $sale) }}" class="btn btn-light-warning">Retur</a>@endcan
@endsection

@section('content')
    @php($canSensitive = auth()->user()?->can('margins.view_sensitive'))
    <div class="row g-6">
        <div class="col-lg-4">
            <x-metronic.card title="Ringkasan">
                <div class="mb-3"><div class="text-muted">Nomor</div><div class="fw-bold">{{ $sale->number }}</div></div>
                <div class="mb-3"><div class="text-muted">Status</div><x-metronic.status-badge :status="$sale->status" /></div>
                <div class="mb-3"><div class="text-muted">Waktu</div>{{ $sale->completed_at?->format('d/m/Y H:i') }}</div>
                <div class="mb-3"><div class="text-muted">Cabang/Kasir</div>{{ $sale->branch?->name }} / {{ $sale->cashier?->name }}</div>
                <div class="mb-3"><div class="text-muted">Pelanggan</div>{{ $sale->customer?->business_name ?? 'Umum' }}</div>
                <div class="mb-3"><div class="text-muted">Grand Total</div><div class="fs-3 fw-bold">Rp {{ number_format((float) $sale->grand_total_amount, 0, ',', '.') }}</div></div>
                <div class="mb-3"><div class="text-muted">Bayar / Kembali</div>Rp {{ number_format((float) $sale->paid_amount, 0, ',', '.') }} / Rp {{ number_format((float) $sale->change_amount, 0, ',', '.') }}</div>
            </x-metronic.card>
        </div>
        <div class="col-lg-8">
            <x-metronic.card title="Item">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Qty</th>@if($canSensitive)<th>HPP/Margin</th>@endif<th>Harga</th><th>Total</th></tr></thead>
                        <tbody>
                        @foreach($sale->items as $item)
                            <tr>
                                <td>{{ $item->sku_snapshot }}<div class="text-muted">{{ $item->product_name_snapshot }}</div></td>
                                <td>{{ $item->quantity }} {{ $item->unit_name_snapshot }}<div class="text-muted">Base {{ $item->base_quantity }}</div></td>
                                @if($canSensitive)<td>HPP Rp {{ number_format((float) $item->hpp_snapshot, 0, ',', '.') }}<div class="text-muted">Margin Rp {{ number_format((float) $item->margin_amount, 0, ',', '.') }}</div></td>@endif
                                <td>Rp {{ number_format((float) $item->selected_price, 0, ',', '.') }}<div class="text-muted">Diskon {{ $item->discount_percent }}%</div></td>
                                <td class="fw-bold">Rp {{ number_format((float) $item->line_total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-metronic.card>
            <x-metronic.card title="Pembayaran & Mutasi" class="mt-6">
                <div class="row">
                    <div class="col-md-6"><h6>Pembayaran</h6>@foreach($sale->payments as $payment)<div>{{ $payment->method->label() }} — Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</div>@endforeach</div>
                    <div class="col-md-6"><h6>Mutasi Stok</h6>@forelse($sale->stockMutations as $mutation)<div>{{ $mutation->mutation_type->label() }} {{ $mutation->quantity_on_hand_change }} — {{ $mutation->product?->sku }}</div>@empty<div class="text-muted">Belum ada mutasi.</div>@endforelse</div>
                </div>
            </x-metronic.card>
        </div>
    </div>
@endsection
