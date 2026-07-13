<?php

namespace App\Enums;

enum CustomerType: string
{
    case GENERAL = 'general';
    case RETAIL_CREDIT = 'retail_credit';
    case B2B = 'b2b';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'Umum',
            self::RETAIL_CREDIT => 'Retail Tempo',
            self::B2B => 'Langganan/B2B',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])->all();
    }
}
