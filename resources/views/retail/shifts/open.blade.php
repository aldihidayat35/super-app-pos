@extends('layouts.metronic.app')

@section('title', 'Buka Shift Kasir - ' . config('app.name'))
@section('page_title', 'Buka Shift Kasir')

@section('content')
    <div class="alert alert-info">Kasir wajib sudah check-in pada jadwal aktif sebelum membuka shift POS. Override hanya untuk supervisor/approver dan wajib mengisi alasan.</div>
    <x-metronic.card title="Buka Shift">
        <form method="POST" action="{{ route('retail.shifts.store') }}" class="row g-3">
            @csrf
            <div class="col-md-4"><x-metronic.form-group name="branch_id" label="Cabang/Toko" required><select name="branch_id" class="form-select">@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></x-metronic.form-group></div>
            <div class="col-md-4"><x-metronic.form-group name="terminal_code" label="Terminal"><input name="terminal_code" class="form-control" placeholder="POS-01"></x-metronic.form-group></div>
            <div class="col-md-4"><x-metronic.form-group name="opening_cash_amount" label="Modal Kas Awal" required><input type="number" step="0.01" min="0" name="opening_cash_amount" class="form-control" required></x-metronic.form-group></div>
            <div class="col-md-4"><x-metronic.form-group name="discrepancy_threshold_amount" label="Threshold Selisih"><input type="number" step="0.01" min="0" name="discrepancy_threshold_amount" value="50000" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-12"><x-metronic.form-group name="attendance_override_reason" label="Alasan Override Absensi Supervisor"><textarea name="attendance_override_reason" rows="2" class="form-control" placeholder="Isi hanya bila supervisor membuka shift tanpa check-in aktif."></textarea></x-metronic.form-group></div>
            <div class="col-md-12"><x-metronic.form-group name="notes" label="Catatan Serah Terima"><textarea name="notes" rows="3" class="form-control"></textarea></x-metronic.form-group></div>
            <div class="col-md-12"><button class="btn btn-primary">Buka Shift</button></div>
        </form>
    </x-metronic.card>
@endsection
