<?php

namespace App\Models;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationLogStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'notification_channel_id',
        'notification_template_id',
        'notification_recipient_id',
        'daily_report_id',
        'secure_report_token_id',
        'channel_type',
        'template_key',
        'recipient_name',
        'destination',
        'subject',
        'body',
        'status',
        'attempts',
        'provider_message_id',
        'error_message',
        'payload',
        'sanitized_response',
        'idempotency_key',
        'scheduled_at',
        'sent_at',
        'next_retry_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'channel_type' => NotificationChannelType::class,
            'status' => NotificationLogStatus::class,
            'attempts' => 'integer',
            'payload' => 'array',
            'sanitized_response' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    public function type(): NotificationChannelType
    {
        $value = $this->getAttribute('channel_type');

        return $value instanceof NotificationChannelType ? $value : NotificationChannelType::from((string) $value);
    }

    public function deliveryStatus(): NotificationLogStatus
    {
        $value = $this->getAttribute('status');

        return $value instanceof NotificationLogStatus ? $value : NotificationLogStatus::from((string) $value);
    }

    /** @return BelongsTo<NotificationChannel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(NotificationChannel::class, 'notification_channel_id');
    }

    /** @return BelongsTo<NotificationTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    /** @return BelongsTo<NotificationRecipient, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(NotificationRecipient::class, 'notification_recipient_id');
    }

    /** @return BelongsTo<DailyReport, $this> */
    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }

    /** @return BelongsTo<SecureReportToken, $this> */
    public function secureToken(): BelongsTo
    {
        return $this->belongsTo(SecureReportToken::class, 'secure_report_token_id');
    }
}
