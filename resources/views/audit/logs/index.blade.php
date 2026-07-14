@extends('layouts.metronic.app')

@section('title', 'Audit Log')
@section('page_title', 'Audit Log')

@section('content')
    <x-metronic.page-title title="Audit Log" description="AUD-01 jejak audit read-only dengan before/after aman dan data sensitif sudah direduksi.">
        @can('audit.export')<a href="{{ route('audit-logs.export', request()->query()) }}" class="btn btn-light-primary">Export CSV</a>@endcan
    </x-metronic.page-title>
    <x-metronic.card title="Filter Audit">
        <form method="GET" class="row g-3 mb-5"><div class="col-md-2"><input name="module" value="{{ request('module') }}" class="form-control" placeholder="Module"></div><div class="col-md-3"><input name="event" value="{{ request('event') }}" class="form-control" placeholder="Event/action"></div><div class="col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div><div class="col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div><div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div></form>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Waktu</th><th>Actor</th><th>Module/Event</th><th>Subject</th><th>IP/Device</th><th>Severity</th><th class="text-end">Aksi</th></tr></thead><tbody>
            @forelse($logs as $log)
                <tr><td>{{ $log->occurred_at?->format('d/m/Y H:i:s') }}</td><td>{{ $log->actor?->email ?: '-' }}</td><td>{{ $log->module }}<div class="text-muted">{{ $log->event }}</div></td><td>{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</td><td>{{ $log->ip_address }}<div class="text-muted">{{ $log->user_agent_hash }}</div></td><td>{{ $log->severity }}</td><td class="text-end"><a href="{{ route('audit-logs.show', $log) }}" class="btn btn-sm btn-light">Detail</a></td></tr>
            @empty
                <tr><td colspan="7"><x-metronic.empty-state title="Belum ada audit" description="Audit akan tercatat saat aksi penting terjadi." /></td></tr>
            @endforelse
        </tbody></table></div>
        {{ $logs->links() }}
    </x-metronic.card>
@endsection
