<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationLogStatus;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\NotificationRecipient;
use App\Models\NotificationTemplate;
use App\Models\SecureReportToken;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class NotificationDispatchService
{
    public function __construct(private readonly WhatsappGatewayClient $whatsappGateway) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function queueLog(
        NotificationChannelType $channelType,
        string $destination,
        string $body,
        ?NotificationTemplate $template = null,
        ?NotificationRecipient $recipient = null,
        ?SecureReportToken $token = null,
        ?User $actor = null,
        array $payload = [],
        ?string $idempotencyKey = null,
        ?string $subject = null,
        ?User $recipientUser = null,
    ): NotificationLog {
        return DB::transaction(function () use ($channelType, $destination, $body, $template, $recipient, $token, $actor, $payload, $idempotencyKey, $subject, $recipientUser): NotificationLog {
            $templateKey = $template instanceof NotificationTemplate ? $template->key : 'manual';
            $tokenPart = $token instanceof SecureReportToken ? (string) $token->id : Str::uuid()->toString();
            $key = $idempotencyKey ?: hash('sha256', implode('|', [
                $channelType->value,
                $destination,
                $templateKey,
                $tokenPart,
            ]));

            $existing = NotificationLog::query()->where('idempotency_key', $key)->first();
            if ($existing instanceof NotificationLog) {
                return $existing;
            }

            $channel = NotificationChannel::query()
                ->where('channel_type', $channelType->value)
                ->where('is_active', true)
                ->latest('id')
                ->first();
            $resolvedUser = $recipientUser ?? $recipient?->user;
            if (! $resolvedUser instanceof User && $channelType === NotificationChannelType::WHATSAPP) {
                $resolvedUser = $this->resolveWhatsappRecipient($destination);
            }

            return NotificationLog::query()->create([
                'notification_channel_id' => $channel?->id,
                'notification_template_id' => $template?->id,
                'notification_recipient_id' => $recipient?->id,
                'recipient_user_id' => $resolvedUser?->id,
                'daily_report_id' => $token?->daily_report_id,
                'secure_report_token_id' => $token?->id,
                'channel_type' => $channelType->value,
                'template_key' => $template?->key,
                'recipient_name' => $resolvedUser instanceof User ? $resolvedUser->name : $recipient?->name,
                'destination' => $destination,
                'subject' => $subject,
                'body' => $body,
                'status' => NotificationLogStatus::QUEUED->value,
                'payload' => $this->redactArray($payload),
                'idempotency_key' => $key,
                'scheduled_at' => now('Asia/Jakarta'),
                'created_by' => $actor?->id,
            ]);
        });
    }

    private function resolveWhatsappRecipient(string $destination): ?User
    {
        try {
            $normalized = $this->whatsappGateway->normalizePhone($destination);
        } catch (Throwable) {
            return null;
        }

        return User::query()
            ->with('employee')
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNotNull('phone_number')
                    ->orWhereHas('employee', fn ($employee) => $employee->whereNotNull('whatsapp_number'));
            })
            ->get()
            ->first(function (User $user) use ($normalized): bool {
                $number = $user->employee?->whatsapp_number ?: $user->phone_number;
                if (! is_string($number) || $number === '') {
                    return false;
                }

                try {
                    return $this->whatsappGateway->normalizePhone($number) === $normalized;
                } catch (Throwable) {
                    return false;
                }
            });
    }

    public function send(NotificationLog $log): NotificationLog
    {
        $log->refresh();
        if (in_array($log->deliveryStatus(), [NotificationLogStatus::SENT, NotificationLogStatus::SKIPPED], true)) {
            return $log;
        }

        $logType = $log->type();
        $channel = $log->channel ?: NotificationChannel::query()
            ->where('channel_type', $logType->value)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (! $channel instanceof NotificationChannel || (bool) config('notifications.dry_run', true)) {
            $log->update([
                'status' => NotificationLogStatus::SKIPPED->value,
                'attempts' => $log->attempts + 1,
                'sent_at' => now('Asia/Jakarta'),
                'sanitized_response' => ['dry_run' => true, 'reason' => $channel ? 'dry_run' : 'channel_inactive'],
            ]);

            return $log;
        }

        try {
            $response = match ($logType) {
                NotificationChannelType::WHATSAPP => $this->sendWhatsapp($channel, $log),
                NotificationChannelType::TELEGRAM => $this->sendTelegram($channel, $log),
            };

            $log->update([
                'notification_channel_id' => $channel->id,
                'status' => NotificationLogStatus::SENT->value,
                'attempts' => $log->attempts + 1,
                'provider_message_id' => $response['message_id'] ?? $response['msg_id'] ?? null,
                'sanitized_response' => $this->redactArray($response),
                'sent_at' => now('Asia/Jakarta'),
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            $attempts = $log->attempts + 1;
            $maxAttempts = max(1, $channel->retry_attempts);
            $log->update([
                'notification_channel_id' => $channel->id,
                'status' => $attempts < $maxAttempts ? NotificationLogStatus::RETRY->value : NotificationLogStatus::FAILED->value,
                'attempts' => $attempts,
                'error_message' => Str::limit($exception->getMessage(), 1000),
                'next_retry_at' => $attempts < $maxAttempts ? now('Asia/Jakarta')->addMinutes(min(60, 2 ** $attempts)) : null,
            ]);
        }

        return $log->refresh();
    }

    /** @return array<string, mixed> */
    private function sendWhatsapp(NotificationChannel $channel, NotificationLog $log): array
    {
        if ($channel->provider() === 'baileys_gateway') {
            return $this->whatsappGateway->send($log->destination, $log->body);
        }

        $credentials = $channel->credentialData();
        $token = (string) ($credentials['token'] ?? config('notifications.whatsapp.token', ''));
        $endpoint = $channel->endpoint ?: (string) config('notifications.whatsapp.base_url', '');
        if ($endpoint === '') {
            throw new ConnectionException('Endpoint WhatsApp API belum dikonfigurasi.');
        }

        $request = Http::timeout($channel->timeout_seconds)
            ->acceptJson();

        if ($token !== '') {
            $request = $channel->auth_type === 'query'
                ? $request
                : $request->withToken($token);
        }

        $payload = [
            'sender' => $channel->sender,
            'to' => $log->destination,
            'message' => $log->body,
        ];

        $response = $request->post($endpoint, $channel->auth_type === 'query' && $token !== '' ? array_merge($payload, ['token' => $token]) : $payload);
        if ($response->failed()) {
            throw new ConnectionException('WhatsApp API gagal: HTTP '.$response->status());
        }

        return $this->responsePayload($response->json(), $response->status());
    }

    /** @return array<string, mixed> */
    private function sendTelegram(NotificationChannel $channel, NotificationLog $log): array
    {
        $credentials = $channel->credentialData();
        $token = (string) ($credentials['bot_token'] ?? config('notifications.telegram.bot_token', ''));
        if ($token === '') {
            throw new ConnectionException('Bot token Telegram belum dikonfigurasi.');
        }

        $response = Http::timeout($channel->timeout_seconds)
            ->acceptJson()
            ->post('https://api.telegram.org/bot'.$token.'/sendMessage', [
                'chat_id' => $log->destination,
                'text' => $log->body,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => false,
            ]);

        if ($response->failed()) {
            throw new ConnectionException('Telegram API gagal: HTTP '.$response->status());
        }

        return $this->responsePayload($response->json(), $response->status());
    }

    /**
     * @return array<string, mixed>
     */
    private function responsePayload(mixed $payload, int $status): array
    {
        return is_array($payload) ? $payload : ['status' => $status];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function redactArray(array $input): array
    {
        $redacted = [];
        foreach ($input as $key => $value) {
            $lower = strtolower((string) $key);
            $sensitive = $lower === 'key'
                || preg_match('/(^|_)(token|secret|password|api_key|access_key|private_key|credential|authorization|qr)($|_)/', $lower) === 1;
            if ($sensitive) {
                $redacted[$key] = '***redacted***';

                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redactArray($value) : $value;
        }

        return $redacted;
    }
}
