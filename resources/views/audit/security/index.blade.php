@extends('layouts.metronic.app')

@section('title', 'Log Login dan Keamanan')
@section('page_title', 'Log Login dan Keamanan')

@section('content')
    <x-metronic.page-title title="Log Login dan Keamanan" description="AUD-03 login success/failed, rate limit, reset password, perubahan role, IP/device ringkas." />
    <x-metronic.card title="Log Keamanan">
        <form method="GET" class="row g-3 mb-5"><div class="col-md-4"><select name="event" class="form-select"><option value="">Semua event</option><option value="auth.login_success" @selected(request('event') === 'auth.login_success')>Login sukses</option><option value="auth.login_failed" @selected(request('event') === 'auth.login_failed')>Login gagal</option></select></div><div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div></form>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Waktu</th><th>User</th><th>Event</th><th>IP</th><th>Device Hash</th><th>Konteks Aman</th></tr></thead><tbody>
            @forelse($logs as $log)
                <tr><td>{{ $log->occurred_at?->format('d/m/Y H:i:s') }}</td><td>{{ $log->actor?->email ?: '-' }}</td><td>{{ $log->event }}</td><td>{{ $log->ip_address }}</td><td>{{ $log->user_agent_hash }}</td><td><pre class="small mb-0">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></td></tr>
            @empty
                <tr><td colspan="6"><x-metronic.empty-state title="Belum ada log keamanan" description="Login dan aksi keamanan akan tampil di sini." /></td></tr>
            @endforelse
        </tbody></table></div>
        {{ $logs->links() }}
    </x-metronic.card>
@endsection
