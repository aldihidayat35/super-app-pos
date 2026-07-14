<?php

namespace App\Enums;

use App\Contracts\StatusContract;

enum B2bOrderStatus: string implements StatusContract
{
    case PENDING_CONFIRMATION = 'pending_confirmation';
    case WAREHOUSE_VALIDATION = 'warehouse_validation';
    case RESERVED = 'reserved';
    case INVOICE_READY = 'invoice_ready';
    case AWAITING_PAYMENT = 'awaiting_payment';
    case APPROVED_CREDIT = 'approved_credit';
    case PACKING = 'packing';
    case SHIPPED = 'shipped';
    case RECEIVED = 'received';
    case COMPLETED = 'completed';
    case RETURN_REQUESTED = 'return_requested';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_CONFIRMATION => 'Menunggu Konfirmasi',
            self::WAREHOUSE_VALIDATION => 'Validasi Gudang',
            self::RESERVED => 'Stok Reserved',
            self::INVOICE_READY => 'Siap Invoice',
            self::AWAITING_PAYMENT => 'Menunggu Pembayaran',
            self::APPROVED_CREDIT => 'Kredit Disetujui',
            self::PACKING => 'Packing',
            self::SHIPPED => 'Dikirim',
            self::RECEIVED => 'Diterima',
            self::COMPLETED => 'Selesai',
            self::RETURN_REQUESTED => 'Retur Diajukan',
            self::RETURNED => 'Diretur',
            self::CANCELLED => 'Dibatalkan',
            self::REJECTED => 'Ditolak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING_CONFIRMATION, self::WAREHOUSE_VALIDATION => 'warning',
            self::RESERVED, self::INVOICE_READY, self::AWAITING_PAYMENT, self::APPROVED_CREDIT, self::PACKING => 'primary',
            self::SHIPPED => 'info',
            self::RECEIVED, self::COMPLETED => 'success',
            self::RETURN_REQUESTED => 'warning',
            self::RETURNED => 'secondary',
            self::CANCELLED, self::REJECTED => 'danger',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::RETURNED, self::CANCELLED, self::REJECTED], true);
    }

    public function canCustomerCancel(): bool
    {
        return in_array($this, [self::PENDING_CONFIRMATION, self::WAREHOUSE_VALIDATION, self::RESERVED, self::INVOICE_READY, self::AWAITING_PAYMENT, self::APPROVED_CREDIT], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
