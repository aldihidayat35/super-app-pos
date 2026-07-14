@extends('layouts.metronic.app')

@section('title', 'Limit Kredit')
@section('page_title', 'Limit Kredit')

@section('content')
    <x-metronic.page-title title="Limit Kredit Pelanggan" description="AR-05 kontrol limit, status blokir, threshold approval, dan batas overdue." />

    <x-metronic.card title="Daftar Limit Kredit">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Pelanggan</th><th>Limit</th><th>Saldo</th><th>Termin</th><th>Status</th><th>Blokir/Aturan</th><th class="text-end">Update</th></tr></thead>
                <tbody>
                @forelse($limits as $limit)
                    <tr>
                        <form method="POST" action="{{ route('receivables.credit-limits.update', $limit) }}">
                            @csrf
                            @method('PUT')
                            <td>{{ $limit->customer?->business_name }}</td>
                            <td><input type="number" step="0.01" name="credit_limit" value="{{ $limit->credit_limit }}" class="form-control"></td>
                            <td>{{ App\Support\CurrencyFormatter::rupiah($limit->current_balance) }}</td>
                            <td><input type="number" name="payment_term_days" value="{{ $limit->payment_term_days }}" class="form-control"></td>
                            <td>
                                <select name="status" class="form-select">
                                    @foreach($statuses as $status)
                                        <option value="{{ $status->value }}" @selected($limit->status === $status)>{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="number" name="approval_threshold_amount" value="{{ $limit->approval_threshold_amount }}" class="form-control mb-2" placeholder="Threshold approval">
                                <input type="number" name="max_overdue_days" value="{{ $limit->max_overdue_days }}" class="form-control mb-2" placeholder="Maks overdue">
                                <input name="blocked_reason" value="{{ $limit->blocked_reason }}" class="form-control" placeholder="Alasan blokir">
                            </td>
                            <td class="text-end"><button class="btn btn-sm btn-primary">Simpan</button></td>
                        </form>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-metronic.empty-state title="Belum ada limit kredit" description="Limit kredit dibuat dari master pelanggan atau transaksi pertama." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $limits->links() }}
    </x-metronic.card>
@endsection
