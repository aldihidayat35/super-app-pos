<?php

namespace App\Enums;

enum StockTransferDiscrepancyResolutionType: string
{
    case FOUND_RECEIVED = 'found_received';
    case INVENTORY_LOSS = 'inventory_loss';
    case SHORTAGE_ACCEPTED = 'shortage_accepted';

    public function label(): string
    {
        return match ($this) {
            self::FOUND_RECEIVED => 'Barang ditemukan dan diterima susulan',
            self::INVENTORY_LOSS => 'Barang dinyatakan hilang',
            self::SHORTAGE_ACCEPTED => 'Kekurangan diterima sebagai final',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])->all();
    }
}
