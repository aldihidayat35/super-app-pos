<?php

namespace App\Enums;

enum DailyReportStatus: string
{
    case GENERATED = 'generated';
    case SENDING = 'sending';
    case SENT = 'sent';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::GENERATED => 'Dibuat',
            self::SENDING => 'Mengirim',
            self::SENT => 'Terkirim',
            self::FAILED => 'Gagal',
        };
    }
}
