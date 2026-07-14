@extends('layouts.metronic.app')

@section('title', 'Koreksi Absensi')
@section('page_title', 'Koreksi Absensi')

@section('content')
    <x-metronic.page-title title="Koreksi Absensi" description="HR-06 koreksi jam hadir/pulang dengan bukti, approval, dan audit tanpa menghapus rekaman asli." />
    <x-metronic.card title="Ajukan Koreksi" class="mb-5">
        <form method="POST" action="{{ route('attendance.corrections.store') }}" enctype="multipart/form-data" class="row g-3">@csrf
            <div class="col-md-4"><select name="attendance_id" class="form-select">@foreach($attendances as $attendance)<option value="{{ $attendance->id }}">{{ $attendance->employee?->name }} — {{ $attendance->attendance_date?->format('d/m/Y') }}</option>@endforeach</select></div>
            <div class="col-md-3"><input type="datetime-local" name="proposed_check_in_at" class="form-control"></div>
            <div class="col-md-3"><input type="datetime-local" name="proposed_check_out_at" class="form-control"></div>
            <div class="col-md-2"><input type="file" name="proof" class="form-control"></div>
            <div class="col-md-10"><input name="reason" class="form-control" placeholder="Alasan koreksi"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Ajukan</button></div>
        </form>
    </x-metronic.card>
    <x-metronic.card title="Daftar Koreksi">
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Karyawan</th><th>Lama</th><th>Usulan</th><th>Alasan</th><th>Status</th><th class="text-end">Approval</th></tr></thead><tbody>
            @forelse($corrections as $correction)
                <tr><td>{{ $correction->employee?->name }}</td><td>{{ $correction->old_check_in_at?->format('d/m H:i') }} - {{ $correction->old_check_out_at?->format('d/m H:i') }}</td><td>{{ $correction->proposed_check_in_at?->format('d/m H:i') }} - {{ $correction->proposed_check_out_at?->format('d/m H:i') }}</td><td>{{ $correction->reason }}</td><td><x-metronic.status-badge :status="$correction->status->value" :label="$correction->status->label()" /></td><td class="text-end">@can('attendance.approve') @if($correction->status->value === 'pending')<form method="POST" action="{{ route('attendance.corrections.approve', $correction) }}" class="d-inline">@csrf<input type="hidden" name="approval_note" value="Disetujui dari daftar"><button class="btn btn-sm btn-light-primary">Approve</button></form><form method="POST" action="{{ route('attendance.corrections.reject', $correction) }}" class="d-inline">@csrf<input type="hidden" name="approval_note" value="Ditolak dari daftar"><button class="btn btn-sm btn-light-danger">Reject</button></form>@endif @endcan</td></tr>
            @empty
                <tr><td colspan="6"><x-metronic.empty-state title="Belum ada koreksi" description="Koreksi absensi yang diajukan akan tampil di sini." /></td></tr>
            @endforelse
        </tbody></table></div>
        {{ $corrections->links() }}
    </x-metronic.card>
@endsection
