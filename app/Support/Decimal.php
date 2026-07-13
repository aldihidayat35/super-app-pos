<?php

namespace App\Support;

use InvalidArgumentException;

class Decimal
{
    public static function add(string|int|float $left, string|int|float $right, int $scale = 4): string
    {
        return self::fromScaledInteger(self::toScaledInteger($left, $scale) + self::toScaledInteger($right, $scale), $scale);
    }

    public static function sub(string|int|float $left, string|int|float $right, int $scale = 4): string
    {
        return self::fromScaledInteger(self::toScaledInteger($left, $scale) - self::toScaledInteger($right, $scale), $scale);
    }

    public static function mul(string|int|float $left, string|int|float $right, int $leftScale = 4, int $rightScale = 2, int $resultScale = 2): string
    {
        $result = self::toScaledInteger($left, $leftScale) * self::toScaledInteger($right, $rightScale);
        $divisor = 10 ** ($leftScale + $rightScale - $resultScale);

        return self::fromScaledInteger(self::divideIntegerWithRounding($result, $divisor), $resultScale);
    }

    public static function div(string|int|float $left, string|int|float $right, int $leftScale = 2, int $rightScale = 4, int $resultScale = 2): string
    {
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
        return self::fromScaledInteger(self::toScaledInteger($value, $scale), $scale);
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

    private static function divideIntegerWithRounding(int $numerator, int $denominator): int
    {
        $negative = ($numerator < 0) !== ($denominator < 0);
        $numerator = abs($numerator);
        $denominator = abs($denominator);
        $rounded = intdiv(($numerator * 2) + $denominator, $denominator * 2);

        return $negative ? -$rounded : $rounded;
    }
}
