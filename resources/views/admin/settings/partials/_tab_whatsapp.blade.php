@php
    $connection = $whatsappData['connection'] ?? new \App\Models\WhatsappConnection(['status' => 'disconnected']);
    $configured = $whatsappData['configured'] ?? false;
    $qr = $whatsappData['qr'] ?? null;
    $qr_expires_at = $whatsappData['qr_expires_at'] ?? null;
    $messages = $whatsappData['messages'] ?? collect();
    $initial = [
        'configured' => $configured,
        'status' => $connection->status,
        'phone' => $connection->phone_number,
        'name' => $connection->account_name,
        'connected_at' => $connection->connected_at?->toIso8601String(),
        'last_checked_at' => $connection->last_checked_at?->toIso8601String(),
        'last_error' => $connection->last_error,
        'qr' => $qr,
        'qr_expires_at' => $qr_expires_at,
        'messages' => $messages,
    ];
    $statusLabel = match ($connection->status) {
        'connected' => 'Terhubung',
        'connecting' => 'Sedang menghubungkan',
        'qr_ready' => 'QR siap dipindai',
        'reconnecting' => 'Sedang menyambungkan ulang',
        'logged_out' => 'Sesi telah diputus',
        default => 'Belum terhubung',
    };
@endphp

<div data-whatsapp-connection data-status-url="{{ route('admin.whatsapp.status') }}" data-connect-url="{{ route('admin.whatsapp.connect') }}" data-disconnect-url="{{ route('admin.whatsapp.disconnect') }}" data-initial="{{ e(json_encode($initial, JSON_UNESCAPED_SLASHES)) }}" data-status="{{ $connection->status }}">
    <div class="row g-5">
        <div class="col-lg-7">
            <x-metronic.card title="Status Koneksi WhatsApp" class="h-100">
                <div class="d-flex align-items-center gap-4 mb-6">
                    <div class="symbol symbol-60px">
                        <span class="symbol-label bg-light-success">
                            <i class="ki-outline ki-whatsapp fs-1 text-success"></i>
                        </span>
                    </div>
                    <div>
                        <div class="text-muted fs-8 text-uppercase">Status saat ini</div>
                        <div class="fs-2 fw-bold" data-wa-status>{{ $statusLabel }}</div>
                    </div>
                </div>
                <div class="row g-4 mb-6">
                    <div class="col-sm-6">
                        <div class="text-muted fs-8">Nomor WhatsApp</div>
                        <div class="fw-bold fs-7" data-wa-phone>{{ $connection->phone_number ?: '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted fs-8">Nama Akun</div>
                        <div class="fw-bold fs-7" data-wa-name>{{ $connection->account_name ?: '—' }}</div>
                    </div>
                </div>
                <div class="alert alert-danger d-none" data-wa-error></div>
                @can('whatsapp_connection.manage')
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn {{ $connection->status === 'connected' ? 'btn-light-danger' : 'btn-success' }}" data-wa-primary-action data-wa-action="{{ $connection->status === 'connected' ? 'disconnect' : 'connect' }}" data-url="{{ $connection->status === 'connected' ? route('admin.whatsapp.disconnect') : route('admin.whatsapp.connect') }}">
                            <i class="ki-outline {{ $connection->status === 'connected' ? 'ki-disconnect' : 'ki-whatsapp' }}"></i>
                            <span data-wa-primary-label>{{ $connection->status === 'connected' ? 'Putuskan WhatsApp' : 'Hubungkan WhatsApp' }}</span>
                        </button>
                        <button class="btn btn-light-primary d-none" data-wa-action="reconnect" data-url="{{ route('admin.whatsapp.reconnect') }}" data-show-status="connecting,qr_ready,reconnecting,disconnected">
                            Minta QR Baru / Sambungkan Ulang
                        </button>
                    </div>
                @endcan
            </x-metronic.card>
        </div>

        <div class="col-lg-5">
            <x-metronic.card title="Pindai QR dari WhatsApp" class="h-100">
                <div class="text-center d-none" data-wa-qr-box>
                    <canvas data-wa-qr class="mw-100"></canvas>
                    <p class="text-muted mt-3 mb-0 fs-8">Buka WhatsApp &rarr; Perangkat tertaut &rarr; Tautkan perangkat, lalu pindai kode ini.</p>
                </div>
                <div data-show-status="connected" class="{{ $connection->status === 'connected' ? '' : 'd-none' }} text-center py-10">
                    <i class="ki-outline ki-check-circle fs-4x text-success"></i>
                    <div class="fw-bold fs-5 mt-3">WhatsApp sudah terhubung</div>
                </div>
                <div data-show-status="disconnected,logged_out" class="{{ in_array($connection->status, ['disconnected', 'logged_out'], true) ? '' : 'd-none' }} text-center py-10">
                    <i class="ki-outline ki-qr-code fs-4x text-muted"></i>
                    <div class="text-muted mt-3 fs-8">Klik Hubungkan WhatsApp untuk menampilkan QR.</div>
                </div>
            </x-metronic.card>
        </div>
    </div>

    @can('whatsapp_connection.manage')
        <x-metronic.card title="Kirim Pesan Uji" class="mt-5 {{ $connection->status === 'connected' ? '' : 'd-none' }}" data-show-status="connected">
            <form method="POST" action="{{ route('admin.whatsapp.test') }}" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label fs-8 fw-semibold">Nomor tujuan</label>
                    <input name="destination" class="form-control form-control-sm form-control-solid" placeholder="081234567890" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fs-8 fw-semibold">Pesan</label>
                    <input name="message" value="Tes WhatsApp GudangToko berhasil." class="form-control form-control-sm form-control-solid" required maxlength="1000">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-sm btn-primary w-100">Kirim Uji</button>
                </div>
            </form>
        </x-metronic.card>
    @endcan

    <x-metronic.card title="Riwayat Pengiriman WhatsApp Terakhir" class="mt-5">
        <div class="table-responsive">
            <table class="table table-row-dashed table-hover align-middle gs-2 gy-3 mb-0" data-wa-messages>
                <thead>
                    <tr class="text-muted fw-bold text-uppercase fs-8 border-bottom border-gray-200">
                        <th>Tujuan</th>
                        <th>Status</th>
                        <th>Pesan</th>
                        <th>Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($messages as $log)
                        <tr>
                            <td class="fw-semibold fs-8">{{ $log->recipient_phone ?: ($log->recipient_address ?: '—') }}</td>
                            <td>
                                <span class="badge badge-light-{{ $log->status === 'sent' || $log->status === 'delivered' ? 'success' : 'secondary' }} fs-9">
                                    {{ ucfirst($log->status) }}
                                </span>
                            </td>
                            <td class="text-truncate max-w-300px fs-8">{{ $log->content_preview ?: '—' }}</td>
                            <td class="fs-9 text-muted">{{ $log->sent_at?->diffForHumans() ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-6 fs-8">Belum ada riwayat pengiriman pesan WhatsApp.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-metronic.card>
</div>
