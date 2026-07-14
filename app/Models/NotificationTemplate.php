<?php

namespace App\Models;

use App\Enums\NotificationChannelType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'key',
        'name',
        'channel_type',
        'subject',
        'body',
        'fallback_body',
        'allowed_variables',
        'version',
        'is_active',
        'history',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'channel_type' => NotificationChannelType::class,
            'allowed_variables' => 'array',
            'history' => 'array',
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<NotificationLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}
