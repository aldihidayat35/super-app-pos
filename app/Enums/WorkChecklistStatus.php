<?php

namespace App\Enums;

enum WorkChecklistStatus: string
{
    case OPEN = 'open';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Belum Selesai',
            self::COMPLETED => 'Selesai',
        };
    }
}
