@extends('layouts.metronic.app')

@section('title', 'Master Shift')
@section('page_title', 'Master Shift')

@section('content')
    <x-metronic.page-title title="Master Shift" description="HR-02 shift pagi/siang/sore/malam, toleransi, lintas hari, break, dan status.">
        @can('attendance.update')<a href="{{ route('attendance.work-shifts.create') }}" class="btn btn-primary">Tambah Shift</a>@endcan
    </x-metronic.page-title>
    <x-metronic.card>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Kode</th><th>Nama</th><th>Lokasi</th><th>Jam</th><th>Toleransi</th><th>Break</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
            @forelse($shifts as $shift)
                <tr><td class="fw-bold">{{ $shift->code }}</td><td>{{ $shift->name }}</td><td>{{ $shift->workLocation?->name ?: 'Global' }}</td><td>{{ substr($shift->start_time,0,5) }} - {{ substr($shift->end_time,0,5) }} @if($shift->is_cross_midnight)<span class="badge badge-light-info">Lintas hari</span>@endif</td><td>Telat {{ $shift->tolerance_late_minutes }}m<br>Pulang {{ $shift->tolerance_early_leave_minutes }}m</td><td>{{ $shift->break_minutes }}m</td><td><span class="badge {{ $shift->is_active ? 'badge-light-success' : 'badge-light-danger' }}">{{ $shift->is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td class="text-end">@can('attendance.update')<a href="{{ route('attendance.work-shifts.edit', $shift) }}" class="btn btn-sm btn-light">Edit</a>@endcan</td></tr>
            @empty
                <tr><td colspan="8"><x-metronic.empty-state title="Belum ada shift" description="Tambahkan shift sebelum membuat jadwal karyawan." /></td></tr>
            @endforelse
        </tbody></table></div>
        {{ $shifts->links() }}
    </x-metronic.card>
@endsection
