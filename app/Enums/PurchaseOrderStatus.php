<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case SENT_TO_SUPPLIER = 'sent_to_supplier';
    case PARTIALLY_RECEIVED = 'partially_received';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Diajukan',
            self::APPROVED => 'Disetujui',
            self::SENT_TO_SUPPLIER => 'Dikirim ke Supplier',
            self::PARTIALLY_RECEIVED => 'Diterima Sebagian',
            self::COMPLETED => 'Selesai',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::SUBMITTED => 'warning',
            self::APPROVED => 'primary',
            self::SENT_TO_SUPPLIER => 'info',
            self::PARTIALLY_RECEIVED => 'warning',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function canEditItems(): bool
    {
        return in_array($this, [self::DRAFT, self::SUBMITTED], true);
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
