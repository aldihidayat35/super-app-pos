@extends('layouts.metronic.app')

@section('title', 'Riwayat Shift dan Closing - ' . config('app.name'))
@section('page_title', 'Riwayat Shift dan Closing')

@section('toolbar_actions')
    <a href="{{ route('retail.shifts.export', request()->query()) }}" class="btn btn-light-success">Export</a>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" class="row g-3 mb-6"><div class="col-md-3"><select name="branch_id" class="form-select"><option value="">Semua toko</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') == $branch->id)>{{ $branch->name }}</option>@endforeach</select></div><div class="col-md-2"><select name="status" class="form-select"><option value="">Semua status</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div><div class="col-md-2"><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control"></div><div class="col-md-2"><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control"></div><div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div></form>
        <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>No</th><th>Toko/Kasir</th><th>Status</th><th>Modal</th><th>Tunai/Non Tunai</th><th>Expense</th><th>Expected/Actual/Selisih</th><th>Aksi</th></tr></thead><tbody>
            @forelse($shifts as $shift)<tr><td class="fw-bold">{{ $shift->number }}</td><td>{{ $shift->branch?->name }}<div class="text-muted">{{ $shift->cashier?->name }}</div></td><td><x-metronic.status-badge :status="$shift->status" /></td><td>Rp {{ number_format((float) $shift->opening_cash_amount, 0, ',', '.') }}</td><td>Rp {{ number_format((float) $shift->cash_sales_amount, 0, ',', '.') }}<div class="text-muted">Non Rp {{ number_format((float) $shift->non_cash_sales_amount, 0, ',', '.') }}</div></td><td>Rp {{ number_format((float) $shift->expense_amount, 0, ',', '.') }}</td><td>Rp {{ number_format((float) $shift->expected_cash_amount, 0, ',', '.') }} / Rp {{ number_format((float) $shift->actual_cash_amount, 0, ',', '.') }}<div class="text-muted">Selisih Rp {{ number_format((float) $shift->difference_amount, 0, ',', '.') }}</div></td><td><a href="{{ route('retail.shifts.report', $shift) }}" class="btn btn-sm btn-light-primary">Laporan</a>@if($shift->status->value === 'closing_submitted') <a href="{{ route('retail.shifts.approval', $shift) }}" class="btn btn-sm btn-success">Approval</a>@endif</td></tr>@empty<tr><td colspan="8"><x-metronic.empty-state title="Belum ada shift" description="Riwayat shift akan tampil setelah kasir membuka shift." /></td></tr>@endforelse
        </tbody></table></div>{{ $shifts->links() }}
    </x-metronic.card>
@endsection
