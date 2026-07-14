@extends('layouts.metronic.app')

@section('title', $employee ? 'Edit Karyawan' : 'Tambah Karyawan')
@section('page_title', $employee ? 'Edit Karyawan' : 'Tambah Karyawan')

@section('content')
    <x-metronic.card title="{{ $employee ? 'Edit Karyawan' : 'Tambah Karyawan' }}">
        <form method="POST" action="{{ $employee ? route('attendance.employees.update', $employee) : route('attendance.employees.store') }}" class="row g-3">
            @csrf @if($employee) @method('PUT') @endif
            <div class="col-md-3"><x-metronic.form-group name="employee_no" label="NIK Internal" required><input name="employee_no" value="{{ old('employee_no', $employee?->employee_no) }}" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-5"><x-metronic.form-group name="name" label="Nama" required><input name="name" value="{{ old('name', $employee?->name) }}" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-4"><x-metronic.form-group name="user_id" label="User Opsional"><select name="user_id" class="form-select"><option value="">Tanpa user</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(old('user_id', $employee?->user_id) == $user->id)>{{ $user->name }} — {{ $user->email }}</option>@endforeach</select></x-metronic.form-group></div>
            <div class="col-md-4"><x-metronic.form-group name="work_location_id" label="Lokasi Kerja" required><select name="work_location_id" class="form-select">@foreach($locations as $location)<option value="{{ $location->id }}" @selected(old('work_location_id', $employee?->work_location_id) == $location->id)>{{ $location->name }}</option>@endforeach</select></x-metronic.form-group></div>
            <div class="col-md-3"><x-metronic.form-group name="position" label="Posisi"><input name="position" value="{{ old('position', $employee?->position) }}" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-3"><x-metronic.form-group name="whatsapp_number" label="Nomor WA"><input name="whatsapp_number" value="{{ old('whatsapp_number', $employee?->whatsapp_number) }}" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="joined_at" label="Tanggal Masuk"><input type="date" name="joined_at" value="{{ old('joined_at', $employee?->joined_at?->toDateString()) }}" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-3"><x-metronic.form-group name="status" label="Status"><select name="status" class="form-select">@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', $employee?->status?->value ?? 'active') === $status->value)>{{ $status->label() }}</option>@endforeach</select></x-metronic.form-group></div>
            <div class="col-md-3 pt-9"><label class="form-check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $employee?->is_active ?? true))><span class="form-check-label">Aktif</span></label></div>
            <div class="col-12"><button class="btn btn-primary">Simpan</button><a href="{{ route('attendance.employees.index') }}" class="btn btn-light">Batal</a></div>
        </form>
    </x-metronic.card>
@endsection
