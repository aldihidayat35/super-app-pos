<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertRule extends Model
{
    protected $fillable = [
        'rule_key',
        'name',
        'alert_type',
        'severity',
        'threshold_value',
        'cooldown_minutes',
        'recipient_scope',
        'channel_types',
        'is_active',
        'last_triggered_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'threshold_value' => 'decimal:4',
            'cooldown_minutes' => 'integer',
            'recipient_scope' => 'array',
            'channel_types' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
