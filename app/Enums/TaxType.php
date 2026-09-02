<?php

namespace App\Enums;

enum TaxType: string
{
    case PPN = 'ppn';
    case PPNBM = 'ppnbm';
    case PPH21 = 'pph21';
    case PPH22 = 'pph22';
    case PPH23 = 'pph23';
    case PPH26 = 'pph26';
    case PPH4_2 = 'pph4_2';

    public function label(): string
    {
        return match ($this) {
            self::PPN => 'PPN',
            self::PPNBM => 'PPnBM',
            self::PPH21 => 'PPh Pasal 21',
            self::PPH22 => 'PPh Pasal 22',
            self::PPH23 => 'PPh Pasal 23',
            self::PPH26 => 'PPh Pasal 26',
            self::PPH4_2 => 'PPh Final Pasal 4(2)',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])->all();
    }
}
