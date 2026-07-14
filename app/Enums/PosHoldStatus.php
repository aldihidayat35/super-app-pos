<?php

namespace App\Enums;

enum PosHoldStatus: string
{
    case HELD = 'held';
    case RESUMED = 'resumed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::HELD => 'Ditahan',
            self::RESUMED => 'Dilanjutkan',
            self::CANCELLED => 'Dibatalkan',
        };
    }
}
