<?php

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;

final class DateFormatter
{
    public static function date(DateTimeInterface|CarbonInterface|null $value): string
    {
        return $value?->format((string) config('gudangtoko.date_format', 'd/m/Y')) ?? '-';
    }

    public static function dateTime(DateTimeInterface|CarbonInterface|null $value): string
    {
        return $value?->format((string) config('gudangtoko.datetime_format', 'd/m/Y H:i')) ?? '-';
    }
}
