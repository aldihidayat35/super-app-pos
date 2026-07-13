<?php

namespace App\Enums;

enum StockOpnameReason: string
{
    case BROKEN = 'broken';
    case LOST = 'lost';
    case INPUT_ERROR = 'input_error';
    case WRONG_LOCATION = 'wrong_location';
    case UNRECORDED_RETURN = 'unrecorded_return';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BROKEN => 'Pecah/Rusak',
            self::LOST => 'Hilang',
            self::INPUT_ERROR => 'Salah Input',
            self::WRONG_LOCATION => 'Salah Lokasi',
            self::UNRECORDED_RETURN => 'Retur Belum Dicatat',
            self::OTHER => 'Lainnya',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $reason): array => [$reason->value => $reason->label()])->all();
    }
}
