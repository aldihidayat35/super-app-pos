@extends('layouts.metronic.app')

@section('title', 'Input Pembayaran')
@section('page_title', 'Input Pembayaran')

@section('content')
    <x-metronic.page-title title="Upload / Entri Pembayaran" description="Pembayaran penuh atau parsial untuk invoice." />
    <x-metronic.card title="Form Pembayaran">
        <form method="POST" action="{{ route('payments.store') }}" enctype="multipart/form-data" class="row g-4">
            @csrf
            <div class="col-md-6"><label class="form-label">Invoice</label><select name="invoice_id" class="form-select" required>@foreach($invoices as $invoice)<option value="{{ $invoice->id }}" @selected($selectedInvoiceId===$invoice->id)>{{ $invoice->number }} · {{ $invoice->customer?->business_name }} · Sisa {{ App\Support\CurrencyFormatter::rupiah($invoice->outstanding_amount) }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Nominal</label><input name="amount" type="number" step="0.01" min="0.01" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Metode</label><select name="method" class="form-select">@foreach($methods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Tanggal</label><input name="payment_date" type="date" value="{{ now()->toDateString() }}" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Bank</label><input name="bank_name" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Referensi</label><input name="reference_no" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Bukti</label><input name="proof" type="file" class="form-control"></div>
            <div class="col-12"><label class="form-label">Catatan</label><textarea name="notes" class="form-control"></textarea></div>
            <div class="col-12"><button class="btn btn-primary">Simpan Pembayaran</button></div>
        </form>
    </x-metronic.card>
@endsection
