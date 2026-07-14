<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
    case PARTIAL = 'partial';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ISSUED => 'Terbit',
            self::PARTIAL => 'Dibayar Sebagian',
            self::PAID => 'Lunas',
            self::OVERDUE => 'Jatuh Tempo',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::PAID, self::CANCELLED], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
