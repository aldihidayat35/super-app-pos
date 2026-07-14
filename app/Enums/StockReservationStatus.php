<?php

namespace App\Enums;

use App\Contracts\StatusContract;

enum StockReservationStatus: string implements StatusContract
{
    case ACTIVE = 'active';
    case RELEASED = 'released';
    case CONVERTED = 'converted';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktif',
            self::RELEASED => 'Dilepas',
            self::CONVERTED => 'Dikonversi ke Issue',
            self::EXPIRED => 'Kedaluwarsa',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'primary',
            self::RELEASED => 'warning',
            self::CONVERTED => 'success',
            self::EXPIRED => 'danger',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::ACTIVE;
    }
}
