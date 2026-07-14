@extends('layouts.metronic.app')

@section('title', 'Jadwal Shift Karyawan')
@section('page_title', 'Jadwal Shift Karyawan')

@section('content')
    <x-metronic.page-title title="Jadwal Shift Karyawan" description="HR-03 kalender jadwal, assign shift, lokasi, konflik, dan status libur." />
    @can('attendance.update')
    <x-metronic.card title="Assign Jadwal" class="mb-5">
        <form method="POST" action="{{ route('attendance.schedules.store') }}" class="row g-3">@csrf
            <div class="col-md-3"><select name="employee_id" class="form-select">@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="work_shift_id" class="form-select">@foreach($shifts as $shift)<option value="{{ $shift->id }}">{{ $shift->name }} ({{ substr($shift->start_time,0,5) }}-{{ substr($shift->end_time,0,5) }})</option>@endforeach</select></div>
            <div class="col-md-2"><input type="date" name="scheduled_date" value="{{ now()->toDateString() }}" class="form-control"></div>
            <div class="col-md-2"><select name="work_location_id" class="form-select"><option value="">Ikuti shift/karyawan</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Simpan</button></div>
            <div class="col-12"><input name="notes" class="form-control" placeholder="Catatan jadwal/copy schedule/hari libur"></div>
        </form>
    </x-metronic.card>
    @endcan
    <x-metronic.card title="Daftar Jadwal">
        <form method="GET" class="row g-3 mb-5"><div class="col-md-3"><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control"></div><div class="col-md-3"><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control"></div><div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div></form>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Tanggal</th><th>Karyawan</th><th>Shift</th><th>Lokasi</th><th>Jam</th><th>Status</th><th>Catatan</th></tr></thead><tbody>
            @forelse($schedules as $schedule)
                <tr><td>{{ $schedule->scheduled_date?->format('d/m/Y') }}</td><td>{{ $schedule->employee?->name }}</td><td>{{ $schedule->workShift?->name }}</td><td>{{ $schedule->workLocation?->name }}</td><td>{{ $schedule->scheduled_start_at?->format('d/m H:i') }} - {{ $schedule->scheduled_end_at?->format('d/m H:i') }}</td><td>{{ $schedule->status->label() }}</td><td>{{ $schedule->notes }}</td></tr>
            @empty
                <tr><td colspan="7"><x-metronic.empty-state title="Belum ada jadwal" description="Assign jadwal untuk mengaktifkan check-in/out." /></td></tr>
            @endforelse
        </tbody></table></div>
        {{ $schedules->links() }}
    </x-metronic.card>
@endsection
