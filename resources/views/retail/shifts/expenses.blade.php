@extends('layouts.metronic.app')

@section('title', 'Pengeluaran Kecil - ' . config('app.name'))
@section('page_title', 'Pengeluaran Kecil')

@section('content')
    <div class="row g-6">
        <div class="col-lg-4">
            <x-metronic.card title="Input Pengeluaran">
                <form method="POST" action="{{ route('retail.shifts.expenses.store', $shift) }}">
                    @csrf
                    <x-metronic.form-group name="category" label="Kategori" required><select name="category" class="form-select"><option value="plastic">Plastik</option><option value="transport">Transport</option><option value="parking">Parkir</option><option value="operational">Operasional</option><option value="other">Lainnya</option></select></x-metronic.form-group>
                    <x-metronic.form-group name="payment_method" label="Metode" required><select name="payment_method" class="form-select"><option value="cash">Tunai</option><option value="bank_transfer">Transfer</option><option value="qris">QRIS</option><option value="manual">Manual</option></select></x-metronic.form-group>
                    <x-metronic.form-group name="amount" label="Nominal" required><input type="number" step="0.01" min="0" name="amount" class="form-control"></x-metronic.form-group>
                    <x-metronic.form-group name="spent_at" label="Waktu"><input type="datetime-local" name="spent_at" class="form-control"></x-metronic.form-group>
                    <x-metronic.form-group name="proof_path" label="Bukti Foto/Path"><input name="proof_path" class="form-control" placeholder="storage/..."></x-metronic.form-group>
                    <x-metronic.form-group name="notes" label="Catatan"><textarea name="notes" rows="3" class="form-control"></textarea></x-metronic.form-group>
                    <button class="btn btn-primary">Simpan Pengeluaran</button>
                </form>
            </x-metronic.card>
        </div>
        <div class="col-lg-8">
            <x-metronic.card title="Daftar Pengeluaran">
                <div class="alert alert-info">Total expense tunai shift ini: Rp {{ number_format((float) ($summary['expenses'] ?? 0), 0, ',', '.') }}</div>
                <div class="table-responsive"><table class="table"><thead><tr><th>Kategori</th><th>Metode</th><th>Nominal</th><th>User</th><th>Waktu</th><th>Catatan</th></tr></thead><tbody>
                    @forelse($expenses as $expense)<tr><td>{{ $expense->category }}</td><td>{{ $expense->payment_method }}</td><td>Rp {{ number_format((float) $expense->amount, 0, ',', '.') }}</td><td>{{ $expense->creator?->name }}</td><td>{{ $expense->spent_at?->format('d/m/Y H:i') }}</td><td>{{ $expense->notes }}</td></tr>@empty<tr><td colspan="6"><x-metronic.empty-state title="Belum ada pengeluaran" description="Pengeluaran kecil shift akan tampil di sini." /></td></tr>@endforelse
                </tbody></table></div>{{ $expenses->links() }}
            </x-metronic.card>
        </div>
    </div>
@endsection
