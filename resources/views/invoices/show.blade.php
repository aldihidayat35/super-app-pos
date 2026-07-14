@extends('layouts.metronic.app')

@section('title', $invoice->number)
@section('page_title', 'Detail Invoice')

@section('content')
    <x-metronic.page-title :title="$invoice->number" description="Detail tagihan, pembayaran, dan PDF.">
        <a href="{{ route('invoices.index') }}" class="btn btn-light">Kembali</a>
        <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-light-primary">PDF</a>
        @if($invoice->outstanding_amount > 0)<a href="{{ route('payments.create', ['invoice_id' => $invoice->id]) }}" class="btn btn-primary">Bayar / Input Pembayaran</a>@endif
    </x-metronic.page-title>
    <div class="row g-5 mb-5">
        <div class="col-lg-4"><x-metronic.card title="Pelanggan"><div class="fw-bold">{{ $invoice->customer?->business_name }}</div><div class="text-muted">{{ $invoice->customer?->code }}</div><div>Order: {{ $invoice->order?->number ?: '-' }}</div></x-metronic.card></div>
        <div class="col-lg-4"><x-metronic.card title="Tanggal"><div>Issue: {{ $invoice->issue_date?->format('d/m/Y') }}</div><div>Due: {{ $invoice->due_date?->format('d/m/Y') }}</div><div>Status: <x-metronic.status-badge :status="$invoice->status->value" :label="$invoice->status->label()" /></div></x-metronic.card></div>
        <div class="col-lg-4"><x-metronic.card title="Saldo"><div>Total: {{ App\Support\CurrencyFormatter::rupiah($invoice->total_amount) }}</div><div>Paid: {{ App\Support\CurrencyFormatter::rupiah($invoice->paid_amount) }}</div><div class="fw-bold">Balance: {{ App\Support\CurrencyFormatter::rupiah($invoice->outstanding_amount) }}</div></x-metronic.card></div>
    </div>
    <x-metronic.card title="Item Invoice" class="mb-5">
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Deskripsi</th><th>Qty</th><th>Harga</th><th>Diskon</th><th>Pajak</th><th>Total</th></tr></thead><tbody>@foreach($invoice->items as $item)<tr><td>{{ $item->description }}</td><td>{{ $item->quantity }} {{ $item->unit_name_snapshot }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($item->unit_price) }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($item->discount_amount) }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($item->tax_amount) }}</td><td class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($item->line_total) }}</td></tr>@endforeach</tbody></table></div>
    </x-metronic.card>
    <x-metronic.card title="Timeline Pembayaran">
        @forelse($invoice->allocations as $allocation)
            <div class="border-bottom py-3"><div class="fw-bold">{{ $allocation->payment?->number }} · {{ App\Support\CurrencyFormatter::rupiah($allocation->amount) }}</div><div class="text-muted">{{ $allocation->payment?->payment_date?->format('d/m/Y') }} · {{ $allocation->payment?->method?->label() }} · {{ $allocation->payment?->status?->label() }}</div></div>
        @empty
            <x-metronic.empty-state title="Belum ada pembayaran" description="Pembayaran yang dialokasikan ke invoice ini akan tampil di sini." />
        @endforelse
    </x-metronic.card>
@endsection
