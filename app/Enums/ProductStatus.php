<?php

namespace App\Enums;

use App\Contracts\StatusContract;

enum ProductStatus: string implements StatusContract
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case DISCONTINUED = 'discontinued';
    case PREORDER = 'preorder';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktif',
            self::INACTIVE => 'Nonaktif',
            self::DISCONTINUED => 'Discontinued',
            self::PREORDER => 'Preorder',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'secondary',
            self::DISCONTINUED => 'danger',
            self::PREORDER => 'warning',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::DISCONTINUED;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
