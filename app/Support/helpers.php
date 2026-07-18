<?php

use App\Support\QuantityFormatter;

if (! function_exists('qty')) {
    function qty(int|float|string|Stringable|null $value, int $maxDecimals = 4, string $empty = '-'): string
    {
        return QuantityFormatter::format($value, $maxDecimals, $empty);
    }
}

if (! function_exists('qty_input')) {
    function qty_input(int|float|string|Stringable|null $value, int $maxDecimals = 4, string $empty = ''): string
    {
        return QuantityFormatter::input($value, $maxDecimals, $empty);
    }
}
