<?php

namespace App\Services\Notifications;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsappGatewayClient
{
    /** @return array<string, mixed> */
    public function status(): array
    {
        return $this->request()->get('/api/integrations/pos/session/status')->throw()->json('data') ?? [];
    }

    /** @return array<string, mixed> */
    public function connect(): array
    {
        return $this->request()->post('/api/integrations/pos/session/connect')->throw()->json('data') ?? [];
    }

    /** @return array<string, mixed> */
    public function reconnect(): array
    {
        return $this->request()->post('/api/integrations/pos/session/reconnect')->throw()->json('data') ?? [];
    }

    /** @return array<string, mixed> */
    public function disconnect(): array
    {
        return $this->request()->delete('/api/integrations/pos/session')->throw()->json('data') ?? [];
    }

    /** @return array<string, mixed> */
    public function send(string $destination, string $message): array
    {
        $response = $this->request()->post('/api/wa/send', [
            'session_id' => $this->sessionId(),
            'to' => $this->normalizePhone($destination),
            'message' => $message,
        ])->throw();

        return $response->json('data') ?? [];
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
            && filled(config('whatsapp.gateway.base_url'))
            && filled(config('whatsapp.gateway.api_key'));
    }

    public function sessionId(): string
    {
        return (string) config('whatsapp.gateway.session_id', 'gudangtoko-main');
    }

    private function request(): PendingRequest
    {
        if (! $this->configured()) {
            throw new ConnectionException('Gateway WhatsApp belum dikonfigurasi.');
        }

        return Http::baseUrl(rtrim((string) config('whatsapp.gateway.base_url'), '/'))
            ->withHeaders(['X-Api-Key' => (string) config('whatsapp.gateway.api_key')])
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('whatsapp.gateway.timeout', 10));
    }
}
