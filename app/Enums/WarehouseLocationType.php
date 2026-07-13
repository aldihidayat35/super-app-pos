<?php

namespace App\Enums;

enum WarehouseLocationType: string
{
    case ZONE = 'zone';
    case RACK = 'rack';
    case BIN = 'bin';

    public function label(): string
    {
        return match ($this) {
            self::ZONE => 'Zona',
            self::RACK => 'Rak',
            self::BIN => 'Bin',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])->all();
    }
}
