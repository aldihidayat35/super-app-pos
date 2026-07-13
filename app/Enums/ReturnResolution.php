<?php

namespace App\Enums;

enum ReturnResolution: string
{
    case RESTOCK_GOOD = 'restock_good';
    case MOVE_TO_DAMAGED = 'move_to_damaged';
    case RETURN_TO_SUPPLIER = 'return_to_supplier';
    case REPLACE_ITEM = 'replace_item';
    case REFUND = 'refund';
    case REDUCE_RECEIVABLE = 'reduce_receivable';
    case CREDIT_NOTE = 'credit_note';
    case REJECT_RETURN = 'reject_return';

    public function label(): string
    {
        return match ($this) {
            self::RESTOCK_GOOD => 'Masuk Stok Layak Jual',
            self::MOVE_TO_DAMAGED => 'Masuk Barang Rusak',
            self::RETURN_TO_SUPPLIER => 'Retur ke Supplier',
            self::REPLACE_ITEM => 'Tukar Barang',
            self::REFUND => 'Refund',
            self::REDUCE_RECEIVABLE => 'Kurangi Piutang',
            self::CREDIT_NOTE => 'Credit Note',
            self::REJECT_RETURN => 'Tolak Retur',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $resolution): array => [$resolution->value => $resolution->label()])->all();
    }
}
