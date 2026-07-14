<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case PRESENT = 'present';
    case LATE = 'late';
    case EARLY_LEAVE = 'early_leave';
    case PERMISSION = 'permission';
    case SICK = 'sick';
    case ALPHA = 'alpha';
    case LEAVE = 'leave';
    case OVERTIME = 'overtime';

    public function label(): string
    {
        return match ($this) {
            self::PRESENT => 'Hadir',
            self::LATE => 'Terlambat',
            self::EARLY_LEAVE => 'Pulang Cepat',
            self::PERMISSION => 'Izin',
            self::SICK => 'Sakit',
            self::ALPHA => 'Alfa',
            self::LEAVE => 'Cuti',
            self::OVERTIME => 'Lembur',
        };
    }
}
