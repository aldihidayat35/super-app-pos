@extends('layouts.metronic.app')

@section('title', 'Invoice')
@section('page_title', 'Invoice')

@section('content')
    <x-metronic.page-title title="Daftar Invoice" description="Tagihan B2B/POS sesuai scope akses.">
        <a href="{{ route('payments.create') }}" class="btn btn-primary">Input Pembayaran</a>
    </x-metronic.page-title>
    <x-metronic.card title="Filter Invoice">
        <form class="row g-3 mb-5">
            <div class="col-md-5"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nomor/customer"></div>
            <div class="col-md-3"><select name="status" class="form-select"><option value="">Semua Status</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nomor</th><th>Pelanggan</th><th>Tanggal</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
            @forelse($invoices as $invoice)
                <tr><td class="fw-bold">{{ $invoice->number }}<div class="text-muted">{{ $invoice->source_type }} {{ $invoice->order?->number }}</div></td><td>{{ $invoice->customer?->business_name }}</td><td>{{ $invoice->issue_date?->format('d/m/Y') }}<div class="text-muted">Due: {{ $invoice->due_date?->format('d/m/Y') }}</div></td><td>{{ App\Support\CurrencyFormatter::rupiah($invoice->total_amount) }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($invoice->paid_amount) }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($invoice->outstanding_amount) }}</td><td><x-metronic.status-badge :status="$invoice->status->value" :label="$invoice->status->label()" /></td><td class="text-end"><a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-light">Detail</a></td></tr>
            @empty
                <tr><td colspan="8"><x-metronic.empty-state title="Belum ada invoice" description="Invoice akan muncul setelah order B2B diterbitkan." /></td></tr>
            @endforelse
        </tbody></table></div>
        {{ $invoices->links() }}
    </x-metronic.card>
@endsection
