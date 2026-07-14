<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case WAITING = 'waiting';
    case PACKING = 'packing';
    case READY = 'ready';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case RETURNED = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::WAITING => 'Menunggu',
            self::PACKING => 'Packing',
            self::READY => 'Siap Kirim',
            self::SHIPPED => 'Dikirim',
            self::DELIVERED => 'Terkirim',
            self::FAILED => 'Gagal Kirim',
            self::RETURNED => 'Dikembalikan',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
