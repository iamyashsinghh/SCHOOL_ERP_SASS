<?php

namespace App\Enums\Helpdesk\Faq;

use App\Concerns\HasEnum;

enum Visibility: string
{
    use HasEnum;

    case PUBLIC = 'public';
    case AUTHENTICATED = 'authenticated';

    public static function translation(): string
    {
        return 'helpdesk.faq.visibilities.';
    }
}
