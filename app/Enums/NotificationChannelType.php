<?php

namespace App\Enums;

enum NotificationChannelType: string
{
    case WHATSAPP = 'whatsapp';
    case TELEGRAM = 'telegram';

    public function label(): string
    {
        return match ($this) {
            self::WHATSAPP => 'WhatsApp API',
            self::TELEGRAM => 'Telegram Bot',
        };
    }
}
