<?php

namespace App\Support;

use InvalidArgumentException;

class Decimal
{
    public static function add(string|int|float $left, string|int|float $right, int $scale = 4): string
    {
        if (extension_loaded('bcmath')) {
            $l = self::sanitize($left);
            $r = self::sanitize($right);

            return self::round(bcadd($l, $r, $scale + 2), $scale);
        }

        return self::fromScaledInteger(self::toScaledInteger($left, $scale) + self::toScaledInteger($right, $scale), $scale);
    }

    public static function sub(string|int|float $left, string|int|float $right, int $scale = 4): string
    {
        if (extension_loaded('bcmath')) {
            $l = self::sanitize($left);
            $r = self::sanitize($right);

            return self::round(bcsub($l, $r, $scale + 2), $scale);
        }

        return self::fromScaledInteger(self::toScaledInteger($left, $scale) - self::toScaledInteger($right, $scale), $scale);
    }

    public static function mul(string|int|float $left, string|int|float $right, int $leftScale = 4, int $rightScale = 2, int $resultScale = 2): string
    {
        if (extension_loaded('bcmath')) {
            $l = self::sanitize($left);
            $r = self::sanitize($right);

            return self::round(bcmul($l, $r, $leftScale + $rightScale + 2), $resultScale);
        }

        $result = self::toScaledInteger($left, $leftScale) * self::toScaledInteger($right, $rightScale);
        $divisor = 10 ** ($leftScale + $rightScale - $resultScale);

        return self::fromScaledInteger(self::divideIntegerWithRounding($result, $divisor), $resultScale);
    }

    public static function div(string|int|float $left, string|int|float $right, int $leftScale = 2, int $rightScale = 4, int $resultScale = 2): string
    {
        if (extension_loaded('bcmath')) {
            $l = self::sanitize($left);
            $r = self::sanitize($right);

            if (bccomp($r, '0', 10) === 0) {
                throw new InvalidArgumentException('Pembagi desimal tidak boleh nol.');
            }

            return self::round(bcdiv($l, $r, $resultScale + 4), $resultScale);
        }

        $rightInteger = self::toScaledInteger($right, $rightScale);

        if ($rightInteger === 0) {
            throw new InvalidArgumentException('Pembagi desimal tidak boleh nol.');
        }

        $numerator = self::toScaledInteger($left, $leftScale) * (10 ** ($rightScale + $resultScale));
        $denominator = $rightInteger * (10 ** $leftScale);

        return self::fromScaledInteger(self::divideIntegerWithRounding($numerator, $denominator), $resultScale);
    }

    public static function compare(string|int|float $left, string|int|float $right, int $scale = 4): int
    {
        if (extension_loaded('bcmath')) {
            $l = self::sanitize($left);
            $r = self::sanitize($right);

            return bccomp($l, $r, $scale);
        }

        return self::toScaledInteger($left, $scale) <=> self::toScaledInteger($right, $scale);
    }

    public static function isZero(string|int|float $value, int $scale = 4): bool
    {
        return self::compare($value, '0', $scale) === 0;
    }

    public static function isPositive(string|int|float $value, int $scale = 4): bool
    {
        return self::compare($value, '0', $scale) > 0;
    }

    public static function normalize(string|int|float $value, int $scale = 4): string
    {
        $v = self::sanitize($value);
        $negative = str_starts_with($v, '-');
        $v = ltrim($v, '-');
        [$whole, $fraction] = array_pad(explode('.', $v, 2), 2, '');
        $fraction = substr(str_pad($fraction, $scale, '0'), 0, $scale);

        $res = ($scale > 0) ? ($whole.'.'.$fraction) : $whole;
        if ($negative && (extension_loaded('bcmath') ? bccomp($res, '0', $scale) !== 0 : (int) $res !== 0)) {
            return '-'.$res;
        }

        return $res;
    }

    public static function round(string $value, int $scale = 0): string
    {
        $value = trim($value);
        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Format angka desimal tidak valid: '.$value);
        }

        $isNegative = str_starts_with($value, '-');
        $unsignedValue = ltrim($value, '-');

        $half = '0.'.str_repeat('0', $scale).'5';
        $addition = bcadd($unsignedValue, $half, $scale + 1);

        $rounded = $scale === 0 ? bcadd($addition, '0', 0) : bcadd($addition, '0', $scale);

        if ($isNegative && bccomp($rounded, '0', $scale) !== 0) {
            return '-'.$rounded;
        }

        return $rounded;
    }

    private static function sanitize(string|int|float $value): string
    {
        $v = trim((string) $value);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $v)) {
            throw new InvalidArgumentException('Format angka desimal tidak valid: '.$v);
        }

        return $v;
    }

    private static function toScaledInteger(string|int|float $value, int $scale): int
    {
        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Format angka desimal tidak valid.');
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = substr(str_pad($fraction, $scale, '0'), 0, $scale);
        $scaled = ((int) $whole * (10 ** $scale)) + (int) $fraction;

        return $negative ? -$scaled : $scaled;
    }

    private static function fromScaledInteger(int $value, int $scale): string
    {
        $negative = $value < 0;
        $value = abs($value);
        $base = 10 ** $scale;
        $whole = intdiv($value, $base);
        $fraction = str_pad((string) ($value % $base), $scale, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    private static function divideIntegerWithRounding(int|float $numerator, int|float $denominator): int
    {
        $negative = ($numerator < 0) !== ($denominator < 0);
        $numerator = abs($numerator);
        $denominator = abs($denominator);
        $rounded = (int) round($numerator / $denominator);

        return $negative ? -$rounded : $rounded;
    }
}
