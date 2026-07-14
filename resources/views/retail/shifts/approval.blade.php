@extends('layouts.metronic.app')

@section('title', 'Verifikasi Closing - ' . config('app.name'))
@section('page_title', 'Verifikasi Closing')

@section('content')
    <x-metronic.card title="Review {{ $shift->number }}">
        <div class="row g-4 mb-6">
            @foreach(['expected_cash'=>'Expected', 'actual_cash'=>'Actual', 'difference'=>'Selisih', 'cash_sales'=>'Tunai', 'non_cash_sales'=>'Non Tunai', 'expenses'=>'Expense'] as $key => $label)
                <div class="col-md-4"><div class="border rounded p-4"><div class="text-muted">{{ $label }}</div><div class="fs-4 fw-bold">Rp {{ number_format((float) ($summary[$key] ?? 0), 0, ',', '.') }}</div></div></div>
            @endforeach
        </div>
        @if(abs((float) ($summary['difference'] ?? 0)) > (float) $shift->discrepancy_threshold_amount)<div class="alert alert-warning">Selisih melewati threshold dan perlu perhatian supervisor.</div>@endif
        <div class="mb-5"><div class="text-muted">Alasan Selisih</div>{{ $shift->discrepancy_reason ?: '-' }}</div>
        <form method="POST" action="{{ route('retail.shifts.approve', $shift) }}" class="d-inline">@csrf<textarea name="notes" class="form-control mb-3" placeholder="Catatan approval"></textarea><button class="btn btn-success">Approve Closing</button></form>
        <form method="POST" action="{{ route('retail.shifts.reject', $shift) }}" class="mt-3">@csrf<x-metronic.form-group name="notes" label="Alasan Reject" required><textarea name="notes" class="form-control" required></textarea></x-metronic.form-group><button class="btn btn-light-danger">Reject/Reopen</button></form>
    </x-metronic.card>
@endsection
