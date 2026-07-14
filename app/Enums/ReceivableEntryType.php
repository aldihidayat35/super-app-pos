<?php

namespace App\Enums;

enum ReceivableEntryType: string
{
    case INVOICE = 'invoice';
    case PAYMENT = 'payment';
    case CREDIT_NOTE = 'credit_note';
    case DEBIT_NOTE = 'debit_note';
    case REVERSAL = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::INVOICE => 'Invoice',
            self::PAYMENT => 'Pembayaran',
            self::CREDIT_NOTE => 'Credit Note',
            self::DEBIT_NOTE => 'Debit Note',
            self::REVERSAL => 'Reversal',
        };
    }
}
