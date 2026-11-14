<?php

namespace App\Services\Notifications;

use App\Models\WhatsappConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsappGatewayClient
{
    /**
     * Ambil status sesi dari gateway Billey WA.
     *
     * @return array<string, mixed>
     */
    public function status(?string $sessionId = null, ?string $apiToken = null): array
    {
        if (empty($sessionId)) {
            $conn = $this->cachedConnection();
            $sessionId = $conn?->session_id ?: $this->sessionId();
            $apiToken = $apiToken ?: ($conn?->api_token ?: (string) config('whatsapp.gateway.api_key'));
        }

        // Jika memiliki apiToken dan sessionId berupa guest session
        if (filled($apiToken) && filled($sessionId) && str_starts_with($sessionId, 'guest_')) {
            $response = $this->request($apiToken)->get("/api/guest-session/{$sessionId}/status");

            if ($response->successful()) {
                $json = $response->json() ?? [];
                $info = $json['info'] ?? [];

                return [
                    'status' => (string) ($json['status'] ?? 'disconnected'),
                    'qr' => $json['qr'] ?? null,
                    'phone' => $info['phoneNumber'] ?? null,
                    'name' => $info['pushName'] ?? ($info['sessionName'] ?? null),
                    'connected_at' => $info['connectedAt'] ?? null,
                    'session_id' => $sessionId,
                    'api_token' => $apiToken,
                ];
            }

            if (in_array($response->status(), [401, 404, 410], true)) {
                return [
                    'status' => 'disconnected',
                    'session_id' => $sessionId,
                    'api_token' => $apiToken,
                ];
            }

            $response->throw();
        }

        // Cek sesi via /api/sessions (mencakup sesi manual/statis seperti 'wa joki')
        $response = $this->request($apiToken)->get('/api/sessions');
        if ($response->successful()) {
            $sessions = $response->json('sessions') ?? [];
            foreach ($sessions as $session) {
                if (($session['id'] ?? '') === $sessionId) {
                    return [
                        'status' => (string) ($session['status'] ?? 'disconnected'),
                        'qr' => null,
                        'phone' => $session['phoneNumber'] ?? null,
                        'name' => $session['name'] ?? null,
                        'connected_at' => null,
                        'session_id' => $sessionId,
                        'api_token' => $apiToken,
                    ];
                }
            }
        }

        return [
            'status' => 'disconnected',
            'session_id' => $sessionId,
            'api_token' => $apiToken,
        ];
    }

    /**
     * Buat sesi baru (guest session) dan dapatkan QR code.
     *
     * @return array<string, mixed>
     */
    public function connect(?string $currentSessionId = null, ?string $currentToken = null): array
    {
        $created = $this->createGuestSession();
        $sessionId = $created['session_id'];
        $apiToken = $created['api_token'];

        // Coba ambil status awal untuk mengambil QR jika sudah siap
        for ($i = 0; $i < 3; $i++) {
            usleep(500000); // 500ms
            $status = $this->status($sessionId, $apiToken);
            if (! empty($status['qr']) || ($status['status'] ?? '') === 'connected') {
                return $status;
            }
        }

        return [
            'status' => $created['status'] ?? 'connecting',
            'qr' => null,
            'session_id' => $sessionId,
            'api_token' => $apiToken,
        ];
    }

    /**
     * Meminta refresh QR atau sambungkan ulang sesi yang aktif.
     *
     * @return array<string, mixed>
     */
    public function reconnect(?string $sessionId = null, ?string $apiToken = null): array
    {
        if (empty($sessionId)) {
            $conn = $this->cachedConnection();
            $sessionId = $conn?->session_id;
            $apiToken = $apiToken ?: $conn?->api_token;
        }

        if (filled($sessionId) && filled($apiToken) && str_starts_with($sessionId, 'guest_')) {
            $response = $this->request($apiToken)->post("/api/guest-session/{$sessionId}/refresh-qr");

            if ($response->successful()) {
                usleep(600000); // 600ms

                return $this->status($sessionId, $apiToken);
            }
        }

        // Jika sesi lama sudah tidak valid/expired di gateway, buat sesi baru
        return $this->connect();
    }

    /**
     * Putuskan sesi WhatsApp di gateway.
     *
     * @return array<string, mixed>
     */
    public function disconnect(?string $sessionId = null, ?string $apiToken = null): array
    {
        if (empty($sessionId)) {
            $conn = $this->cachedConnection();
            $sessionId = $conn?->session_id;
            $apiToken = $apiToken ?: $conn?->api_token;
        }

        if (filled($sessionId) && filled($apiToken) && str_starts_with($sessionId, 'guest_')) {
            $this->request($apiToken)->delete("/api/guest-session/{$sessionId}");
        }

        return [
            'status' => 'logged_out',
            'session_id' => $sessionId ?: $this->sessionId(),
            'phone' => null,
            'name' => null,
        ];
    }

    /**
     * Kirim pesan WhatsApp ke nomor tujuan melalui Billey WA.
     *
     * @return array<string, mixed>
     */
    public function send(string $destination, string $message, ?string $sessionId = null, ?string $apiToken = null): array
    {
        if (empty($sessionId) || empty($apiToken)) {
            $conn = $this->cachedConnection();
            $sessionId = $sessionId ?: ($conn?->session_id ?: $this->sessionId());
            $apiToken = $apiToken ?: ($conn?->api_token ?: (string) config('whatsapp.gateway.api_key'));
        }

        $response = $this->request($apiToken)->post('/api/send-message', [
            'session_id' => $sessionId,
            'to' => $this->normalizePhone($destination),
            'message' => $message,
        ]);

        if (! $response->successful()) {
            $errorMessage = $response->json('error')
                ?? $response->json('message')
                ?? 'Gateway WhatsApp merespons HTTP '.$response->status().'.';
            throw new RuntimeException($errorMessage);
        }

        return [
            'msg_id' => $response->json('msg_id')
                ?? ($response->json('messageId')
                ?? ($response->json('data.msg_id')
                ?? 'wa-'.uniqid())),
        ];
    }

    /**
     * Buat guest session baru di Billey WA gateway.
     *
     * @return array{session_id: string, api_token: string, status: string, info: array<string, mixed>}
     */
    public function createGuestSession(): array
    {
        $response = $this->request()->post('/api/guest-session/create')->throw();
        $json = $response->json() ?? [];

        return [
            'session_id' => (string) ($json['sessionId'] ?? ''),
            'api_token' => (string) ($json['apiToken'] ?? ''),
            'status' => (string) ($json['status'] ?? 'connecting'),
            'info' => $json['info'] ?? [],
        ];
    }

    public function normalizePhone(string $value): string
    {
        $number = preg_replace('/\D+/', '', trim($value)) ?? '';
        if (str_starts_with($number, '0')) {
            $number = '62'.substr($number, 1);
        }
        if (! preg_match('/^[1-9][0-9]{7,15}$/', $number)) {
            throw new RuntimeException('Nomor WhatsApp tujuan tidak valid.');
        }

        return $number;
    }

    public function configured(): bool
    {
        return (bool) config('whatsapp.gateway.enabled')
            && filled(config('whatsapp.gateway.base_url'));
    }

    public function sessionId(): string
    {
        return (string) config('whatsapp.gateway.session_id', 'gudangtoko-main');
    }

    private function cachedConnection(): ?WhatsappConnection
    {
        return WhatsappConnection::query()->where('status', 'connected')->first()
            ?? WhatsappConnection::query()->first();
    }

    private function request(?string $token = null): PendingRequest
    {
        if (! $this->configured()) {
            throw new ConnectionException('Gateway WhatsApp belum dikonfigurasi.');
        }

        $request = Http::baseUrl(rtrim((string) config('whatsapp.gateway.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('whatsapp.gateway.timeout', 15));

        $bearer = $token ?: (string) config('whatsapp.gateway.api_key');
        if (filled($bearer)) {
            $request = $request->withToken($bearer)->withHeaders(['X-Api-Key' => $bearer]);
        }

        return $request;
    }
}
