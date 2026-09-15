<?php

namespace App\Enums;

enum FinancialYearStatus: string
{
    case DRAFT = 'draft';
    case REVIEWED = 'reviewed';
    case APPROVED = 'approved';
    case LOCKED = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf', self::REVIEWED => 'Sudah diperiksa',
            self::APPROVED => 'Disetujui', self::LOCKED => 'Dikunci',
        };
    }
}
