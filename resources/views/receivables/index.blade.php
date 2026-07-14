@extends('layouts.metronic.app')

@section('title', 'Daftar Piutang')
@section('page_title', 'Daftar Piutang')

@section('content')
    <x-metronic.page-title title="Daftar Piutang" description="AR-01 piutang gudang dan pelanggan langganan dengan aging, status, dan saldo.">
        <a href="{{ route('receivables.payments.create') }}" class="btn btn-primary">Input Pembayaran</a>
    </x-metronic.page-title>

    <x-metronic.card title="Filter Piutang">
        <form method="GET" class="row g-3 mb-5">
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="aging" class="form-select">
                    <option value="">Semua Aging</option>
                    @foreach(['not_due' => 'Belum jatuh tempo', '1_7' => '1-7 hari', '8_30' => '8-30 hari', '31_60' => '31-60 hari', 'over_60' => '> 60 hari'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('aging') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="channel" class="form-select">
                    <option value="">Semua Channel</option>
                    <option value="warehouse" @selected(request('channel') === 'warehouse')>Gudang/B2B</option>
                    <option value="retail" @selected(request('channel') === 'retail')>Toko Internal</option>
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
            <div class="col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Nomor</th><th>Pelanggan</th><th>Referensi</th><th>Jatuh Tempo</th><th>Principal</th><th>Paid/Adj</th><th>Outstanding</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                @forelse($receivables as $receivable)
                    <tr>
                        <td class="fw-bold">{{ $receivable->number }}<div class="text-muted">{{ ucfirst($receivable->channel) }}</div></td>
                        <td><a href="{{ route('receivables.customers.show', $receivable->customer) }}">{{ $receivable->customer?->business_name }}</a><div class="text-muted">{{ $receivable->workLocation?->name }}</div></td>
                        <td>{{ $receivable->source_no }}<div class="text-muted">{{ $receivable->source_type }}</div></td>
                        <td>{{ $receivable->due_date?->format('d/m/Y') }}<div class="text-muted">{{ $receivable->aging_bucket }}</div></td>
                        <td>{{ App\Support\CurrencyFormatter::rupiah($receivable->principal_amount) }}</td>
                        <td>{{ App\Support\CurrencyFormatter::rupiah($receivable->paid_amount) }}<div class="text-muted">CN: {{ App\Support\CurrencyFormatter::rupiah($receivable->adjustment_amount) }}</div></td>
                        <td class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($receivable->outstanding_amount) }}</td>
                        <td><x-metronic.status-badge :status="$receivable->status->value" :label="$receivable->status->label()" /></td>
                        <td class="text-end">
                            <a href="{{ route('receivables.adjustments', $receivable) }}" class="btn btn-sm btn-light">Koreksi</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-metronic.empty-state title="Belum ada piutang" description="Piutang dibuat otomatis dari invoice B2B atau transaksi POS kredit." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $receivables->links() }}
    </x-metronic.card>
@endsection
