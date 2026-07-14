@extends('layouts.metronic.app')

@section('title', 'Master Karyawan')
@section('page_title', 'Master Karyawan')

@section('content')
    <x-metronic.page-title title="Master Karyawan" description="HR-01 data karyawan, posisi, lokasi kerja, user, status, dan histori penempatan.">
        @can('attendance.update')<a href="{{ route('attendance.employees.create') }}" class="btn btn-primary">Tambah Karyawan</a>@endcan
    </x-metronic.page-title>
    <x-metronic.card title="Filter Karyawan">
        <form method="GET" class="row g-3 mb-5">
            <div class="col-md-4"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama/NIK internal"></div>
            <div class="col-md-3"><select name="work_location_id" class="form-select"><option value="">Semua lokasi</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(request('work_location_id') == $location->id)>{{ $location->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="status" class="form-select"><option value="">Semua status</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>NIK</th><th>Nama</th><th>Lokasi</th><th>Posisi</th><th>User</th><th>WA</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
            @forelse($employees as $employee)
                <tr>
                    <td class="fw-bold">{{ $employee->employee_no }}</td><td>{{ $employee->name }}<div class="text-muted">Masuk: {{ $employee->joined_at?->format('d/m/Y') ?: '-' }}</div></td><td>{{ $employee->workLocation?->name }}</td><td>{{ $employee->position }}</td><td>{{ $employee->user?->email ?: '-' }}</td><td>{{ $employee->whatsapp_number }}</td><td><x-metronic.status-badge :status="$employee->status->value" :label="$employee->status->label()" /></td>
                    <td class="text-end">@can('attendance.update')<a href="{{ route('attendance.employees.edit', $employee) }}" class="btn btn-sm btn-light">Edit</a><form method="POST" action="{{ route('attendance.employees.deactivate', $employee) }}" class="d-inline">@csrf @method('PATCH')<button class="btn btn-sm btn-light-danger">Nonaktif</button></form>@endcan</td>
                </tr>
            @empty
                <tr><td colspan="8"><x-metronic.empty-state title="Belum ada karyawan" description="Tambahkan karyawan untuk mengaktifkan jadwal dan absensi." /></td></tr>
            @endforelse
        </tbody></table></div>
        {{ $employees->links() }}
    </x-metronic.card>
@endsection
