@extends('layouts.metronic.app')

@section('title', 'Limit Kredit')
@section('page_title', 'Limit Kredit')

@section('content')
    <x-metronic.page-title title="Limit Kredit Pelanggan" description="AR-05 kontrol limit, status blokir, threshold approval, dan batas overdue." />

    <x-metronic.card title="Daftar Limit Kredit">
        <!-- Desktop Table -->
        <div class="table-responsive d-none d-md-block">
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

        <!-- Mobile Cards -->
        <div class="d-md-none">
            @forelse($limits as $limit)
                <div class="card mb-3 border-primary">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <span class="fw-bold">{{ $limit->customer?->business_name }}</span>
                        <span class="badge bg-light text-primary">{{ $limit->status?->label() ?? 'Aktif' }}</span>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('receivables.credit-limits.update', $limit) }}">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label class="form-label text-muted small">Limit Kredit</label>
                                <input type="number" step="0.01" name="credit_limit" value="{{ $limit->credit_limit }}" class="form-control form-control-lg">
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Saldo Saat Ini</label>
                                <div class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($limit->current_balance) }}</div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Termin (Hari)</label>
                                    <input type="number" name="payment_term_days" value="{{ $limit->payment_term_days }}" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Status</label>
                                    <select name="status" class="form-select">
                                        @foreach($statuses as $status)
                                            <option value="{{ $status->value }}" @selected($limit->status === $status)>{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Threshold Approval</label>
                                <input type="number" name="approval_threshold_amount" value="{{ $limit->approval_threshold_amount }}" class="form-control" placeholder="0">
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Maks Overdue (Hari)</label>
                                <input type="number" name="max_overdue_days" value="{{ $limit->max_overdue_days }}" class="form-control" placeholder="0">
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Alasan Blokir</label>
                                <input type="text" name="blocked_reason" value="{{ $limit->blocked_reason }}" class="form-control" placeholder="Kosongkan jika tidak diblokir">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Simpan Perubahan</button>
                        </form>
                    </div>
                </div>
            @empty
                <x-metronic.empty-state title="Belum ada limit kredit" description="Limit kredit dibuat dari master pelanggan atau transaksi pertama." />
            @endforelse
        </div>

        {{ $limits->links() }}
    </x-metronic.card>
@endsection
