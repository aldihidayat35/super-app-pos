@extends('layouts.metronic.app')

@section('title', 'Jadwal Shift Karyawan')
@section('page_title', 'Jadwal Shift Karyawan')

@section('content')
    <x-metronic.page-title title="Jadwal Shift Karyawan" description="Atur pola kerja Senin sampai Minggu, lalu sistem menyiapkan jadwal harian secara otomatis." />
    @if(auth()->user()->can('attendance.update') && auth()->user()->hasAnyRole(['kepala_toko', 'kepala_gudang', 'super_admin']))
    <x-metronic.card title="Pola Jadwal Mingguan" class="mb-5">
        <form method="POST" action="{{ route('attendance.schedule-patterns.store') }}" class="row g-4">@csrf
            <div class="col-md-4"><label class="form-label">Karyawan</label><select name="employee_id" class="form-select form-select-solid" required>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Lokasi</label><select name="work_location_id" class="form-select form-select-solid" required>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Mulai Senin</label><input type="date" name="effective_from" value="{{ now()->startOfWeek()->addWeek()->toDateString() }}" class="form-control form-control-solid" required></div>
            @foreach([1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'] as $day=>$label)
                <div class="col-xl col-md-3 col-6"><label class="form-label">{{ $label }}</label><select name="days[{{ $day }}]" class="form-select form-select-sm" required><option value="off">Libur</option>@foreach($shifts as $shift)<option value="{{ $shift->id }}">{{ $shift->name }} ({{ substr($shift->start_time,0,5) }})</option>@endforeach</select></div>
            @endforeach
            <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary">Simpan Pola dan Buat Jadwal</button></div>
        </form>
        @if($patterns->isNotEmpty())<div class="separator my-5"></div><div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted"><th>Karyawan</th><th>Lokasi</th><th>Mulai</th><th>Senin</th><th>Selasa</th><th>Rabu</th><th>Kamis</th><th>Jumat</th><th>Sabtu</th><th>Minggu</th></tr></thead><tbody>@foreach($patterns as $pattern)<tr><td class="fw-bold">{{ $pattern->employee?->name }}</td><td>{{ $pattern->workLocation?->name }}</td><td>{{ $pattern->effective_from->format('d/m/Y') }}</td>@foreach(range(1,7) as $weekday)@php($patternDay = $pattern->days->firstWhere('weekday',$weekday))<td><span class="badge {{ $patternDay?->workShift ? 'badge-light-primary' : 'badge-light' }}">{{ $patternDay?->workShift?->name ?? 'Libur' }}</span></td>@endforeach</tr>@endforeach</tbody></table></div>@endif
    </x-metronic.card>
    <x-metronic.card title="Pengecualian Tanggal Tertentu" class="mb-5">
        <div class="text-muted fs-7 mb-4">Gunakan bagian ini jika jadwal pada satu tanggal berbeda dari pola mingguan. Perubahan ini tidak akan ditimpa generator.</div>
        <form method="POST" action="{{ route('attendance.schedules.store') }}" class="row g-3">@csrf
            <div class="col-md-3"><select name="employee_id" class="form-select">@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="work_shift_id" class="form-select">@foreach($shifts as $shift)<option value="{{ $shift->id }}">{{ $shift->name }} ({{ substr($shift->start_time,0,5) }}-{{ substr($shift->end_time,0,5) }})</option>@endforeach</select></div>
            <div class="col-md-2"><input type="date" name="scheduled_date" value="{{ now()->toDateString() }}" class="form-control"></div>
            <div class="col-md-2"><select name="work_location_id" class="form-select"><option value="">Ikuti shift/karyawan</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Simpan</button></div>
            <div class="col-12"><input name="notes" class="form-control" placeholder="Catatan jadwal/copy schedule/hari libur"></div>
        </form>
    </x-metronic.card>
    @endif
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
