@extends('layouts.metronic.app')

@section('title', 'Koneksi WhatsApp')
@section('page_title', 'Koneksi WhatsApp')

@section('content')
@php
    $initial = ['configured' => $configured, 'status' => $connection->status, 'phone' => $connection->phone_number, 'name' => $connection->account_name, 'connected_at' => $connection->connected_at?->toIso8601String(), 'last_checked_at' => $connection->last_checked_at?->toIso8601String(), 'last_error' => $connection->last_error, 'qr' => $qr, 'qr_expires_at' => $qr_expires_at, 'messages' => $messages];
    $statusLabel = match ($connection->status) {
        'connected' => 'Terhubung',
        'connecting' => 'Sedang menghubungkan',
        'qr_ready' => 'QR siap dipindai',
        'reconnecting' => 'Sedang menyambungkan ulang',
        'logged_out' => 'Sesi telah diputus',
        default => 'Belum terhubung',
    };
@endphp
<x-metronic.page-title title="Koneksi WhatsApp Perusahaan" description="Hubungkan satu nomor WhatsApp perusahaan untuk laporan, alert, approval, dan notifikasi keluar aplikasi." />

<div data-whatsapp-connection data-status-url="{{ route('admin.whatsapp.status') }}" data-connect-url="{{ route('admin.whatsapp.connect') }}" data-disconnect-url="{{ route('admin.whatsapp.disconnect') }}" data-initial="{{ e(json_encode($initial, JSON_UNESCAPED_SLASHES)) }}" data-status="{{ $connection->status }}">
    <div class="row g-5">
        <div class="col-lg-7">
            <x-metronic.card title="Status Koneksi" class="h-100">
                <div class="d-flex align-items-center gap-4 mb-6">
                    <div class="symbol symbol-60px"><span class="symbol-label bg-light-success"><i class="ki-outline ki-whatsapp fs-1 text-success"></i></span></div>
                    <div><div class="text-muted fs-8 text-uppercase">Status saat ini</div><div class="fs-2 fw-bold" data-wa-status>{{ $statusLabel }}</div></div>
                </div>
                <div class="row g-4 mb-6">
                    <div class="col-sm-6"><div class="text-muted">Nomor WhatsApp</div><div class="fw-bold" data-wa-phone>{{ $connection->phone_number ?: '—' }}</div></div>
                    <div class="col-sm-6"><div class="text-muted">Nama akun</div><div class="fw-bold" data-wa-name>{{ $connection->account_name ?: '—' }}</div></div>
                </div>
                <div class="alert alert-danger d-none" data-wa-error></div>
                @can('whatsapp_connection.manage')
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn {{ $connection->status === 'connected' ? 'btn-light-danger' : 'btn-success' }}" data-wa-primary-action data-wa-action="{{ $connection->status === 'connected' ? 'disconnect' : 'connect' }}" data-url="{{ $connection->status === 'connected' ? route('admin.whatsapp.disconnect') : route('admin.whatsapp.connect') }}"><i class="ki-outline {{ $connection->status === 'connected' ? 'ki-disconnect' : 'ki-whatsapp' }}"></i> <span data-wa-primary-label>{{ $connection->status === 'connected' ? 'Putuskan WhatsApp' : 'Hubungkan WhatsApp' }}</span></button>
                    <button class="btn btn-light-primary d-none" data-wa-action="reconnect" data-url="{{ route('admin.whatsapp.reconnect') }}" data-show-status="connecting,qr_ready,reconnecting,disconnected">Minta QR Baru / Sambungkan Ulang</button>
                </div>
                @endcan
            </x-metronic.card>
        </div>
        <div class="col-lg-5">
            <x-metronic.card title="Pindai QR dari WhatsApp" class="h-100">
                <div class="text-center d-none" data-wa-qr-box><canvas data-wa-qr class="mw-100"></canvas><p class="text-muted mt-3 mb-0">Buka WhatsApp → Perangkat tertaut → Tautkan perangkat, lalu pindai kode ini.</p></div>
                <div data-show-status="connected" class="{{ $connection->status === 'connected' ? '' : 'd-none' }} text-center py-10"><i class="ki-outline ki-check-circle fs-4x text-success"></i><div class="fw-bold fs-4 mt-3">WhatsApp sudah terhubung</div></div>
                <div data-show-status="disconnected,logged_out" class="{{ in_array($connection->status, ['disconnected', 'logged_out'], true) ? '' : 'd-none' }} text-center py-10"><i class="ki-outline ki-qr-code fs-4x text-muted"></i><div class="text-muted mt-3">Klik Hubungkan WhatsApp untuk menampilkan QR.</div></div>
            </x-metronic.card>
        </div>
    </div>

    @can('whatsapp_connection.manage')
    <x-metronic.card title="Kirim Pesan Uji" class="mt-5 {{ $connection->status === 'connected' ? '' : 'd-none' }}" data-show-status="connected">
        <form method="POST" action="{{ route('admin.whatsapp.test') }}" class="row g-3">@csrf
            <div class="col-md-4"><label class="form-label">Nomor tujuan</label><input name="destination" class="form-control" placeholder="081234567890" required></div>
            <div class="col-md-6"><label class="form-label">Pesan</label><input name="message" value="Tes WhatsApp GudangToko berhasil." class="form-control" required maxlength="1000"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Kirim Uji</button></div>
        </form>
    </x-metronic.card>
    @endcan

    <x-metronic.card title="Riwayat Pengiriman WhatsApp" class="mt-5">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <p class="text-muted mb-0">Status diperbarui otomatis selama pesan masih dalam antrean atau sedang dicoba kembali.</p>
            @can('notifications.view')
                <a href="{{ route('admin.notifications.logs.index', ['channel_type' => 'whatsapp']) }}" class="btn btn-sm btn-light-primary">Lihat Semua Riwayat</a>
            @endcan
        </div>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle mb-0">
                <thead>
                    <tr class="text-muted fw-semibold"><th>Waktu</th><th>Penerima</th><th>Nomor Tujuan</th><th>Pesan</th><th>Status</th><th>Percobaan</th><th>ID WhatsApp / Keterangan</th></tr>
                </thead>
                <tbody data-wa-message-history>
                    @forelse($messages as $message)
                        <tr>
                            <td>{{ $message['created_at'] ?: '—' }}</td>
                            <td><div class="fw-semibold">{{ $message['recipient_name'] ?: 'Nomor eksternal' }}</div>@if($message['recipient_linked'])<span class="badge badge-light-primary mt-1">Akun sistem</span>@endif</td>
                            <td>{{ $message['destination'] ?: '—' }}</td>
                            <td>{{ $message['message'] ?: '—' }}</td>
                            <td><x-metronic.status-badge :status="$message['status']" :label="$message['status_label']" />@if($message['sent_at'])<div class="text-muted fs-8 mt-1">{{ $message['sent_at'] }}</div>@endif</td>
                            <td>{{ $message['attempts'] }}</td>
                            <td class="{{ $message['error'] ? 'text-danger' : 'text-muted' }}">{{ $message['error'] ?: ($message['provider_message_id'] ?: '—') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-8">Belum ada pesan WhatsApp yang dikirim.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-metronic.card>
</div>
@endsection
