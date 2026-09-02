<?php

namespace App\Enums;

use App\Contracts\StatusContract;

enum TaxDocumentStatus: string implements StatusContract
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case RECONCILED = 'reconciled';
    case CANCELLED = 'cancelled';
    case REVERSED = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf',
            self::POSTED => 'Diposting',
            self::RECONCILED => 'Direkonsiliasi',
            self::CANCELLED => 'Dibatalkan',
            self::REVERSED => 'Dibalik',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::CANCELLED, self::REVERSED], true);
    }
}
