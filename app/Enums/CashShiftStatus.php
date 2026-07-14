<?php

namespace App\Enums;

enum CashShiftStatus: string
{
    case OPEN = 'open';
    case CLOSING_SUBMITTED = 'closing_submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Terbuka',
            self::CLOSING_SUBMITTED => 'Menunggu Verifikasi',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
            self::CLOSED => 'Ditutup',
        };
    }

    public function isLocked(): bool
    {
        return in_array($this, [self::CLOSING_SUBMITTED, self::APPROVED, self::CLOSED], true);
    }
}
