<?php

namespace App\Enums;

enum PricingChannel: string
{
    case ALL = 'all';
    case RETAIL = 'retail';
    case B2B = 'b2b';
    case POS = 'pos';

    public function label(): string
    {
        return match ($this) {
            self::ALL => 'Semua Channel',
            self::RETAIL => 'Retail',
            self::B2B => 'B2B',
            self::POS => 'POS',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $channel): array => [$channel->value => $channel->label()])->all();
    }
}
