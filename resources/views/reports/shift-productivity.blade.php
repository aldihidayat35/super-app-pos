@extends('layouts.metronic.app')

@section('title', 'Produktivitas Shift dan POS')
@section('page_title', 'Produktivitas Shift dan POS')

@section('content')
    <x-metronic.page-title title="Produktivitas Shift dan POS" description="HR-08 korelasi karyawan hadir, omzet, transaksi, void, diskon, selisih kas, dan closing. Bukan penilaian otomatis mutlak." />
    <x-metronic.card title="Filter & Data">
        <form method="GET" class="row g-3 mb-5"><div class="col-md-3"><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control"></div><div class="col-md-3"><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control"></div><div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div></form>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Shift</th><th>Kasir</th><th>Cabang</th><th>Attendance</th><th>Transaksi</th><th>Omzet</th><th>Diskon</th><th>Selisih Closing</th><th>Status</th></tr></thead><tbody>
            @forelse($shifts as $shift)
                @php($sales = $salesByShift[$shift->id] ?? null)
                <tr><td class="fw-bold">{{ $shift->number }}<div class="text-muted">{{ $shift->opened_at?->format('d/m/Y H:i') }}</div></td><td>{{ $shift->cashier?->name }}</td><td>{{ $shift->branch?->name }}</td><td>{{ $shift->attendance?->check_in_at?->format('H:i') ?: ($shift->attendance_override_reason ? 'Override' : '-') }}</td><td>{{ $sales?->transaction_count ?? 0 }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($sales?->omzet ?? 0) }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($sales?->discount_total ?? 0) }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($shift->difference_amount) }}</td><td>{{ $shift->status->label() }}</td></tr>
            @empty
                <tr><td colspan="9"><x-metronic.empty-state title="Belum ada shift" description="Shift POS pada periode ini belum ada." /></td></tr>
            @endforelse
        </tbody></table></div>
        {{ $shifts->links() }}
    </x-metronic.card>
@endsection
