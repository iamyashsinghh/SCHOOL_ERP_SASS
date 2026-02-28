<?php

namespace App\Concerns;

use App\Models\Tenant\Student\Student;

trait StudentAccess
{
    public function getAccessibleStudentIds()
    {
        return Student::query()
            ->filterAccessible()
            ->pluck('students.id')
            ->all();
    }
}
