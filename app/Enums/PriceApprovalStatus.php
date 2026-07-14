<?php

namespace App\Enums;

enum PriceApprovalStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Approval',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
            self::EXPIRED => 'Kedaluwarsa',
        };
    }
}
