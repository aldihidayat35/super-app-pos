<?php

namespace App\Enums;

enum WorkChecklistFrequency: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'Harian',
            self::WEEKLY => 'Mingguan',
        };
    }
}
