@extends('layouts.metronic.app')

@section('title', 'Pengajuan Izin/Sakit/Cuti')
@section('page_title', 'Pengajuan Izin/Sakit/Cuti')

@section('content')
    <x-metronic.page-title title="Pengajuan Izin/Sakit/Cuti" description="HR-05 pengajuan dengan lampiran, alasan, pengganti shift opsional, dan approval." />
    <x-metronic.card title="Buat Pengajuan" class="mb-5">
        <form method="POST" action="{{ route('attendance.requests.store') }}" enctype="multipart/form-data" class="row g-3">@csrf
            <div class="col-md-2"><select name="type" class="form-select">@foreach($types as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select></div>
            <div class="col-md-3"><input type="datetime-local" name="start_at" class="form-control"></div>
            <div class="col-md-3"><input type="datetime-local" name="end_at" class="form-control"></div>
            <div class="col-md-4"><input type="file" name="proof" class="form-control"></div>
            <div class="col-md-10"><input name="reason" class="form-control" placeholder="Alasan/kontak selama izin"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Ajukan</button></div>
        </form>
    </x-metronic.card>
    <x-metronic.card title="Histori Pengajuan">
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Karyawan</th><th>Jenis</th><th>Periode</th><th>Alasan</th><th>Status</th><th class="text-end">Approval</th></tr></thead><tbody>
            @forelse($requests as $item)
                <tr><td>{{ $item->employee?->name }}</td><td>{{ $item->type->label() }}</td><td>{{ $item->start_at?->format('d/m/Y H:i') }} - {{ $item->end_at?->format('d/m/Y H:i') }}</td><td>{{ $item->reason }}</td><td><x-metronic.status-badge :status="$item->status->value" :label="$item->status->label()" /></td><td class="text-end">@can('attendance.approve') @if($item->status->value === 'pending')<form method="POST" action="{{ route('attendance.requests.approve', $item) }}" class="d-inline">@csrf<input type="hidden" name="approval_note" value="Disetujui dari daftar"><button class="btn btn-sm btn-light-primary">Approve</button></form><form method="POST" action="{{ route('attendance.requests.reject', $item) }}" class="d-inline">@csrf<input type="hidden" name="approval_note" value="Ditolak dari daftar"><button class="btn btn-sm btn-light-danger">Reject</button></form>@endif @endcan</td></tr>
            @empty
                <tr><td colspan="6"><x-metronic.empty-state title="Belum ada pengajuan" description="Pengajuan izin/sakit/cuti akan muncul di sini." /></td></tr>
            @endforelse
        </tbody></table></div>
        {{ $requests->links() }}
    </x-metronic.card>
@endsection
