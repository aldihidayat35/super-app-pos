<?php

namespace App\Enums;

enum TaxDirection: string
{
    case OUTPUT = 'output';
    case INPUT = 'input';
    case WITHHOLDING = 'withholding';

    public function label(): string
    {
        return match ($this) {
            self::OUTPUT => 'Pajak Keluaran',
            self::INPUT => 'Pajak Masukan',
            self::WITHHOLDING => 'Potong/Pungut PPh',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $direction): array => [$direction->value => $direction->label()])->all();
    }
}
