<?php

namespace App\Enums;

use App\Contracts\StatusContract;

enum TaxPeriodStatus: string implements StatusContract
{
    case OPEN = 'open';
    case REVIEWED = 'reviewed';
    case APPROVED = 'approved';
    case REPORTED = 'reported';
    case PAID = 'paid';
    case LOCKED = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Terbuka',
            self::REVIEWED => 'Sudah direview',
            self::APPROVED => 'Disetujui',
            self::REPORTED => 'Sudah dilaporkan',
            self::PAID => 'Sudah dibayar',
            self::LOCKED => 'Dikunci',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::LOCKED;
    }
}
