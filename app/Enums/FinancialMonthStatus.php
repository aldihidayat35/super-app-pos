<?php

namespace App\Enums;

enum FinancialMonthStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case LOCKED = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf', self::SUBMITTED => 'Diajukan', self::LOCKED => 'Dikunci',
        };
    }
}
