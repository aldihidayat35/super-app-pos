@extends('layouts.metronic.app')

@section('title', $shift ? 'Edit Shift' : 'Tambah Shift')
@section('page_title', $shift ? 'Edit Shift' : 'Tambah Shift')

@section('content')
    <x-metronic.card title="{{ $shift ? 'Edit Shift' : 'Tambah Shift' }}">
        <form method="POST" action="{{ $shift ? route('attendance.work-shifts.update', $shift) : route('attendance.work-shifts.store') }}" class="row g-3">
            @csrf @if($shift) @method('PUT') @endif
            <div class="col-md-3"><x-metronic.form-group name="code" label="Kode" required><input name="code" value="{{ old('code', $shift?->code) }}" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-5"><x-metronic.form-group name="name" label="Nama Shift" required><input name="name" value="{{ old('name', $shift?->name) }}" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-4"><x-metronic.form-group name="work_location_id" label="Lokasi"><select name="work_location_id" class="form-select"><option value="">Global</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(old('work_location_id', $shift?->work_location_id) == $location->id)>{{ $location->name }}</option>@endforeach</select></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="start_time" label="Mulai" required><input type="time" name="start_time" value="{{ old('start_time', $shift ? substr($shift->start_time,0,5) : '08:00') }}" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="end_time" label="Selesai" required><input type="time" name="end_time" value="{{ old('end_time', $shift ? substr($shift->end_time,0,5) : '16:00') }}" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="tolerance_late_minutes" label="Toleransi Telat"><input type="number" name="tolerance_late_minutes" value="{{ old('tolerance_late_minutes', $shift?->tolerance_late_minutes ?? 10) }}" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="tolerance_early_leave_minutes" label="Toleransi Pulang"><input type="number" name="tolerance_early_leave_minutes" value="{{ old('tolerance_early_leave_minutes', $shift?->tolerance_early_leave_minutes ?? 10) }}" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="break_minutes" label="Break"><input type="number" name="break_minutes" value="{{ old('break_minutes', $shift?->break_minutes ?? 0) }}" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2 pt-9"><label class="form-check"><input type="hidden" name="is_cross_midnight" value="0"><input type="checkbox" name="is_cross_midnight" value="1" class="form-check-input" @checked(old('is_cross_midnight', $shift?->is_cross_midnight ?? false))><span class="form-check-label">Lintas hari</span></label></div>
            <div class="col-md-2 pt-9"><label class="form-check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $shift?->is_active ?? true))><span class="form-check-label">Aktif</span></label></div>
            <div class="col-md-3"><x-metronic.form-group name="effective_from" label="Efektif Dari"><input type="date" name="effective_from" value="{{ old('effective_from', $shift?->effective_from?->toDateString()) }}" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-3"><x-metronic.form-group name="effective_until" label="Efektif Sampai"><input type="date" name="effective_until" value="{{ old('effective_until', $shift?->effective_until?->toDateString()) }}" class="form-control"></x-metronic.form-group></div>
            <div class="col-12"><button class="btn btn-primary">Simpan</button><a href="{{ route('attendance.work-shifts.index') }}" class="btn btn-light">Batal</a></div>
        </form>
    </x-metronic.card>
@endsection
