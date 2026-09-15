@extends('layouts.metronic.app')

@php
    use App\Enums\AttendanceVerificationStatus;
    use App\Enums\WorkChecklistStatus;
    $answered = $dailyChecklist?->items?->where('status', '!=', \App\Enums\WorkChecklistItemStatus::PENDING)->count() ?? 0;
    $totalItems = $dailyChecklist?->items?->count() ?? 0;
    $checklistReady = $dailyChecklist?->status === WorkChecklistStatus::COMPLETED;
    $canManage = auth()->user()->can('attendance.view');
    $canActOnAttendance = function ($row): bool {
        if (auth()->id() === $row->user_id || ! auth()->user()->can('attendance.approve')) {
            return false;
        }
        $roles = $row->employee?->user?->roles?->pluck('name') ?? collect();
        return auth()->user()->hasAnyRole(['super_admin', 'owner_approver'])
            || (auth()->user()->hasRole('kepala_toko') && $row->workLocation?->type === 'branch' && $roles->intersect(['staf_toko', 'kasir', 'supervisor_shift'])->isNotEmpty())
            || (auth()->user()->hasRole('kepala_gudang') && $row->workLocation?->type === 'warehouse' && $roles->intersect(['staff_gudang', 'picker_packer'])->isNotEmpty());
    };
@endphp

@section('title', 'Kehadiran - '.config('app.name'))
@section('page_title', 'Kehadiran')
@section('page_description', 'Absensi sederhana berdasarkan akun, jadwal, dan lokasi kerja.')

@section('page_guide')
    <x-metronic.page-guide id="attendance-simple" title="Panduan Kehadiran">
        <x-slot:function><p>Catat jam masuk dan pulang memakai waktu server. Kepala lokasi memverifikasi absensi setelah pekerjaan selesai.</p></x-slot:function>
        <x-slot:workflow><ol><li>Tekan Absen Masuk.</li><li>Kerjakan tugas dan checklist harian.</li><li>Selesaikan shift kas bila Anda kasir.</li><li>Tekan Absen Pulang.</li><li>Kepala lokasi memverifikasi catatan.</li></ol></x-slot:workflow>
        <x-slot:warnings><div class="alert alert-warning mb-0">Jam pulang baru dapat dicatat setelah checklist harian selesai.</div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title title="Kehadiran" description="Satu halaman untuk absensi pribadi dan pengawasan tim.">
        <div class="d-flex flex-wrap gap-2">
            @if(auth()->user()->can('reports.view'))<a href="{{ route('reports.attendance.index') }}" class="btn btn-light-primary"><i class="ki-outline ki-chart fs-5"></i>Laporan Kehadiran</a>@endif
        </div>
    </x-metronic.page-title>

    <div class="card mb-6"><div class="card-body p-3">
        <div class="nav nav-tabs nav-tabs-custom gx-4">
            @can('attendance.check')
                <a href="#absensi-saya" class="nav-link active">
                    <span class="nav-link-inner"><i class="ki-duotone ki-user fs-2 me-2 text-primary"></i><span>Absensi Saya</span></span>
                </a>
            @endcan
            @if($canManage)
                <a href="#tim-hari-ini" class="nav-link">
                    <span class="nav-link-inner"><i class="ki-duotone ki-users fs-2 me-2"></i><span>Tim Hari Ini</span></span>
                </a>
                <a href="#persetujuan-absensi" class="nav-link">
                    <span class="nav-link-inner"><i class="ki-duotone ki-checkbox fs-2 me-2"></i><span>Persetujuan Absensi</span></span>
                </a>
            @endif
            @can('attendance.view')
                <a href="{{ route('attendance.schedules.index') }}" class="nav-link">
                    <span class="nav-link-inner"><i class="ki-duotone ki-calendar fs-2 me-2"></i><span>Jadwal Mingguan</span></span>
                </a>
            @endcan
            @if(auth()->user()->can('attendance.check') || auth()->user()->can('attendance.approve'))
                <a href="{{ route('attendance.requests.index') }}" class="nav-link">
                    <span class="nav-link-inner"><i class="ki-duotone ki-leave fs-2 me-2"></i><span>Pengajuan Izin</span></span>
                </a>
            @endif
            @if(auth()->user()->can('attendance.update') || auth()->user()->can('attendance.approve'))
                <a href="{{ route('attendance.corrections.index') }}" class="nav-link">
                    <span class="nav-link-inner"><i class="ki-duotone ki-reload fs-2 me-2"></i><span>Koreksi Absensi</span></span>
                </a>
            @endif
            @can('attendance.update')
                <a href="{{ route('attendance.employees.index') }}" class="nav-link">
                    <span class="nav-link-inner"><i class="ki-duotone ki-employee fs-2 me-2"></i><span>Pengaturan Karyawan</span></span>
                </a>
                <a href="{{ route('attendance.work-shifts.index') }}" class="nav-link">
                    <span class="nav-link-inner"><i class="ki-duotone ki-time fs-2 me-2"></i><span>Pengaturan Shift</span></span>
                </a>
            @endcan
        </div>
        <style>
            .nav-tabs-custom { border-bottom: 2px solid var(--kt-border-color, #e5e7eb); }
            .nav-tabs-custom .nav-link {
                border: none;
                color: var(--kt-text-color-2, #707898);
                position: relative;
                font-weight: 500;
                font-size: 0.9375rem;
                padding: 0.75rem 1rem;
                white-space: nowrap;
            }
            .nav-tabs-custom .nav-link .nav-link-inner { display: flex; align-items: center; gap: 0.5rem; }
            .nav-tabs-custom .nav-link i { display: inline-flex; font-size: 1.125rem; }
            .nav-tabs-custom .nav-link::before {
                content: '';
                position: absolute;
                bottom: -2px; left: 0; right: 0;
                height: 3px;
                border-radius: 3px 3px 0 0;
                background: transparent;
                transition: background 0.2s ease;
            }
            .nav-tabs-custom .nav-link:hover { color: var(--kt-text-color-1, #212529); }
            .nav-tabs-custom .nav-link:hover::before { background: var(--kt-primary, #06d79c); opacity: 0.4; }
            .nav-tabs-custom .nav-link.active { color: var(--kt-primary, #06d79c); font-weight: 700; }
            .nav-tabs-custom .nav-link.active i { color: var(--kt-primary, #06d79c); }
            .nav-tabs-custom .nav-link.active::before { background: var(--kt-primary, #06d79c); }
            .nav-tabs-custom .nav-link:focus { box-shadow: none; }
        </style>
    </div></div>

    @can('attendance.check')
        <div id="absensi-saya" class="card mb-6 overflow-hidden"><div class="card-body p-6 p-lg-8">
            @if(!$employee)
                <x-metronic.empty-state title="Akun belum terhubung ke karyawan" description="Minta kepala lokasi menghubungkan akun Anda pada Master Karyawan." />
            @else
                <div class="row align-items-center g-6">
                    <div class="col-lg-7">
                        <div class="text-muted fw-semibold mb-1">Waktu server Asia/Jakarta</div>
                        <div class="fs-2x fw-bold text-gray-900">{{ now()->translatedFormat('l, d F Y') }}</div>
                        <div class="fs-3 fw-semibold text-primary mt-1">{{ now()->format('H:i') }} WIB</div>
                        <div class="d-flex flex-wrap gap-3 mt-5">
                            <div class="bg-light rounded p-4 min-w-175px"><div class="text-muted fs-8">KARYAWAN</div><div class="fw-bold">{{ $employee->name }}</div></div>
                            <div class="bg-light rounded p-4 min-w-175px"><div class="text-muted fs-8">LOKASI</div><div class="fw-bold">{{ $openAttendance?->workLocation?->name ?? $schedule?->workLocation?->name ?? $employee->workLocation?->name ?? 'Belum ditentukan' }}</div></div>
                            <div class="bg-light rounded p-4 min-w-175px"><div class="text-muted fs-8">JADWAL</div><div class="fw-bold">{{ $schedule?->workShift?->name ?? 'Tidak ada jadwal aktif' }}</div>@if($schedule)<div class="text-muted fs-8">{{ $schedule->scheduled_start_at?->format('H:i') }}–{{ $schedule->scheduled_end_at?->format('H:i') }}</div>@endif</div>
                        </div>
                    </div>
                    <div class="col-lg-5"><div class="border rounded p-5 text-center bg-light-primary">
                        @if(!$openAttendance)
                            <i class="ki-outline ki-login fs-3x text-success"></i><h2 class="mt-3">Belum Absen Masuk</h2>
                            <p class="text-muted">Jam masuk dicatat saat tombol ditekan.</p>
                            <form method="POST" action="{{ route('attendance.check.in') }}">@csrf<button class="btn btn-success btn-lg w-100" @disabled(!$schedule)>Absen Masuk Sekarang</button></form>
                            @if(!$schedule)<div class="text-danger fs-8 mt-3">Tidak ada jadwal aktif. Hubungi kepala lokasi.</div>@endif
                        @else
                            <i class="ki-outline ki-time fs-3x text-primary"></i><h2 class="mt-3">Sedang Bekerja</h2>
                            <div class="fs-4 fw-bold">Masuk {{ $openAttendance->check_in_at->format('H:i') }} WIB</div>
                            <div class="my-4 text-start"><div class="d-flex justify-content-between"><span>Checklist harian</span><strong>{{ $answered }}/{{ $totalItems }}</strong></div><div class="progress h-6px mt-2"><div class="progress-bar" style="width: {{ $totalItems ? round($answered/$totalItems*100) : 0 }}%"></div></div></div>
                            @if($hasOpenCashShift)
                                <a href="{{ route('retail.shifts.current') }}" class="btn btn-warning btn-lg w-100">Selesaikan Closing Shift Kas</a>
                            @elseif(!$checklistReady)
                                <a href="{{ route('work-checklists.index') }}" class="btn btn-primary btn-lg w-100">Selesaikan Checklist Harian</a><div class="text-muted fs-8 mt-2">Setelah selesai, tombol Absen Pulang akan tersedia.</div>
                            @else
                                <form method="POST" action="{{ route('attendance.check.out') }}">@csrf<button class="btn btn-danger btn-lg w-100">Absen Pulang Sekarang</button></form>
                            @endif
                        @endif
                    </div></div>
                </div>
            @endif
        </div></div>

        <x-metronic.card title="Riwayat Saya" class="mb-6">
            <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted"><th>Tanggal</th><th>Lokasi</th><th>Masuk</th><th>Pulang</th><th>Hasil</th><th>Verifikasi</th></tr></thead><tbody>
                @forelse($recentAttendances as $row)<tr><td>{{ $row->attendance_date->format('d/m/Y') }}</td><td>{{ $row->workLocation?->name }}</td><td class="fw-bold">{{ $row->check_in_at?->format('H:i') ?? '—' }}</td><td class="fw-bold">{{ $row->check_out_at?->format('H:i') ?? '—' }}</td><td>{{ $row->status->label() }}</td><td><span class="badge badge-light-{{ $row->verification_status === AttendanceVerificationStatus::APPROVED ? 'success' : ($row->verification_status === AttendanceVerificationStatus::REJECTED ? 'danger' : 'warning') }}">{{ $row->verification_status->label() }}</span>@if($row->verification_note)<div class="text-muted fs-8 mt-1">{{ $row->verification_note }}</div>@endif</td></tr>
                @empty<tr><td colspan="6"><x-metronic.empty-state title="Belum ada riwayat" description="Riwayat muncul setelah Anda melakukan absensi." /></td></tr>@endforelse
            </tbody></table></div>
        </x-metronic.card>
    @endcan

    @if($canManage)
        <div class="row g-4 mb-6">@foreach([['Terjadwal',$teamSummary['scheduled'],'primary'],['Sudah Masuk',$teamSummary['checked_in'],'success'],['Terlambat',$teamSummary['late'],'warning'],['Belum Masuk',$teamSummary['not_checked_in'],'danger'],['Sudah Pulang',$teamSummary['checked_out'],'info'],['Menunggu Verifikasi',$teamSummary['pending_verification'],'danger']] as [$label,$value,$tone])<div class="col-xl-2 col-md-4 col-6"><div class="card border border-gray-200 h-100"><div class="card-body"><div class="text-muted fs-8">{{ strtoupper($label) }}</div><div class="fs-2 fw-bold text-{{ $tone }}">{{ $value }}</div></div></div></div>@endforeach</div>

        <div id="tim-hari-ini"><x-metronic.card title="Tim Hari Ini" class="mb-6"><div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr><th>Karyawan</th><th>Lokasi</th><th>Masuk</th><th>Pulang</th><th>Status</th><th class="text-end">Pulang Darurat</th></tr></thead><tbody>
            @forelse($teamAttendances as $row)<tr><td class="fw-bold">{{ $row->employee?->name }}</td><td>{{ $row->workLocation?->name }}</td><td>{{ $row->check_in_at?->format('H:i') ?? '—' }}</td><td>{{ $row->check_out_at?->format('H:i') ?? '—' }}</td><td>{{ $row->status->label() }}</td><td class="text-end">@if(!$row->check_out_at && $canActOnAttendance($row))<form method="POST" action="{{ route('attendance.supervisor.check-out',$row) }}" class="d-flex gap-2 justify-content-end">@csrf<input name="reason" class="form-control form-control-sm w-250px" required minlength="10" placeholder="Alasan gangguan operasional"><button class="btn btn-sm btn-light-warning">Catat Pulang</button></form>@endif</td></tr>
            @empty<tr><td colspan="6"><x-metronic.empty-state title="Belum ada staf yang masuk" description="Absensi tim hari ini akan tampil di sini." /></td></tr>@endforelse
        </tbody></table></div></x-metronic.card></div>

        <div id="persetujuan-absensi"><x-metronic.card title="Persetujuan Absensi"><div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr><th>Karyawan</th><th>Lokasi</th><th>Tanggal</th><th>Jam Kerja</th><th>Hasil</th><th>Checklist</th><th class="text-end">Keputusan</th></tr></thead><tbody>
            @forelse($pendingAttendances as $row)
                @php
                    $canApproveRow = $canActOnAttendance($row);
                    $checklist = $pendingChecklistSummaries->get($row->user_id.'|'.$row->work_location_id.'|'.$row->attendance_date->toDateString());
                @endphp
                <tr><td class="fw-bold">{{ $row->employee?->name }}</td><td>{{ $row->workLocation?->name }}</td><td>{{ $row->attendance_date->format('d/m/Y') }}</td><td>{{ $row->check_in_at?->format('H:i') }}–{{ $row->check_out_at?->format('H:i') }}<div class="text-muted fs-8">{{ intdiv($row->worked_minutes,60) }}j {{ $row->worked_minutes%60 }}m</div></td><td>{{ $row->status->label() }}</td><td>@if($checklist)<span class="badge badge-light-success">Selesai</span><div class="text-muted fs-8 mt-1">{{ $checklist->items->where('status', \App\Enums\WorkChecklistItemStatus::BLOCKED)->count() }} terkendala</div>@else<span class="badge badge-light-warning">Tidak ditemukan</span>@endif</td><td class="text-end">@if($canApproveRow)<div class="d-flex flex-column gap-2 align-items-end"><form method="POST" action="{{ route('attendance.records.approve',$row) }}">@csrf<button class="btn btn-sm btn-success">Setujui</button></form><form method="POST" action="{{ route('attendance.records.reject',$row) }}" class="d-flex gap-2">@csrf<input name="note" class="form-control form-control-sm w-200px" required placeholder="Alasan penolakan"><button class="btn btn-sm btn-light-danger">Tolak</button></form></div>@else<span class="text-muted fs-8">Menunggu pejabat berwenang</span>@endif</td></tr>
            @empty<tr><td colspan="7"><x-metronic.empty-state title="Tidak ada antrean" description="Semua absensi sudah diproses." /></td></tr>@endforelse
        </tbody></table></div></x-metronic.card></div>
    @endif
@endsection
