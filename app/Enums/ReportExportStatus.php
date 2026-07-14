<?php

namespace App\Enums;

enum ReportExportStatus: string
{
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => 'Dalam Antrian',
            self::PROCESSING => 'Diproses',
            self::COMPLETED => 'Selesai',
            self::FAILED => 'Gagal',
            self::EXPIRED => 'Kedaluwarsa',
        };
    }
}
