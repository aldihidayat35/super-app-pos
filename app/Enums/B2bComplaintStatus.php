<?php

namespace App\Enums;

enum B2bComplaintStatus: string
{
    case SUBMITTED = 'submitted';
    case REVIEWING = 'reviewing';
    case RESOLVED = 'resolved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::SUBMITTED => 'Diajukan',
            self::REVIEWING => 'Diproses',
            self::RESOLVED => 'Selesai',
            self::REJECTED => 'Ditolak',
        };
    }
}
