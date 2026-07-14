<?php

namespace App\Enums;

use App\Contracts\StatusContract;

enum B2bCartStatus: string implements StatusContract
{
    case ACTIVE = 'active';
    case SUBMITTED = 'submitted';
    case ABANDONED = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktif',
            self::SUBMITTED => 'Diajukan',
            self::ABANDONED => 'Ditinggalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'primary',
            self::SUBMITTED => 'success',
            self::ABANDONED => 'secondary',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::ACTIVE;
    }
}
