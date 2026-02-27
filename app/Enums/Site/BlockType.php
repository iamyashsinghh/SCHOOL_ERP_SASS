<?php

namespace App\Enums\Site;

use App\Concerns\HasEnum;

enum BlockType: string
{
    use HasEnum;

    case SLIDER = 'slider';
    case ACCORDION = 'accordion';
    case STAT_COUNTER = 'stat_counter';
    case TESTIMONIAL = 'testimonial';

    public static function translation(): string
    {
        return 'site.block.types.';
    }
}
