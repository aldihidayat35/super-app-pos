<?php

namespace App\Enums;

enum AnomalyStatus: string
{
    case OPEN = 'open';
    case REVIEWED = 'reviewed';
    case RESOLVED = 'resolved';
    case FALSE_POSITIVE = 'false_positive';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::REVIEWED => 'Ditinjau',
            self::RESOLVED => 'Diselesaikan',
            self::FALSE_POSITIVE => 'False Positive',
        };
    }
}
