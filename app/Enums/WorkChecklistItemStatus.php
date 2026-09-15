<?php

namespace App\Enums;

enum WorkChecklistItemStatus: string
{
    case PENDING = 'pending';
    case DONE = 'done';
    case BLOCKED = 'blocked';
    case NOT_APPLICABLE = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Belum Diisi',
            self::DONE => 'Selesai',
            self::BLOCKED => 'Terkendala',
            self::NOT_APPLICABLE => 'Tidak Berlaku',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
