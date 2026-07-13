<?php

namespace App\Enums;

use App\Contracts\StatusContract;

enum DocumentStatus: string implements StatusContract
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Diajukan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
            self::Voided => 'Dibatalkan dengan reversal',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Rejected, self::Completed, self::Cancelled, self::Voided], true);
    }
}
