<?php

namespace App\Support;

use Stringable;

final class QuantityFormatter
{
    public static function format(int|float|string|Stringable|null $value, int $maxDecimals = 4, string $empty = '-'): string
    {
        if ($value === null) {
            return $empty;
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return $empty;
        }

        $raw = str_replace(' ', '', $raw);

        if (str_contains($raw, ',') && ! str_contains($raw, '.')) {
            $raw = str_replace(',', '.', $raw);
        }

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $raw)) {
            return $raw;
        }

        $isNegative = str_starts_with($raw, '-');
        $number = $isNegative ? substr($raw, 1) : $raw;
        [$integer, $decimal] = array_pad(explode('.', $number, 2), 2, '');

        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $integer) ?: $integer;

        $decimal = $maxDecimals > 0 ? substr($decimal, 0, $maxDecimals) : '';
        $decimal = rtrim($decimal, '0');

        return ($isNegative ? '-' : '').$integer.($decimal !== '' ? ','.$decimal : '');
    }

    public static function input(int|float|string|Stringable|null $value, int $maxDecimals = 4, string $empty = ''): string
    {
        if ($value === null) {
            return $empty;
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return $empty;
        }

        $raw = str_replace(' ', '', $raw);

        if (str_contains($raw, ',') && ! str_contains($raw, '.')) {
            $raw = str_replace(',', '.', $raw);
        }

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $raw)) {
            return $raw;
        }

        [$integer, $decimal] = array_pad(explode('.', $raw, 2), 2, '');
        $decimal = $maxDecimals > 0 ? substr($decimal, 0, $maxDecimals) : '';
        $decimal = rtrim($decimal, '0');

        return $integer.($decimal !== '' ? '.'.$decimal : '');
    }
}
