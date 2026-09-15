<?php

namespace App\Enums;

enum AttendanceVerificationStatus: string
{
    case NOT_READY = 'not_ready';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case NOT_REQUIRED = 'not_required';

    public function label(): string
    {
        return match ($this) {
            self::NOT_READY => 'Masih Bekerja',
            self::PENDING => 'Menunggu Verifikasi',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
            self::NOT_REQUIRED => 'Data Lama',
        };
    }
}
