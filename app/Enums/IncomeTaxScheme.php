<?php

namespace App\Enums;

enum IncomeTaxScheme: string
{
    case FINAL_05 = 'final_05';
    case ARTICLE_31E = 'article_31e';
    case GENERAL = 'general';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::FINAL_05 => 'PPh Final 0,5% omzet', self::ARTICLE_31E => 'Fasilitas Pasal 31E',
            self::GENERAL => 'Tarif umum', self::MANUAL => 'Nominal konsultan/Coretax',
        };
    }
}
