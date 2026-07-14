<?php

namespace App\Enums;

enum ApprovalRequestStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Approval',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
            self::CANCELLED => 'Dibatalkan',
            self::EXPIRED => 'Kedaluwarsa',
        };
    }
}
