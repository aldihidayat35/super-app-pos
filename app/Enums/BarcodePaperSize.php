<?php

namespace App\Enums;

enum BarcodePaperSize: string
{
    case A4 = 'A4';
    case THERMAL = 'thermal';

    public function label(): string
    {
        return match ($this) {
            self::A4 => 'A4 (210 × 297 mm)',
            self::THERMAL => 'Thermal ('.qty(config('gudangtoko.barcode.thermal_width_mm', 80)).' × '.qty(config('gudangtoko.barcode.thermal_height_mm', 40)).' mm)',
        };
    }

    public function columns(): int
    {
        return $this === self::A4 ? 3 : 1;
    }

    public function labelsPerPage(): int
    {
        return $this === self::A4 ? 18 : 1;
    }

    /** @return string|array{float, float, float, float} */
    public function dompdfPaper(): string|array
    {
        if ($this === self::A4) {
            return 'a4';
        }

        return [
            0.0,
            0.0,
            self::millimetersToPoints($this->widthMillimeters()),
            self::millimetersToPoints($this->heightMillimeters()),
        ];
    }

    public function widthMillimeters(): float
    {
        return $this === self::A4
            ? 210.0
            : (float) config('gudangtoko.barcode.thermal_width_mm', 80);
    }

    public function heightMillimeters(): float
    {
        return $this === self::A4
            ? 297.0
            : (float) config('gudangtoko.barcode.thermal_height_mm', 40);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $paper): array => [$paper->value => $paper->label()])
            ->all();
    }

    private static function millimetersToPoints(float $millimeters): float
    {
        return $millimeters * 72 / 25.4;
    }
}
