<?php

namespace App\Enums;

enum ProductPriceStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Aktif',
            self::INACTIVE => 'Nonaktif',
        };
    }
}
