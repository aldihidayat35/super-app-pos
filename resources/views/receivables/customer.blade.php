@extends('layouts.metronic.app')

@section('title', 'Kartu Piutang Pelanggan')
@section('page_title', 'Kartu Piutang Pelanggan')

@section('content')
    <x-metronic.page-title title="Kartu Piutang {{ $customer->business_name }}" description="AR-03 kartu piutang, ledger, limit, dan reminder pelanggan.">
        <a href="{{ route('receivables.payments.create', ['customer_id' => $customer->id]) }}" class="btn btn-primary">Input Pembayaran</a>
    </x-metronic.page-title>

    <div class="row g-5 mb-5">
        <div class="col-md-4"><x-metronic.card title="Limit Kredit"><div class="fs-3 fw-bold">{{ App\Support\CurrencyFormatter::rupiah($customer->credit_limit) }}</div><div class="text-muted">Termin {{ $customer->payment_term_days }} hari</div></x-metronic.card></div>
        @php($availableLimit = App\Support\Decimal::sub((string) $customer->credit_limit, (string) $customer->receivable_balance, 2))
        <div class="col-md-4"><x-metronic.card title="Saldo Piutang"><div class="fs-3 fw-bold text-danger">{{ App\Support\CurrencyFormatter::rupiah($customer->receivable_balance) }}</div><div class="text-muted">Sisa limit {{ App\Support\CurrencyFormatter::rupiah(App\Support\Decimal::compare($availableLimit, '0.00', 2) > 0 ? $availableLimit : '0.00') }}</div></x-metronic.card></div>
        <div class="col-md-4"><x-metronic.card title="Status Kredit"><x-metronic.status-badge :status="$customer->creditLimit?->status?->value ?? 'active'" :label="$customer->creditLimit?->status?->label() ?? 'Aktif'" /><div class="text-muted mt-2">{{ $customer->creditLimit?->blocked_reason }}</div></x-metronic.card></div>
    </div>

    <x-metronic.card title="Ledger Piutang">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Nomor</th><th>Referensi</th><th>Jatuh Tempo</th><th>Outstanding</th><th>Ledger Terakhir</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($receivables as $receivable)
                    <tr>
                        <td class="fw-bold">{{ $receivable->number }}</td>
                        <td>{{ $receivable->source_no }}</td>
                        <td>{{ $receivable->due_date?->format('d/m/Y') }}</td>
                        <td class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($receivable->outstanding_amount) }}</td>
                        <td>
                            @foreach($receivable->entries->take(3) as $entry)
                                <div>{{ $entry->occurred_at?->format('d/m/Y H:i') }} — {{ $entry->entry_type->label() }}: {{ App\Support\CurrencyFormatter::rupiah($entry->amount) }}</div>
                            @endforeach
                        </td>
                        <td><x-metronic.status-badge :status="$receivable->status->value" :label="$receivable->status->label()" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-metronic.empty-state title="Belum ada ledger" description="Belum ada piutang untuk pelanggan ini." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $receivables->links() }}
    </x-metronic.card>

    <x-metronic.card title="Catatan Penagihan" class="mt-5">
        @forelse($notes as $note)
            <div class="border-bottom py-3">
                <div class="fw-bold">{{ $note->channel }} — {{ $note->contact_person ?: '-' }}</div>
                <div>{{ $note->note }}</div>
                <div class="text-muted">Follow-up: {{ $note->next_follow_up_date?->format('d/m/Y') ?: '-' }}</div>
            </div>
        @empty
            <x-metronic.empty-state title="Belum ada reminder" description="Catatan reminder akan muncul setelah tim menagih pelanggan." />
        @endforelse
    </x-metronic.card>
@endsection
