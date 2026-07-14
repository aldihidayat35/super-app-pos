<?php

namespace App\Enums;

enum CreditLimitStatus: string
{
    case ACTIVE = 'active';
    case BLOCKED = 'blocked';
    case PENDING_APPROVAL = 'pending_approval';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktif',
            self::BLOCKED => 'Diblokir',
            self::PENDING_APPROVAL => 'Menunggu Approval',
        };
    }
}
