<?php

namespace App\Models;

use App\Enums\NotificationChannelType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationRecipient extends Model
{
    protected $fillable = [
        'name',
        'recipient_type',
        'user_id',
        'role_name',
        'work_location_id',
        'channel_type',
        'destination',
        'report_type',
        'quiet_hours_start',
        'quiet_hours_end',
        'is_verified',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'channel_type' => NotificationChannelType::class,
            'quiet_hours_start' => 'datetime:H:i',
            'quiet_hours_end' => 'datetime:H:i',
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function type(): NotificationChannelType
    {
        $value = $this->getAttribute('channel_type');

        return $value instanceof NotificationChannelType ? $value : NotificationChannelType::from((string) $value);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /** @return HasMany<NotificationLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}
