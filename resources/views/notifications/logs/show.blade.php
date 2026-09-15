@extends('layouts.metronic.app')

@section('title', 'Detail Pesan')
@section('page_title', 'Detail Pesan')

@section('content')
    <x-metronic.page-title title="Detail Pesan #{{ $log->id }}" description="Informasi lengkap penerima, isi pesan, proses antrean, dan hasil pengiriman.">
        <a href="{{ route('admin.notifications.logs.index', ['channel_type' => $log->type()->value]) }}" class="btn btn-light-primary">Kembali ke Riwayat</a>
    </x-metronic.page-title>

    <div class="row g-5">
        <div class="col-xl-8">
            <x-metronic.card title="Isi Pesan">
                <div class="row g-5 mb-6">
                    <div class="col-md-6">
                        <div class="text-muted fs-8 text-uppercase">Jenis Notifikasi</div>
                        <div class="fw-bold fs-5">{{ $notificationType }}</div>
                        @if($notificationTypeKey)<div class="text-muted">Kode: {{ $notificationTypeKey }}</div>@endif
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted fs-8 text-uppercase">Penerima</div>
                        <div class="fw-bold fs-5">{{ $log->recipientUser?->name ?? $log->recipient_name ?? 'Nomor eksternal' }}</div>
                        <div class="text-muted">{{ $log->destination }}</div>
                    </div>
                    @if($log->subject)
                        <div class="col-12"><div class="text-muted fs-8 text-uppercase">Judul</div><div class="fw-semibold">{{ $log->subject }}</div></div>
                    @endif
                </div>
                <div class="border rounded bg-light p-5" style="white-space: pre-wrap; word-break: break-word;">{{ $log->body }}</div>
            </x-metronic.card>
        </div>

        <div class="col-xl-4">
            <x-metronic.card title="Status Pengiriman" class="h-100">
                <div class="mb-5"><x-metronic.status-badge :status="$log->deliveryStatus()->value" :label="$log->deliveryStatus()->label()" /></div>
                <dl class="row mb-0">
                    <dt class="col-6 text-muted">Channel</dt><dd class="col-6 text-end">{{ $log->type()->label() }}</dd>
                    <dt class="col-6 text-muted">Masuk antrean</dt><dd class="col-6 text-end">{{ $log->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') ?: '-' }}</dd>
                    <dt class="col-6 text-muted">Terjadwal</dt><dd class="col-6 text-end">{{ $log->scheduled_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') ?: '-' }}</dd>
                    <dt class="col-6 text-muted">Terkirim</dt><dd class="col-6 text-end">{{ $log->sent_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') ?: '-' }}</dd>
                    <dt class="col-6 text-muted">Percobaan</dt><dd class="col-6 text-end">{{ $log->attempts }}</dd>
                    <dt class="col-6 text-muted">Percobaan berikut</dt><dd class="col-6 text-end">{{ $log->next_retry_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') ?: '-' }}</dd>
                    <dt class="col-6 text-muted">ID WhatsApp</dt><dd class="col-6 text-end text-break">{{ $log->provider_message_id ?: '-' }}</dd>
                </dl>
                @if($log->error_message)
                    <div class="alert alert-danger mt-5 mb-0"><div class="fw-bold mb-1">Keterangan kegagalan</div>{{ $log->error_message }}</div>
                @endif
            </x-metronic.card>
        </div>
    </div>

    <x-metronic.card title="Informasi Sistem" class="mt-5">
        <div class="row g-5">
            <div class="col-md-4"><div class="text-muted">Dibuat oleh</div><div class="fw-semibold">{{ $log->creator?->name ?? 'Sistem otomatis' }}</div></div>
            <div class="col-md-4"><div class="text-muted">Channel pengirim</div><div class="fw-semibold">{{ $log->channel?->name ?? '-' }}</div></div>
            <div class="col-md-4"><div class="text-muted">Template</div><div class="fw-semibold">{{ $log->template?->name ?? $log->template_key ?? '-' }}</div></div>
        </div>

        @if($safePayload)
            <div class="mt-6"><div class="fw-bold mb-2">Data pemicu</div><pre class="bg-light rounded p-4 mb-0 text-break">{{ json_encode($safePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></div>
        @endif
        @if($safeResponse)
            <div class="mt-6"><div class="fw-bold mb-2">Respons gateway yang telah disanitasi</div><pre class="bg-light rounded p-4 mb-0 text-break">{{ json_encode($safeResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></div>
        @endif
    </x-metronic.card>
@endsection
