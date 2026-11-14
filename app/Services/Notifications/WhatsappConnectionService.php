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
        $connection = WhatsappConnection::query()->first();
        if (! $connection) {
            $connection = WhatsappConnection::query()->create([
                'session_id' => $this->gateway->sessionId(),
                'status' => 'disconnected',
            ]);
        }

        return $connection;
    }

    /** @return array{connection: WhatsappConnection, qr: ?string, qr_expires_at: ?string, configured: bool} */
    public function refresh(?User $actor = null): array
    {
        $connection = $this->current();

        if (! $this->gateway->configured()) {
            $connection->forceFill([
                'status' => 'disconnected',
                'last_checked_at' => now(),
                'last_error' => 'Gateway WhatsApp belum dikonfigurasi.',
            ])->save();

            return ['connection' => $connection, 'qr' => null, 'qr_expires_at' => null, 'configured' => false];
        }

        try {
            return $this->persist($this->gateway->status($connection->session_id, $connection->api_token), $actor);
        } catch (Throwable $exception) {
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
        $connection = $this->current();

        return $this->persist($this->gateway->connect($connection->session_id, $connection->api_token), $actor);
    }

    /** @return array{connection: WhatsappConnection, qr: ?string, qr_expires_at: ?string, configured: bool} */
    public function reconnect(User $actor): array
    {
        $connection = $this->current();

        return $this->persist($this->gateway->reconnect($connection->session_id, $connection->api_token), $actor);
    }

    /** @return array{connection: WhatsappConnection, qr: ?string, qr_expires_at: ?string, configured: bool} */
    public function disconnect(User $actor): array
    {
        $connection = $this->current();
        $payload = $this->gateway->disconnect($connection->session_id, $connection->api_token);
        $payload['status'] = 'logged_out';
        $payload['phone'] = null;
        $payload['name'] = null;
        $payload['connected_at'] = null;
        $result = $this->persist($payload, $actor, true);

        NotificationChannel::query()->where('channel_type', NotificationChannelType::WHATSAPP->value)
            ->update(['is_active' => false]);

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

        $fields = [
            'status' => $status,
            'phone_number' => $clearIdentity ? null : ($payload['phone'] ?? $connection->phone_number),
            'account_name' => $clearIdentity ? null : ($payload['name'] ?? $connection->account_name),
            'connected_at' => $clearIdentity ? null : ($payload['connected_at'] ?? ($status === 'connected' ? ($connection->connected_at ?? now()) : $connection->connected_at)),
            'last_checked_at' => now(),
            'last_error' => null,
            'last_action_by' => $actor?->id,
        ];

        if (! empty($payload['session_id'])) {
            $fields['session_id'] = $payload['session_id'];
        }
        if (! empty($payload['api_token'])) {
            $fields['api_token'] = $payload['api_token'];
        }

        $connection->forceFill($fields)->save();

        if ($status === 'connected') {
            $this->ensureNotificationChannel($actor, $connection);
        }

        return [
            'connection' => $connection->fresh(),
            'qr' => $status === 'qr_ready' ? ($payload['qr'] ?? null) : null,
            'qr_expires_at' => $payload['qr_expires_at'] ?? null,
            'configured' => true,
        ];
    }

    private function ensureNotificationChannel(?User $actor, WhatsappConnection $connection): void
    {
        $channel = NotificationChannel::query()->updateOrCreate(
            ['channel_type' => NotificationChannelType::WHATSAPP->value],
            [
                'name' => 'WhatsApp Perusahaan',
                'sender' => $connection->session_id,
                'endpoint' => rtrim((string) config('whatsapp.gateway.base_url'), '/'),
                'auth_type' => 'bearer',
                'credentials' => ['api_token' => $connection->api_token],
                'timeout_seconds' => (int) config('whatsapp.gateway.timeout', 15),
                'retry_attempts' => 3,
                'is_active' => true,
                'metadata' => [
                    'provider' => 'baileys_gateway',
                    'session_id' => $connection->session_id,
                    'api_token' => $connection->api_token,
                ],
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
