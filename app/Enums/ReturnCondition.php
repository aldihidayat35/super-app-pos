<?php

namespace App\Enums;

enum ReturnCondition: string
{
    case GOOD = 'good';
    case DAMAGED = 'damaged';
    case BROKEN = 'broken';
    case EXPIRED = 'expired';
    case WRONG_ITEM = 'wrong_item';
    case LOST = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::GOOD => 'Layak Jual',
            self::DAMAGED => 'Rusak',
            self::BROKEN => 'Pecah',
            self::EXPIRED => 'Expired',
            self::WRONG_ITEM => 'Salah Barang',
            self::LOST => 'Hilang',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $condition): array => [$condition->value => $condition->label()])->all();
    }
}
