<?php

namespace App\Services\Control;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogService
{
    /** @var list<string> */
    private array $sensitiveKeys = ['password', 'password_confirmation', 'token', 'api_key', 'secret', 'remember_token', 'proof', 'file', 'avatar', 'attachment'];

    /**
     * @param  array<array-key, mixed>  $oldValues
     * @param  array<array-key, mixed>  $newValues
     */
    public function record(
        string $event,
        string $module,
        ?User $actor = null,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $reason = null,
        ?Request $request = null,
        ?WorkLocation $location = null,
        string $severity = 'info',
        ?string $correlationId = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_user_id' => $actor?->id,
            'work_location_id' => $location?->id,
            'event' => $event,
            'module' => $module,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'route_name' => $request?->route()?->getName(),
            'http_method' => $request?->method(),
            'ip_address' => $request?->ip(),
            'user_agent_hash' => $request !== null ? sha1((string) $request->userAgent()) : null,
            'old_values' => $this->redact($oldValues),
            'new_values' => $this->redact($newValues),
            'reason' => $reason,
            'severity' => $severity,
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
            'occurred_at' => now(),
        ]);
    }

    /** @param array<array-key, mixed> $context */
    public function security(Request $request, string $event, ?User $user, array $context = [], string $severity = 'info'): AuditLog
    {
        return $this->record(
            event: $event,
            module: 'security',
            actor: $user,
            subject: $user,
            newValues: $context,
            reason: isset($context['reason']) ? (string) $context['reason'] : null,
            request: $request,
            severity: $severity,
        );
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public function redact(array $values): array
    {
        $redacted = [];
        foreach ($values as $key => $value) {
            $keyString = (string) $key;
            if ($this->isSensitive($keyString)) {
                $redacted[$keyString] = '[REDACTED]';

                continue;
            }
            $redacted[$keyString] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }

    private function isSensitive(string $key): bool
    {
        $normalized = Str::lower($key);

        return collect($this->sensitiveKeys)->contains(fn (string $sensitive): bool => str_contains($normalized, $sensitive));
    }
}
