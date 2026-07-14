@extends('layouts.metronic.app')

@section('title', 'Dashboard Anomali')
@section('page_title', 'Dashboard Anomali')

@section('content')
    <x-metronic.page-title title="Dashboard Anomali" description="AUD-02 alert rule-based yang dapat dijelaskan dan tidak mengubah data otomatis." />
    <x-metronic.card title="Alert Anomali">
        <form method="GET" class="row g-3 mb-5"><div class="col-md-3"><select name="status" class="form-select"><option value="">Semua status</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div><div class="col-md-3"><input name="rule_key" value="{{ request('rule_key') }}" class="form-control" placeholder="Rule"></div><div class="col-md-2"><select name="severity" class="form-select"><option value="">Severity</option>@foreach(['low','medium','high','critical'] as $severity)<option value="{{ $severity }}" @selected(request('severity') === $severity)>{{ ucfirst($severity) }}</option>@endforeach</select></div><div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div></form>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Alert</th><th>Subject</th><th>Severity</th><th>Nilai Risiko</th><th>Status</th><th>Evidence</th><th class="text-end">Resolve</th></tr></thead><tbody>
            @forelse($alerts as $alert)
                <tr><td class="fw-bold">{{ $alert->title }}<div class="text-muted">{{ $alert->rule_key }} · {{ $alert->detected_at?->format('d/m/Y H:i') }}</div><div>{{ $alert->description }}</div></td><td>{{ class_basename($alert->subject_type) }} #{{ $alert->subject_id }}</td><td><span class="badge badge-light-warning">{{ $alert->severity }}</span></td><td>{{ \App\Support\CurrencyFormatter::rupiah((string) $alert->risk_value) }}</td><td><x-metronic.status-badge :status="$alert->status->value" :label="$alert->status->label()" /></td><td><pre class="small mb-0">{{ json_encode($alert->evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></td><td class="text-end">@can('audit.resolve')<form method="POST" action="{{ route('audit.anomalies.resolve', $alert) }}">@csrf<select name="status" class="form-select form-select-sm mb-2"><option value="reviewed">Ditinjau</option><option value="resolved">Resolved</option><option value="false_positive">False Positive</option></select><input name="resolution_note" class="form-control form-control-sm mb-2" placeholder="Catatan"><button class="btn btn-sm btn-primary">Simpan</button></form>@endcan</td></tr>
            @empty
                <tr><td colspan="7"><x-metronic.empty-state title="Tidak ada anomali" description="Alert akan muncul dari pricing, closing, overdue, void/retur, dan rule keamanan." /></td></tr>
            @endforelse
        </tbody></table></div>
        {{ $alerts->links() }}
    </x-metronic.card>
@endsection
