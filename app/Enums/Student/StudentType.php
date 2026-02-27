<?php

namespace App\Enums\Student;

use App\Concerns\HasEnum;

enum StudentType: string
{
    use HasEnum;

    case NEW = 'new';
    case OLD = 'old';

    public static function translation(): string
    {
        return 'student.types.';
    }
}
