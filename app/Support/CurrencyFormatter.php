<?php

namespace App\Support;

use InvalidArgumentException;

final class CurrencyFormatter
{
    public static function rupiah(int|string|float|null $amount = 0, bool $withDecimals = false): string
    {
        if ($amount === null || $amount === '') {
            $amount = 0;
        }

        if (is_float($amount)) {
            $amount = number_format($amount, 2, '.', '');
        }

        $normalized = trim((string) $amount);

        if (preg_match('/^-?\d+(?:\.\d+)?$/', $normalized) !== 1) {
            throw new InvalidArgumentException('Nilai mata uang tidak valid.');
        }

        $negative = str_starts_with($normalized, '-');
        $unsigned = ltrim($normalized, '-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $formattedWhole = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) ?: $whole;
        $formattedFraction = $withDecimals ? ','.str_pad(substr($fraction, 0, 2), 2, '0') : '';

        return sprintf('%sRp%s%s', $negative ? '-' : '', $formattedWhole, $formattedFraction);
    }
}
