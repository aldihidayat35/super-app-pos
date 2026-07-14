@extends('layouts.metronic.app')

@section('title', 'Tutup Shift - ' . config('app.name'))
@section('page_title', 'Tutup Shift')

@section('content')
    <x-metronic.card title="Ringkasan Closing {{ $shift->number }}">
        <div class="row g-4 mb-6">
            @foreach(['opening_cash'=>'Modal', 'cash_sales'=>'Cash Sales', 'non_cash_sales'=>'Non Cash', 'refunds'=>'Refund', 'expenses'=>'Expense', 'expected_cash'=>'Expected Cash'] as $key => $label)
                <div class="col-md-4"><div class="border rounded p-4"><div class="text-muted">{{ $label }}</div><div class="fs-4 fw-bold">Rp {{ number_format((float) ($summary[$key] ?? 0), 0, ',', '.') }}</div></div></div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('retail.shifts.close.submit', $shift) }}">
            @csrf
            <div class="row">
                <div class="col-md-4"><x-metronic.form-group name="actual_cash_amount" label="Uang Fisik Total"><input type="number" step="0.01" min="0" name="actual_cash_amount" class="form-control" value="{{ $summary['expected_cash'] ?? 0 }}"></x-metronic.form-group></div>
                <div class="col-md-8"><x-metronic.form-group name="discrepancy_reason" label="Alasan Selisih"><input name="discrepancy_reason" class="form-control" placeholder="Wajib jika ada selisih"></x-metronic.form-group></div>
            </div>
            <div class="table-responsive mb-5"><table class="table"><thead><tr><th>Pecahan</th><th>Jumlah Lembar/Koin</th></tr></thead><tbody>@foreach($denominations as $i => $denomination)<tr><td>Rp {{ number_format($denomination, 0, ',', '.') }}<input type="hidden" name="cash_counts[{{ $i }}][denomination]" value="{{ $denomination }}"></td><td><input type="number" min="0" name="cash_counts[{{ $i }}][quantity]" value="0" class="form-control"></td></tr>@endforeach</tbody></table></div>
            <x-metronic.form-group name="handover_notes" label="Catatan Serah Terima"><textarea name="handover_notes" rows="3" class="form-control"></textarea></x-metronic.form-group>
            <button class="btn btn-primary" data-confirm="Submit closing akan mengunci input shift hingga supervisor memverifikasi. Lanjutkan?">Submit Closing</button>
        </form>
    </x-metronic.card>
@endsection
