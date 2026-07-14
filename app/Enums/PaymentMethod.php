<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case BANK_TRANSFER = 'bank_transfer';
    case QRIS = 'qris';
    case MANUAL = 'manual';
    case CREDIT = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Tunai',
            self::BANK_TRANSFER => 'Transfer Bank',
            self::QRIS => 'QRIS',
            self::MANUAL => 'Manual',
            self::CREDIT => 'Tempo/Piutang',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $method): array => [$method->value => $method->label()])->all();
    }
}
