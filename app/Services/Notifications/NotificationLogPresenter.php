<?php

namespace App\Services\Notifications;

use App\Models\BusinessNotificationSetting;
use App\Models\NotificationLog;
use Illuminate\Support\Str;

class NotificationLogPresenter
{
    /** @var array<string, string>|null */
    private ?array $businessNotificationNames = null;

    public function typeKey(NotificationLog $log): ?string
    {
        $payload = $log->getAttribute('payload');
        $eventKey = is_array($payload) ? ($payload['event_key'] ?? null) : null;

        if (is_string($eventKey) && $eventKey !== '') {
            return $eventKey;
        }

        if (filled($log->template_key)) {
            return (string) $log->template_key;
        }

        $source = is_array($payload) ? ($payload['source'] ?? null) : null;

        return is_string($source) && $source !== '' ? $source : null;
    }

    public function typeLabel(NotificationLog $log): string
    {
        $key = $this->typeKey($log);

        if ($key === 'whatsapp_connection_test') {
            return 'Pesan Uji WhatsApp';
        }

        if ($key !== null && isset($this->businessNotificationNames()[$key])) {
            return $this->businessNotificationNames()[$key];
        }

        if ($log->template !== null) {
            return $log->template->name;
        }

        if (filled($log->subject)) {
            return (string) $log->subject;
        }

        return $key !== null ? Str::headline($key) : 'Pesan Manual';
    }

    /**
     * @param  array<array-key, mixed>|null  $metadata
     * @return array<array-key, mixed>|null
     */
    public function safeMetadata(?array $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        foreach ($metadata as $key => $value) {
            if (preg_match('/token|secret|password|api.?key|credential|\bqr\b/i', (string) $key) === 1) {
                $metadata[$key] = '[DISEMBUNYIKAN]';
            } elseif (is_array($value)) {
                $metadata[$key] = $this->safeMetadata($value);
            }
        }

        return $metadata;
    }

    /** @return array<array-key, mixed>|null */
    public function payload(NotificationLog $log): ?array
    {
        $payload = $log->getAttribute('payload');

        return is_array($payload) ? $payload : null;
    }

    /** @return array<array-key, mixed>|null */
    public function response(NotificationLog $log): ?array
    {
        $response = $log->getAttribute('sanitized_response');

        return is_array($response) ? $response : null;
    }

    /** @return array<string, string> */
    private function businessNotificationNames(): array
    {
        if ($this->businessNotificationNames === null) {
            $this->businessNotificationNames = BusinessNotificationSetting::query()
                ->pluck('name', 'event_key')
                ->mapWithKeys(fn (mixed $name, mixed $key): array => [(string) $key => (string) $name])
                ->all();
        }

        return $this->businessNotificationNames;
    }
}
