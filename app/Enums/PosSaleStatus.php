<?php

namespace App\Enums;

enum PosSaleStatus: string
{
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case VOID_PENDING = 'void_pending';
    case VOID_APPROVED = 'void_approved';
    case RETURNED = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktif',
            self::COMPLETED => 'Selesai',
            self::VOID_PENDING => 'Menunggu Void',
            self::VOID_APPROVED => 'Void Disetujui',
            self::RETURNED => 'Diretur',
        };
    }

    public function canVoid(): bool
    {
        return in_array($this, [self::COMPLETED, self::RETURNED], true);
    }
}
