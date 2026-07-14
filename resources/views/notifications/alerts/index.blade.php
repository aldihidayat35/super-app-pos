@extends('layouts.metronic.app')

@section('title', 'Aturan Alert Bisnis')
@section('page_title', 'Aturan Alert Bisnis')

@section('content')
    <x-metronic.page-title title="Aturan Alert Bisnis" description="NTF-07 threshold stok, overdue, pending order, overpricing, margin, selisih, void, severity, cooldown, channel, status, dan preview." />

    @if(session('notification_preview'))
        <div class="alert alert-info"><strong>{{ session('notification_preview')['subject'] }}</strong><br>{{ session('notification_preview')['body'] }}</div>
    @endif

    <x-metronic.card title="Tambah Aturan Alert">
        <form method="POST" action="{{ route('admin.notifications.alerts.store') }}" class="row g-3">
            @csrf
            <div class="col-md-2"><label class="form-label">Rule Key</label><input name="rule_key" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Nama</label><input name="name" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Tipe Alert</label><select name="alert_type" class="form-select"><option value="critical_stock">Stok Kritis</option><option value="receivable_due">Piutang Due</option><option value="pending_order">Order Tertunda</option><option value="overpricing">Overpricing</option><option value="margin">Margin</option><option value="closing_difference">Selisih Closing</option><option value="void">Void</option></select></div>
            <div class="col-md-2"><label class="form-label">Severity</label><select name="severity" class="form-select"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="critical">Critical</option></select></div>
            <div class="col-md-1"><label class="form-label">Threshold</label><input name="threshold_value" type="number" step="0.0001" class="form-control"></div>
            <div class="col-md-1"><label class="form-label">Cooldown</label><input name="cooldown_minutes" type="number" value="60" class="form-control"></div>
            <div class="col-md-1"><label class="form-label">Status</label><select name="is_active" class="form-select"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
            <div class="col-md-3"><label class="form-label">Channel</label><select name="channel_types[]" class="form-select" multiple><option value="whatsapp" selected>WhatsApp</option><option value="telegram" selected>Telegram</option></select></div>
            <div class="col-12"><button class="btn btn-primary">Simpan Alert</button></div>
        </form>
    </x-metronic.card>

    <x-metronic.card title="Daftar Alert" class="mt-5">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Rule</th><th>Tipe</th><th>Severity</th><th>Threshold</th><th>Cooldown</th><th>Channel</th><th>Last Trigger</th><th>Status</th><th class="text-end">Preview</th></tr></thead>
                <tbody>
                @forelse($rules as $rule)
                    <tr>
                        <td>{{ $rule->name }}<div class="text-muted">{{ $rule->rule_key }}</div></td>
                        <td>{{ $rule->alert_type }}</td>
                        <td>{{ $rule->severity }}</td>
                        <td>{{ $rule->threshold_value ?: '-' }}</td>
                        <td>{{ $rule->cooldown_minutes }} menit</td>
                        <td>{{ implode(', ', $rule->channel_types ?? []) }}</td>
                        <td>{{ $rule->last_triggered_at?->format('d/m/Y H:i') ?: '-' }}</td>
                        <td><x-metronic.status-badge :status="$rule->is_active ? 'active' : 'inactive'" :label="$rule->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                        <td class="text-end"><form method="POST" action="{{ route('admin.notifications.alerts.preview', $rule) }}">@csrf <button class="btn btn-sm btn-light-info">Preview</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-metronic.empty-state title="Belum ada aturan alert" description="Tambahkan threshold untuk stok, piutang, order, margin, closing, atau void." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $rules->links() }}
    </x-metronic.card>
@endsection
