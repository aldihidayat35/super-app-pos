<?php

namespace App\Enums;

enum ReceiptQcStatus: string
{
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case DAMAGED = 'damaged';
    case RETURNED_TO_SUPPLIER = 'returned_to_supplier';

    public function label(): string
    {
        return match ($this) {
            self::ACCEPTED => 'Diterima',
            self::REJECTED => 'Ditolak',
            self::DAMAGED => 'Rusak',
            self::RETURNED_TO_SUPPLIER => 'Dikembalikan ke Supplier',
        };
    }
}
