<?php

namespace App\Enums;

enum StockMutationType: string
{
    case RECEIVE = 'receive';
    case ISSUE = 'issue';
    case RESERVE = 'reserve';
    case RELEASE_RESERVATION = 'release_reservation';
    case TRANSFER_OUT = 'transfer_out';
    case TRANSFER_IN = 'transfer_in';
    case DAMAGE = 'damage';
    case RECOVER = 'recover';
    case ADJUST = 'adjust';
    case RETURN_IN = 'return_in';
    case RETURN_OUT = 'return_out';

    public function label(): string
    {
        return match ($this) {
            self::RECEIVE => 'Barang Masuk',
            self::ISSUE => 'Barang Keluar',
            self::RESERVE => 'Reservasi',
            self::RELEASE_RESERVATION => 'Lepas Reservasi',
            self::TRANSFER_OUT => 'Transfer Keluar',
            self::TRANSFER_IN => 'Transfer Masuk',
            self::DAMAGE => 'Rusak',
            self::RECOVER => 'Pulih dari Rusak',
            self::ADJUST => 'Penyesuaian',
            self::RETURN_IN => 'Retur Masuk',
            self::RETURN_OUT => 'Retur Keluar',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])->all();
    }
}
