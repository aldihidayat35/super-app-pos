@extends('layouts.metronic.app')

@section('title', 'Log Pengiriman')
@section('page_title', 'Log Pengiriman')

@section('content')
    <x-metronic.page-title title="Log Pengiriman" description="NTF-05 audit delivery queued/sent/failed/retry, response tersanitasi, link report, dan retry manual." />

    <x-metronic.card title="Filter Log">
        <form method="GET" class="row g-3">
            <div class="col-md-3"><label class="form-label">Channel</label><select name="channel_type" class="form-select"><option value="">Semua</option>@foreach($types as $type)<option value="{{ $type->value }}" @selected(request('channel_type') === $type->value)>{{ $type->label() }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">Semua</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Penerima</label><input name="recipient" value="{{ request('recipient') }}" class="form-control"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Filter</button></div>
        </form>
    </x-metronic.card>

    <x-metronic.card title="Log Delivery" class="mt-5">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Waktu</th><th>Channel</th><th>Template</th><th>Penerima</th><th>Status</th><th>Attempt</th><th>Error/Response</th><th>Report</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ $log->created_at->format('d/m/Y H:i') }}<div class="text-muted">Sent: {{ $log->sent_at?->format('H:i') ?: '-' }}</div></td>
                        <td>{{ $log->channel_type->label() }}</td>
                        <td>{{ $log->template_key ?: '-' }}</td>
                        <td>{{ $log->recipient_name ?: '-' }}<div class="text-muted">{{ $log->destination }}</div></td>
                        <td><x-metronic.status-badge :status="$log->status" /></td>
                        <td>{{ $log->attempts }}</td>
                        <td class="text-muted">{{ $log->error_message ?: Str::limit(json_encode($log->sanitized_response, JSON_UNESCAPED_SLASHES), 90) }}</td>
                        <td>
                            @if($log->secureToken)
                                Token #{{ $log->secureToken->id }}<div class="text-muted">Exp {{ $log->secureToken->expires_at->format('d/m H:i') }}</div>
                                @if($log->secureToken->revoked_at)
                                    <span class="badge badge-light-danger">Revoked</span>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-end">
                            @if(in_array($log->status->value, ['failed', 'retry'], true))
                                <form method="POST" action="{{ route('admin.notifications.logs.retry', $log) }}" class="d-inline">@csrf <button class="btn btn-sm btn-light-primary">Retry</button></form>
                            @endif
                            @if($log->secureToken && ! $log->secureToken->revoked_at)
                                <form method="POST" action="{{ route('admin.notifications.report-tokens.revoke', $log->secureToken) }}" class="d-inline">@csrf <button class="btn btn-sm btn-light-danger">Revoke</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-metronic.empty-state title="Belum ada log" description="Test channel atau jalankan jadwal untuk membuat log delivery." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    </x-metronic.card>
@endsection
