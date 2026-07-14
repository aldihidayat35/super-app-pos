<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class SecureReportToken extends Model
{
    protected $fillable = [
        'daily_report_id',
        'token_hash',
        'recipient_destination',
        'recipient_id',
        'user_id',
        'scope',
        'expires_at',
        'read_at',
        'revoked_at',
        'one_time',
        'access_count',
    ];

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'expires_at' => 'datetime',
            'read_at' => 'datetime',
            'revoked_at' => 'datetime',
            'one_time' => 'boolean',
            'access_count' => 'integer',
        ];
    }

    /** @return BelongsTo<DailyReport, $this> */
    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }

    /** @return BelongsTo<NotificationRecipient, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(NotificationRecipient::class);
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && Carbon::parse($this->getAttribute('expires_at'), 'Asia/Jakarta')->isFuture()
            && (! $this->one_time || $this->read_at === null);
    }
}
