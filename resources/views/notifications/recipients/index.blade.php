@extends('layouts.metronic.app')

@section('title', 'Penerima Notifikasi')
@section('page_title', 'Penerima Notifikasi')

@section('content')
    <x-metronic.page-title title="Penerima Notifikasi" description="NTF-04 penerima bertingkat per user/role/grup, nomor/chat ID, jenis laporan, lokasi, jam tenang, status, dan verifikasi." />

    <x-metronic.card title="Tambah Penerima">
        <form method="POST" action="{{ route('admin.notifications.recipients.store') }}" class="row g-3">
            @csrf
            <div class="col-md-3"><label class="form-label">Nama</label><input name="name" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Tipe</label><select name="recipient_type" class="form-select"><option value="user">User</option><option value="role">Role</option><option value="group">Grup</option></select></div>
            <div class="col-md-3"><label class="form-label">User</label><select name="user_id" class="form-select"><option value="">Opsional</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Role</label><select name="role_name" class="form-select"><option value="">Opsional</option>@foreach($roles as $role)<option value="{{ $role }}">{{ $role }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Lokasi</label><select name="work_location_id" class="form-select"><option value="">Semua</option>@foreach($workLocations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Channel</label><select name="channel_type" class="form-select">@foreach($types as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Nomor WA / Chat ID</label><input name="destination" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Jenis Laporan</label><select name="report_type" class="form-select"><option value="daily_report">Daily Report</option><option value="critical_stock">Stok Kritis</option><option value="receivable_due">Piutang Due</option><option value="pending_order">Order Tertunda</option><option value="approval">Approval</option></select></div>
            <div class="col-md-2"><label class="form-label">Jam Tenang Mulai</label><input name="quiet_hours_start" type="time" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Jam Tenang Akhir</label><input name="quiet_hours_end" type="time" class="form-control"></div>
            <div class="col-md-1"><label class="form-label">Verif</label><select name="is_verified" class="form-select"><option value="1">Ya</option><option value="0">Tidak</option></select></div>
            <div class="col-md-1"><label class="form-label">Status</label><select name="is_active" class="form-select"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
            <div class="col-12"><button class="btn btn-primary">Simpan Penerima</button></div>
        </form>
    </x-metronic.card>

    <x-metronic.card title="Daftar Penerima" class="mt-5">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Penerima</th><th>Scope</th><th>Channel</th><th>Tujuan</th><th>Laporan</th><th>Jam Tenang</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($recipients as $recipient)
                    <tr>
                        <td>{{ $recipient->name }}<div class="text-muted">{{ $recipient->recipient_type }} {{ $recipient->user?->email }}</div></td>
                        <td>{{ $recipient->role_name ?: '-' }}<div class="text-muted">{{ $recipient->workLocation?->name ?: 'Semua lokasi' }}</div></td>
                        <td>{{ $recipient->channel_type->label() }}</td>
                        <td>{{ $recipient->destination }}</td>
                        <td>{{ $recipient->report_type }}</td>
                        <td>{{ $recipient->quiet_hours_start?->format('H:i') ?: '-' }} - {{ $recipient->quiet_hours_end?->format('H:i') ?: '-' }}</td>
                        <td><x-metronic.status-badge :status="$recipient->is_active ? 'active' : 'inactive'" :label="$recipient->is_active ? ($recipient->is_verified ? 'Aktif Verified' : 'Aktif Belum Verified') : 'Nonaktif'" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-metronic.empty-state title="Belum ada penerima" description="Tambahkan owner, kepala gudang, kepala toko, atau grup penerima." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $recipients->links() }}
    </x-metronic.card>
@endsection
