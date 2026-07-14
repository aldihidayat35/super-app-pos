@extends('layouts.metronic.app')

@section('title', 'Log Login dan Keamanan')
@section('page_title', 'Log Login dan Keamanan')

@section('content')
    <x-metronic.page-title
        title="Log Login dan Keamanan"
        description="AUD-03 — Pantau login sukses/gagal, rate limit, reset password, session revoke, perubahan role, IP, device, dan pola anomali."
    />

    @if ($alerts !== [])
        <div class="mb-5">
            @foreach ($alerts as $alert)
                <div class="alert alert-{{ $alert['level'] }} d-flex align-items-start gap-3">
                    <i class="ki-outline ki-shield-cross fs-2"></i>
                    <div>
                        <div class="fw-bold">{{ $alert['title'] }}</div>
                        <div>{{ $alert['message'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="row g-5 mb-5">
        @foreach ([
            ['label' => 'Login Sukses', 'value' => $summary['login_success'], 'color' => 'success'],
            ['label' => 'Login Gagal', 'value' => $summary['login_failed'], 'color' => 'danger'],
            ['label' => 'Rate Limit', 'value' => $summary['rate_limited'], 'color' => 'warning'],
            ['label' => 'Reset Password', 'value' => $summary['password_reset'], 'color' => 'primary'],
            ['label' => 'Session Revoke', 'value' => $summary['session_revoked'], 'color' => 'info'],
            ['label' => 'Perubahan Role/User', 'value' => $summary['role_change'], 'color' => 'dark'],
        ] as $metric)
            <div class="col-md-4 col-xl-2">
                <x-metronic.card class="h-100">
                    <div class="text-gray-600 fs-7">{{ $metric['label'] }}</div>
                    <div class="fs-2hx fw-bold text-{{ $metric['color'] }}">{{ number_format($metric['value'], 0, ',', '.') }}</div>
                    <div class="text-muted fs-8">24 jam terakhir</div>
                </x-metronic.card>
            </div>
        @endforeach
    </div>

    <x-metronic.card title="Filter Audit Keamanan">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Event</label>
                <select name="event" class="form-select">
                    <option value="">Semua event</option>
                    @foreach ($events as $event)
                        <option value="{{ $event }}" @selected(request('event') === $event)>{{ $event }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Severity</label>
                <select name="severity" class="form-select">
                    <option value="">Semua severity</option>
                    @foreach ($severities as $severity)
                        <option value="{{ $severity }}" @selected(request('severity') === $severity)>{{ ucfirst($severity) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">User</label>
                <select name="user_id" class="form-select">
                    <option value="">Semua user</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }} — {{ $user->email }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">IP</label>
                <input type="text" name="ip_address" value="{{ request('ip_address') }}" class="form-control" placeholder="Contoh: 127.0.0.1">
            </div>
            <div class="col-md-2">
                <label class="form-label">Mulai</label>
                <input type="date" name="start_date" value="{{ request('start_date') }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sampai</label>
                <input type="date" name="end_date" value="{{ request('end_date') }}" class="form-control">
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button class="btn btn-light-primary">
                    <i class="ki-outline ki-filter fs-4"></i>Filter
                </button>
                <a href="{{ route('audit.security.index') }}" class="btn btn-light">Reset</a>
            </div>
        </form>
    </x-metronic.card>

    <x-metronic.card title="Log Keamanan" class="mt-5">
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead>
                    <tr class="text-gray-600 fw-bold">
                        <th>Waktu</th>
                        <th>User</th>
                        <th>Event</th>
                        <th>Severity</th>
                        <th>IP</th>
                        <th>Device Hash</th>
                        <th>Route</th>
                        <th>Konteks Aman</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="text-nowrap">{{ $log->occurred_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</td>
                            <td>
                                <div class="fw-semibold">{{ $log->actor?->name ?: '-' }}</div>
                                <div class="text-muted fs-8">{{ $log->actor?->email }}</div>
                            </td>
                            <td><code>{{ $log->event }}</code></td>
                            <td><x-metronic.status-badge :status="$log->severity" /></td>
                            <td>{{ $log->ip_address ?: '-' }}</td>
                            <td class="text-muted fs-8">{{ $log->user_agent_hash ?: '-' }}</td>
                            <td>
                                <div>{{ $log->route_name ?: '-' }}</div>
                                <div class="text-muted fs-8">{{ $log->http_method ?: '-' }}</div>
                            </td>
                            <td>
                                <pre class="small mb-0 text-gray-700">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                @if ($log->reason)
                                    <div class="text-muted fs-8 mt-2">Alasan: {{ $log->reason }}</div>
                                @endif
                                @if ($log->correlation_id)
                                    <div class="text-muted fs-8">Correlation: {{ $log->correlation_id }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-metronic.empty-state title="Belum ada log keamanan" description="Login, reset password, session, rate limit, dan perubahan role akan tampil di sini." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $logs->links() }}
    </x-metronic.card>
@endsection
