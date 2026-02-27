<?php

namespace App\Enums;

use App\Concerns\HasEnum;

enum CustomFieldForm: string
{
    use HasEnum;

    case TODO = 'todo';
    case REGISTRATION = 'registration';
    case STUDENT = 'student';
    case EMPLOYEE = 'employee';
    case ENQUIRY = 'enquiry';
    case TASK = 'task';
    case TICKET = 'ticket';

    public static function translation(): string
    {
        return 'custom_field.forms.';
    }
}
