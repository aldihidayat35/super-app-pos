<?php

namespace App\Enums;

enum AttendanceRequestType: string
{
    case PERMISSION = 'permission';
    case SICK = 'sick';
    case LEAVE = 'leave';
    case OVERTIME = 'overtime';

    public function label(): string
    {
        return match ($this) {
            self::PERMISSION => 'Izin',
            self::SICK => 'Sakit',
            self::LEAVE => 'Cuti',
            self::OVERTIME => 'Lembur',
        };
    }
}
