@extends('layouts.metronic.app')

@section('title', 'Laporan Kehadiran')
@section('page_title', 'Laporan Kehadiran')

@section('content')
    <x-metronic.page-title title="Laporan Kehadiran" description="HR-07 laporan harian/bulanan per toko dan karyawan: hadir, telat, izin, sakit, alfa, lembur, jam kerja." />
    <div class="row g-5 mb-5">@foreach(['present' => 'Hadir', 'late' => 'Telat', 'early_leave' => 'Pulang Cepat', 'permission' => 'Izin', 'sick' => 'Sakit', 'leave' => 'Cuti'] as $key => $label)<div class="col-md-2"><x-metronic.card><div class="text-muted">{{ $label }}</div><div class="fs-2 fw-bold">{{ $summary[$key] ?? 0 }}</div></x-metronic.card></div>@endforeach</div>
    <x-metronic.card title="Filter & Data">
        <form method="GET" class="row g-3 mb-5"><div class="col-md-2"><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control"></div><div class="col-md-2"><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control"></div><div class="col-md-3"><select name="work_location_id" class="form-select"><option value="">Semua lokasi</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(request('work_location_id') == $location->id)>{{ $location->name }}</option>@endforeach</select></div><div class="col-md-3"><select name="employee_id" class="form-select"><option value="">Semua karyawan</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>{{ $employee->name }}</option>@endforeach</select></div><div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div></form>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Tanggal</th><th>Karyawan</th><th>Lokasi</th><th>Check-in</th><th>Check-out</th><th>Status</th><th>Telat/Pulang Cepat</th><th>Jam Kerja</th></tr></thead><tbody>
            @forelse($attendances as $attendance)
                <tr><td>{{ $attendance->attendance_date?->format('d/m/Y') }}</td><td>{{ $attendance->employee?->name }}</td><td>{{ $attendance->workLocation?->name }}</td><td>{{ $attendance->check_in_at?->format('H:i') ?: '-' }}</td><td>{{ $attendance->check_out_at?->format('H:i') ?: '-' }}</td><td>{{ $attendance->status->label() }}</td><td>{{ $attendance->late_minutes }}m / {{ $attendance->early_leave_minutes }}m</td><td>{{ $attendance->worked_minutes }}m</td></tr>
            @empty
                <tr><td colspan="8"><x-metronic.empty-state title="Belum ada data" description="Belum ada absensi pada filter ini." /></td></tr>
            @endforelse
        </tbody></table></div>
        {{ $attendances->links() }}
    </x-metronic.card>
@endsection
