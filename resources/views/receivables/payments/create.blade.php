@extends('layouts.metronic.app')

@section('title', 'Input Pembayaran Piutang')
@section('page_title', 'Input Pembayaran Piutang')

@section('content')
    <x-metronic.page-title title="Input Pembayaran Piutang" description="AR-04 alokasi pembayaran multi-invoice, parsial, dan bukti bayar." />

    <x-metronic.card title="Form Pembayaran">
        <form method="POST" action="{{ route('receivables.payments.store') }}" enctype="multipart/form-data" class="row g-4">
            @csrf
            <div class="col-md-4">
                <label class="form-label">Pelanggan</label>
                <select name="customer_id" class="form-select" onchange="this.form.method='GET'; this.form.action='{{ route('receivables.payments.create') }}'; this.form.submit()">
                    <option value="">Pilih pelanggan</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((string) $selectedCustomerId === (string) $customer->id)>{{ $customer->business_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Metode</label>
                <select name="method" class="form-select">
                    @foreach($methods as $value => $label)
                        @if($value !== 'credit')
                            <option value="{{ $value }}" @selected(old('method') === $value)>{{ $label }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Tanggal Bayar</label><input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">No Referensi</label><input name="reference_no" value="{{ old('reference_no') }}" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Bukti Bayar</label><input type="file" name="proof" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Catatan</label><input name="notes" value="{{ old('notes') }}" class="form-control"></div>
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}">

            <div class="col-12">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Piutang</th><th>Jatuh Tempo</th><th>Outstanding</th><th>Alokasi Pembayaran</th></tr></thead>
                        <tbody>
                        @forelse($receivables as $receivable)
                            <tr>
                                <td>{{ $receivable->number }}<div class="text-muted">{{ $receivable->source_no }}</div></td>
                                <td>{{ $receivable->due_date?->format('d/m/Y') }}</td>
                                <td>{{ App\Support\CurrencyFormatter::rupiah($receivable->outstanding_amount) }}</td>
                                <td><input type="number" step="0.01" min="0" max="{{ $receivable->outstanding_amount }}" name="allocations[{{ $receivable->id }}]" value="{{ old('allocations.'.$receivable->id, 0) }}" class="form-control"></td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-metronic.empty-state title="Tidak ada piutang terbuka" description="Pilih pelanggan yang memiliki saldo piutang." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-12 text-end"><button class="btn btn-primary">Simpan Pembayaran</button></div>
        </form>
    </x-metronic.card>
@endsection
