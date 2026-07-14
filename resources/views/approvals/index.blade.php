@extends('layouts.metronic.app')

@section('title', 'Kotak Masuk Approval')
@section('page_title', 'Kotak Masuk Approval')

@section('content')
    <x-metronic.page-title title="Kotak Masuk Approval" description="APP-01 approval sensitif lintas modul: harga, stok, void, retur, kredit, absensi, dan tindakan berisiko." />
    <x-metronic.card title="Filter Approval">
        <form method="GET" class="row g-3 mb-5">
            <div class="col-md-3"><select name="status" class="form-select"><option value="">Semua status</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
            <div class="col-md-3"><input name="module" value="{{ request('module') }}" class="form-control" placeholder="Modul"></div>
            <div class="col-md-3"><select name="risk_level" class="form-select"><option value="">Semua risiko</option>@foreach(['normal','medium','high','critical'] as $risk)<option value="{{ $risk }}" @selected(request('risk_level') === $risk)>{{ ucfirst($risk) }}</option>@endforeach</select></div>
            <div class="col-md-3"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Jenis</th><th>Requester</th><th>Nilai Risiko</th><th>Lokasi</th><th>SLA</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
            @forelse($approvals as $approval)
                <tr>
                    <td class="fw-bold">{{ str_replace('_', ' ', $approval->approval_type) }}<div class="text-muted">{{ $approval->module }} · {{ class_basename($approval->subject_type) }} #{{ $approval->subject_id }}</div></td>
                    <td>{{ $approval->requester?->name ?: '-' }}<div class="text-muted">{{ $approval->created_at?->format('d/m/Y H:i') }}</div></td>
                    <td>{{ \App\Support\CurrencyFormatter::rupiah((string) $approval->risk_value) }}<div><span class="badge badge-light-{{ $approval->risk_level === 'critical' ? 'danger' : ($approval->risk_level === 'high' ? 'warning' : 'info') }}">{{ $approval->risk_level }}</span></div></td>
                    <td>{{ $approval->workLocation?->name ?: 'Global' }}</td>
                    <td>{{ $approval->expires_at?->diffForHumans() ?: '-' }}</td>
                    <td><x-metronic.status-badge :status="$approval->current_status->value" :label="$approval->current_status->label()" /></td>
                    <td class="text-end"><a href="{{ route('approvals.show', $approval) }}" class="btn btn-sm btn-light">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="7"><x-metronic.empty-state title="Tidak ada approval" description="Permintaan approval sensitif akan muncul di sini." /></td></tr>
            @endforelse
        </tbody></table></div>
        {{ $approvals->links() }}
    </x-metronic.card>
@endsection
