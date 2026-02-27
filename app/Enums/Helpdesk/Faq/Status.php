<?php

namespace App\Enums\Helpdesk\Faq;

use App\Concerns\HasEnum;

enum Status: string
{
    use HasEnum;

    case DRAFT = 'draft';
    case PUBLISHED = 'published';

    public static function translation(): string
    {
        return 'helpdesk.faq.statuses.';
    }
}
