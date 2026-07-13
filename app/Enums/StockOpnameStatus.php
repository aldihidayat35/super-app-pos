<?php

namespace App\Enums;

enum StockOpnameStatus: string
{
    case DRAFT = 'draft';
    case COUNTING = 'counting';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::COUNTING => 'Counting',
            self::PENDING_APPROVAL => 'Menunggu Approval',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
            self::COMPLETED => 'Selesai',
        };
    }

    public function canCount(): bool
    {
        return $this === self::COUNTING;
    }

    public function isLocked(): bool
    {
        return in_array($this, [self::PENDING_APPROVAL, self::APPROVED, self::COMPLETED], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
