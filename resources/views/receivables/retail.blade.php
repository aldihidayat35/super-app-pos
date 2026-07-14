@extends('layouts.metronic.app')

@section('title', 'Piutang Toko')
@section('page_title', 'Piutang Toko')

@section('content')
    <x-metronic.page-title title="Piutang Toko Internal" description="AR-07 piutang dari transaksi POS tempo yang terpisah dari kas shift." />

    <x-metronic.card title="Daftar Piutang Toko">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Nomor</th><th>Pelanggan</th><th>Cabang</th><th>Transaksi POS</th><th>Jatuh Tempo</th><th>Outstanding</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($receivables as $receivable)
                    <tr>
                        <td class="fw-bold">{{ $receivable->number }}</td>
                        <td>{{ $receivable->customer?->business_name }}</td>
                        <td>{{ $receivable->workLocation?->name }}</td>
                        <td>{{ $receivable->posSale?->number }}</td>
                        <td>{{ $receivable->due_date?->format('d/m/Y') }}</td>
                        <td class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($receivable->outstanding_amount) }}</td>
                        <td><x-metronic.status-badge :status="$receivable->status->value" :label="$receivable->status->label()" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-metronic.empty-state title="Belum ada piutang toko" description="Piutang toko dibuat otomatis dari pembayaran POS metode Tempo/Piutang." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $receivables->links() }}
    </x-metronic.card>
@endsection
