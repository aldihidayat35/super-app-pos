<?php

namespace App\Models;

use App\Enums\DailyReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class DailyReport extends Model
{
    protected $fillable = [
        'schedule_id',
        'report_date',
        'period_start',
        'period_end',
        'status',
        'filters',
        'summary',
        'rows',
        'definitions',
        'generated_at',
        'generated_by',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'status' => DailyReportStatus::class,
            'filters' => 'array',
            'summary' => 'array',
            'rows' => 'array',
            'definitions' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function reportDate(): Carbon
    {
        return Carbon::parse($this->getAttribute('report_date'), 'Asia/Jakarta');
    }

    /** @return array<string, mixed> */
    public function summaryData(): array
    {
        $summary = $this->getAttribute('summary');

        return is_array($summary) ? $summary : [];
    }

    /** @return BelongsTo<NotificationSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(NotificationSchedule::class);
    }

    /** @return BelongsTo<User, $this> */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /** @return HasMany<SecureReportToken, $this> */
    public function tokens(): HasMany
    {
        return $this->hasMany(SecureReportToken::class);
    }
}
