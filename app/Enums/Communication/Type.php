<?php

namespace App\Enums\Communication;

use App\Concerns\HasEnum;

enum Type: string
{
    use HasEnum;

    case SMS = 'sms';
    case EMAIL = 'email';
    case WHATSAPP = 'whatsapp';
    case PUSH_MESSAGE = 'push_message';

    public static function translation(): string
    {
        return 'communication.types.';
    }
}
