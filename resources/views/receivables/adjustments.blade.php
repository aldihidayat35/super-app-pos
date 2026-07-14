@extends('layouts.metronic.app')

@section('title', 'Koreksi Piutang')
@section('page_title', 'Koreksi Piutang')

@section('content')
    <x-metronic.page-title title="Koreksi Piutang {{ $receivable->number }}" description="AR-08 credit note dan koreksi saldo piutang dengan approval." />

    <div class="row g-5">
        <div class="col-lg-7">
            <x-metronic.card title="Ledger Piutang">
                <div class="mb-4">
                    <div class="text-muted">Pelanggan</div>
                    <div class="fw-bold">{{ $receivable->customer?->business_name }}</div>
                    <div class="text-muted mt-3">Outstanding</div>
                    <div class="fs-3 fw-bold text-danger">{{ App\Support\CurrencyFormatter::rupiah($receivable->outstanding_amount) }}</div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Waktu</th><th>Tipe</th><th>Ref</th><th>Before</th><th>Mutasi</th><th>After</th><th>Catatan</th></tr></thead>
                        <tbody>
                        @forelse($receivable->entries as $entry)
                            <tr>
                                <td>{{ $entry->occurred_at?->format('d/m/Y H:i') }}</td>
                                <td>{{ $entry->entry_type->label() }}</td>
                                <td>{{ $entry->source_no }}</td>
                                <td>{{ App\Support\CurrencyFormatter::rupiah($entry->balance_before) }}</td>
                                <td>{{ App\Support\CurrencyFormatter::rupiah($entry->amount) }}</td>
                                <td>{{ App\Support\CurrencyFormatter::rupiah($entry->balance_after) }}</td>
                                <td>{{ $entry->notes }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-metronic.empty-state title="Ledger kosong" description="Belum ada entri ledger." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-metronic.card>
        </div>
        <div class="col-lg-5">
            <x-metronic.card title="Buat Credit Note">
                <form method="POST" action="{{ route('receivables.adjustments.store', $receivable) }}" class="vstack gap-3">
                    @csrf
                    <input type="number" step="0.01" min="0.01" max="{{ $receivable->outstanding_amount }}" name="amount" class="form-control" placeholder="Nominal">
                    <textarea name="reason" class="form-control" rows="4" placeholder="Alasan koreksi"></textarea>
                    <button class="btn btn-primary">Ajukan Credit Note</button>
                </form>
            </x-metronic.card>

            <x-metronic.card title="Daftar Credit Note" class="mt-5">
                @forelse($creditNotes as $creditNote)
                    <div class="border-bottom py-3">
                        <div class="d-flex justify-content-between"><span class="fw-bold">{{ $creditNote->number }}</span><span>{{ App\Support\CurrencyFormatter::rupiah($creditNote->amount) }}</span></div>
                        <div>{{ $creditNote->reason }}</div>
                        <div class="text-muted">{{ $creditNote->status->label() }}</div>
                        @if($creditNote->status->value === 'pending')
                            <form method="POST" action="{{ route('receivables.credit-notes.approve', $creditNote) }}" class="mt-2">
                                @csrf
                                <input name="approval_note" class="form-control mb-2" placeholder="Catatan approval">
                                <button class="btn btn-sm btn-light-primary">Approve</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <x-metronic.empty-state title="Belum ada credit note" description="Koreksi yang diajukan akan muncul di sini." />
                @endforelse
            </x-metronic.card>
        </div>
    </div>
@endsection
