<?php

namespace App\Enums;

enum StaffBonusProgramStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';

    public function label(): string
    {
        return $this === self::DRAFT ? 'Draft' : 'Aktif';
    }
}
