<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationSchedule extends Model
{
    protected $fillable = [
        'name',
        'schedule_key',
        'frequency',
        'run_time',
        'timezone',
        'report_type',
        'report_period',
        'template_id',
        'channel_types',
        'recipient_scope',
        'work_location_id',
        'is_active',
        'last_run_at',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'channel_types' => 'array',
            'recipient_scope' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<NotificationTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class);
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /** @return HasMany<DailyReport, $this> */
    public function dailyReports(): HasMany
    {
        return $this->hasMany(DailyReport::class, 'schedule_id');
    }
}
