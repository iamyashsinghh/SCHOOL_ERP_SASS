<?php

namespace App\Enums\Resource;

use App\Concerns\HasEnum;

enum AudienceType: string
{
    use HasEnum;

    case BATCH_WISE = 'batch_wise';
    case STUDENT_WISE = 'student_wise';

    public static function translation(): string
    {
        return 'resource.audience_types.';
    }
}
