<?php

namespace App\Enums;

enum NotificationLogStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case FAILED = 'failed';
    case RETRY = 'retry';
    case SKIPPED = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => 'Dalam Antrian',
            self::SENT => 'Terkirim',
            self::FAILED => 'Gagal',
            self::RETRY => 'Retry',
            self::SKIPPED => 'Dry-run/Skip',
        };
    }
}
