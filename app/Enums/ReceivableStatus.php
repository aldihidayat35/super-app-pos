<?php

namespace App\Enums;

enum ReceivableStatus: string
{
    case OPEN = 'open';
    case PARTIAL = 'partial';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';
    case WRITTEN_OFF = 'written_off';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::PARTIAL => 'Dibayar Sebagian',
            self::PAID => 'Lunas',
            self::OVERDUE => 'Overdue',
            self::CANCELLED => 'Dibatalkan',
            self::WRITTEN_OFF => 'Dihapus Buku',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
