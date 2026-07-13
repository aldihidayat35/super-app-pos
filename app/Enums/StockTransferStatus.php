<?php

namespace App\Enums;

enum StockTransferStatus: string
{
    case DRAFT = 'draft';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case PACKING = 'packing';
    case SHIPPED = 'shipped';
    case PARTIALLY_RECEIVED = 'partially_received';
    case FULLY_RECEIVED = 'fully_received';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING_APPROVAL => 'Menunggu Approval',
            self::APPROVED => 'Disetujui',
            self::PACKING => 'Packing',
            self::SHIPPED => 'Dikirim',
            self::PARTIALLY_RECEIVED => 'Diterima Sebagian',
            self::FULLY_RECEIVED => 'Diterima Penuh',
            self::COMPLETED => 'Selesai',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function canEditItems(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING_APPROVAL], true);
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING_APPROVAL, self::APPROVED, self::PACKING], true);
    }

    public function canReceive(): bool
    {
        return in_array($this, [self::SHIPPED, self::PARTIALLY_RECEIVED], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
