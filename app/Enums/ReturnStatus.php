<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case INSPECTED = 'inspected';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case SETTLED = 'settled';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Diajukan',
            self::INSPECTED => 'Sudah QC',
            self::PENDING_APPROVAL => 'Menunggu Approval',
            self::APPROVED => 'Disetujui',
            self::SETTLED => 'Selesai',
            self::REJECTED => 'Ditolak',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
