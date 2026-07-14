<?php

namespace App\Enums;

enum EmployeeScheduleStatus: string
{
    case SCHEDULED = 'scheduled';
    case DAY_OFF = 'day_off';
    case HOLIDAY = 'holiday';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Terjadwal',
            self::DAY_OFF => 'Libur',
            self::HOLIDAY => 'Hari Libur',
            self::CANCELLED => 'Dibatalkan',
        };
    }
}
