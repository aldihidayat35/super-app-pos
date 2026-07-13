<?php

namespace App\Enums;

use App\Contracts\StatusContract;

enum CustomerStatus: string implements StatusContract
{
    case PENDING_VERIFICATION = 'pending_verification';
    case ACTIVE = 'active';
    case FROZEN = 'frozen';
    case BLACKLISTED = 'blacklisted';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_VERIFICATION => 'Menunggu Verifikasi',
            self::ACTIVE => 'Aktif',
            self::FROZEN => 'Dibekukan',
            self::BLACKLISTED => 'Blacklist',
            self::INACTIVE => 'Nonaktif',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING_VERIFICATION => 'warning',
            self::ACTIVE => 'success',
            self::FROZEN => 'info',
            self::BLACKLISTED => 'danger',
            self::INACTIVE => 'secondary',
        };
    }

    public function isFinal(): bool
    {
        return false;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
