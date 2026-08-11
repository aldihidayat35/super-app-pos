<?php

use App\Support\QuantityFormatter;

if (! function_exists('versioned_asset')) {
    function versioned_asset(string $path): string
    {
        $normalizedPath = ltrim($path, '/');
        $absolutePath = public_path($normalizedPath);
        $modifiedAt = is_file($absolutePath) ? filemtime($absolutePath) : false;
        $url = asset($normalizedPath);

        return $modifiedAt === false ? $url : $url.'?v='.$modifiedAt;
    }
}

if (! function_exists('qty')) {
    function qty(int|float|string|Stringable|null $value, int $maxDecimals = 0, string $empty = '-'): string
    {
        return QuantityFormatter::format($value, $maxDecimals, $empty);
    }
}

if (! function_exists('qty_input')) {
    function qty_input(int|float|string|Stringable|null $value, int $maxDecimals = 0, string $empty = ''): string
    {
        return QuantityFormatter::input($value, $maxDecimals, $empty);
    }
}
