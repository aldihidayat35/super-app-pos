<?php

namespace App\Models;

use App\Enums\NotificationChannelType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationChannel extends Model
{
    protected $fillable = [
        'name',
        'channel_type',
        'endpoint',
        'auth_type',
        'credentials',
        'sender',
        'default_destination',
        'timeout_seconds',
        'retry_attempts',
        'is_active',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'channel_type' => NotificationChannelType::class,
            'credentials' => 'encrypted:array',
            'metadata' => 'array',
            'timeout_seconds' => 'integer',
            'retry_attempts' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function type(): NotificationChannelType
    {
        $value = $this->getAttribute('channel_type');

        return $value instanceof NotificationChannelType ? $value : NotificationChannelType::from((string) $value);
    }

    /** @return array<string, mixed> */
    public function credentialData(): array
    {
        $credentials = $this->getAttribute('credentials');

        return is_array($credentials) ? $credentials : [];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<NotificationLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}
