<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class DocumentNumber
{
    public static function format(string $prefix, int $sequence, ?DateTimeInterface $date = null): string
    {
        if ($sequence < 1) {
            throw new InvalidArgumentException('Urutan nomor dokumen harus lebih besar dari nol.');
        }

        $cleanPrefix = strtoupper(trim($prefix));

        if ($cleanPrefix === '' || preg_match('/^[A-Z0-9-]+$/', $cleanPrefix) !== 1) {
            throw new InvalidArgumentException('Prefix nomor dokumen tidak valid.');
        }

        $separator = (string) config('gudangtoko.document_number.separator', '/');
        $length = (int) config('gudangtoko.document_number.sequence_length', 6);
        $documentDate = Carbon::instance($date ?? now())->timezone((string) config('app.timezone'));

        return implode($separator, [
            $cleanPrefix,
            $documentDate->format('Ymd'),
            str_pad((string) $sequence, $length, '0', STR_PAD_LEFT),
        ]);
    }
}
