<?php

namespace App\Enums;

enum StaffBonusPeriodStatus: string
{
    case ACTIVE = 'active';
    case CALCULATING = 'calculating';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Berjalan', self::CALCULATING => 'Menghitung',
            self::PENDING_APPROVAL => 'Menunggu Persetujuan', self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak', self::CLOSED => 'Ditutup',
        };
    }
}
