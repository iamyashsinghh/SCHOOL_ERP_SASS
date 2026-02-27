<?php

namespace App\Enums\Approval;

use App\Concerns\HasEnum;

enum Event: string
{
    use HasEnum;

    case STUDENT_TRANSFER = 'student_transfer';
    case EMPLOYEE_LEAVE = 'employee_leave';

    public static function translation(): string
    {
        return 'approval.events.';
    }
}
