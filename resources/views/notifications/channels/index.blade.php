@extends('layouts.metronic.app')

@section('title', 'Channel Notifikasi')
@section('page_title', 'Channel WA API dan Telegram')

@section('content')
    <x-metronic.page-title title="Channel WA API dan Telegram" description="NTF-01 konfigurasi endpoint, secret terenkripsi, status aktif, retry, timeout, dan test message." />

    <x-metronic.card title="Tambah Channel">
        <form method="POST" action="{{ route('admin.notifications.channels.store') }}" class="row g-3">
            @csrf
            <div class="col-md-3"><label class="form-label">Nama</label><input name="name" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Tipe</label><select name="channel_type" class="form-select">@foreach($types as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Endpoint</label><input name="endpoint" type="url" class="form-control" placeholder="WA API endpoint"></div>
            <div class="col-md-2"><label class="form-label">Auth</label><select name="auth_type" class="form-select"><option value="bearer">Bearer</option><option value="query">Query Token</option><option value="none">Tanpa Auth</option></select></div>
            <div class="col-md-2"><label class="form-label">Status</label><select name="is_active" class="form-select"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
            <div class="col-md-3"><label class="form-label">Token/API Key WA</label><input name="token" type="password" class="form-control" autocomplete="new-password"></div>
            <div class="col-md-3"><label class="form-label">Bot Token Telegram</label><input name="bot_token" type="password" class="form-control" autocomplete="new-password"></div>
            <div class="col-md-2"><label class="form-label">Sender/Session</label><input name="sender" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Default Chat/No.</label><input name="default_destination" class="form-control"></div>
            <div class="col-md-1"><label class="form-label">Timeout</label><input name="timeout_seconds" value="10" type="number" class="form-control"></div>
            <div class="col-md-1"><label class="form-label">Retry</label><input name="retry_attempts" value="3" type="number" class="form-control"></div>
            <div class="col-12"><button class="btn btn-primary">Simpan Channel</button></div>
        </form>
    </x-metronic.card>

    <x-metronic.card title="Daftar Channel" class="mt-5">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Nama</th><th>Tipe</th><th>Endpoint/Sender</th><th>Secret</th><th>Timeout/Retry</th><th>Status</th><th>Test</th><th class="text-end">Update</th></tr></thead>
                <tbody>
                @forelse($channels as $channel)
                    <tr>
                        <td>{{ $channel->name }}<div class="text-muted">#{{ $channel->id }}</div></td>
                        <td>{{ $channel->channel_type->label() }}</td>
                        <td><div>{{ $channel->endpoint ?: '-' }}</div><span class="text-muted">{{ $channel->sender ?: $channel->default_destination }}</span></td>
                        <td><span class="badge badge-light">•••••••• terenkripsi</span></td>
                        <td>{{ $channel->timeout_seconds }}s / {{ $channel->retry_attempts }}x</td>
                        <td><x-metronic.status-badge :status="$channel->is_active ? 'active' : 'inactive'" :label="$channel->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                        <td>
                            <form method="POST" action="{{ route('admin.notifications.channels.test', $channel) }}" class="d-flex gap-2">
                                @csrf
                                <input name="name" value="{{ $channel->name }}" type="hidden"><input name="channel_type" value="{{ $channel->channel_type->value }}" type="hidden"><input name="endpoint" value="{{ $channel->endpoint }}" type="hidden"><input name="auth_type" value="{{ $channel->auth_type }}" type="hidden"><input name="sender" value="{{ $channel->sender }}" type="hidden"><input name="default_destination" value="{{ $channel->default_destination }}" type="hidden"><input name="timeout_seconds" value="{{ $channel->timeout_seconds }}" type="hidden"><input name="retry_attempts" value="{{ $channel->retry_attempts }}" type="hidden"><input name="is_active" value="{{ $channel->is_active ? 1 : 0 }}" type="hidden">
                                <input name="test_destination" class="form-control form-control-sm" placeholder="No/chat ID" value="{{ $channel->default_destination }}">
                                <button class="btn btn-sm btn-light-primary">Test</button>
                            </form>
                        </td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('admin.notifications.channels.update', $channel) }}" class="d-inline-flex gap-2">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $channel->name }}" type="hidden"><input name="channel_type" value="{{ $channel->channel_type->value }}" type="hidden"><input name="endpoint" value="{{ $channel->endpoint }}" type="hidden"><input name="auth_type" value="{{ $channel->auth_type }}" type="hidden"><input name="sender" value="{{ $channel->sender }}" type="hidden"><input name="default_destination" value="{{ $channel->default_destination }}" type="hidden"><input name="timeout_seconds" value="{{ $channel->timeout_seconds }}" type="hidden"><input name="retry_attempts" value="{{ $channel->retry_attempts }}" type="hidden">
                                <select name="is_active" class="form-select form-select-sm"><option value="1" @selected($channel->is_active)>Aktif</option><option value="0" @selected(! $channel->is_active)>Nonaktif</option></select>
                                <button class="btn btn-sm btn-light">Update</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-metronic.empty-state title="Belum ada channel" description="Tambahkan WA API atau Telegram untuk mulai mengirim notifikasi." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $channels->links() }}
    </x-metronic.card>
@endsection
