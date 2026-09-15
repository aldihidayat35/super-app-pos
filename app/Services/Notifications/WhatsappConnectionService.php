<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use App\Models\User;
use App\Models\WhatsappConnection;
use Illuminate\Http\Client\RequestException;
use Throwable;

class WhatsappConnectionService
{
    public function __construct(private readonly WhatsappGatewayClient $gateway) {}

    public function current(): WhatsappConnection
    {
        return WhatsappConnection::query()->firstOrCreate(
            ['session_id' => $this->gateway->sessionId()],
            ['status' => 'disconnected']
        );
    }

    /** @return array{connection: WhatsappConnection, qr: ?string, qr_expires_at: ?string, configured: bool} */
    public function refresh(?User $actor = null): array
    {
        if (! $this->gateway->configured()) {
            $connection = $this->current();
            $connection->forceFill(['status' => 'disconnected', 'last_checked_at' => now(), 'last_error' => 'Gateway WhatsApp belum dikonfigurasi.'])->save();

            return ['connection' => $connection, 'qr' => null, 'qr_expires_at' => null, 'configured' => false];
        }

        try {
            return $this->persist($this->gateway->status(), $actor);
        } catch (Throwable $exception) {
            $connection = $this->current();
            $connection->forceFill([
                'status' => 'disconnected',
                'last_checked_at' => now(),
                'last_error' => $this->safeError($exception),
                'last_action_by' => $actor?->id,
            ])->save();

            return ['connection' => $connection, 'qr' => null, 'qr_expires_at' => null, 'configured' => true];
        }
    }

    /** @return array{connection: WhatsappConnection, qr: ?string, qr_expires_at: ?string, configured: bool} */
    public function connect(User $actor): array
    {
        return $this->persist($this->gateway->connect(), $actor);
    }

    /** @return array{connection: WhatsappConnection, qr: ?string, qr_expires_at: ?string, configured: bool} */
    public function reconnect(User $actor): array
    {
        return $this->persist($this->gateway->reconnect(), $actor);
    }

    /** @return array{connection: WhatsappConnection, qr: ?string, qr_expires_at: ?string, configured: bool} */
    public function disconnect(User $actor): array
    {
        $payload = $this->gateway->disconnect();
        $payload['status'] = 'logged_out';
        $payload['phone'] = null;
        $payload['name'] = null;
        $payload['connected_at'] = null;
        $result = $this->persist($payload, $actor, true);
        NotificationChannel::query()->where('channel_type', NotificationChannelType::WHATSAPP->value)
            ->where('sender', $this->gateway->sessionId())->update(['is_active' => false]);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{connection: WhatsappConnection, qr: ?string, qr_expires_at: ?string, configured: bool}
     */
    private function persist(array $payload, ?User $actor, bool $clearIdentity = false): array
    {
        $connection = $this->current();
        $status = in_array($payload['status'] ?? null, ['disconnected', 'connecting', 'qr_ready', 'connected', 'reconnecting', 'logged_out'], true)
            ? $payload['status'] : 'disconnected';
        $connection->forceFill([
            'status' => $status,
            'phone_number' => $clearIdentity ? null : ($payload['phone'] ?? $connection->phone_number),
            'account_name' => $clearIdentity ? null : ($payload['name'] ?? $connection->account_name),
            'connected_at' => $clearIdentity ? null : ($payload['connected_at'] ?? ($status === 'connected' ? ($connection->connected_at ?? now()) : $connection->connected_at)),
            'last_checked_at' => now(),
            'last_error' => null,
            'last_action_by' => $actor?->id,
        ])->save();

        if ($status === 'connected') {
            $this->ensureNotificationChannel($actor);
        }

        return [
            'connection' => $connection->fresh(),
            'qr' => $status === 'qr_ready' ? ($payload['qr'] ?? null) : null,
            'qr_expires_at' => $payload['qr_expires_at'] ?? null,
            'configured' => true,
        ];
    }

    private function ensureNotificationChannel(?User $actor): void
    {
        $channel = NotificationChannel::query()->updateOrCreate(
            ['channel_type' => NotificationChannelType::WHATSAPP->value, 'sender' => $this->gateway->sessionId()],
            [
                'name' => 'WhatsApp Perusahaan',
                'endpoint' => rtrim((string) config('whatsapp.gateway.base_url'), '/'),
                'auth_type' => 'api_key',
                'credentials' => [],
                'timeout_seconds' => (int) config('whatsapp.gateway.timeout', 10),
                'retry_attempts' => 3,
                'is_active' => true,
                'metadata' => ['provider' => 'baileys_gateway'],
                'updated_by' => $actor?->id,
                'created_by' => $actor?->id,
            ]
        );
        NotificationChannel::query()->where('channel_type', NotificationChannelType::WHATSAPP->value)
            ->whereKeyNot($channel->id)->update(['is_active' => false]);
    }

    private function safeError(Throwable $exception): string
    {
        if ($exception instanceof RequestException) {
            return 'Gateway WhatsApp merespons HTTP '.$exception->response->status().'.';
        }

        return 'Gateway WhatsApp tidak dapat dihubungi.';
    }
}
