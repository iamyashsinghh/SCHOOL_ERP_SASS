<?php

namespace App\Services\Student;

use App\Models\Tenant\Student\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FeeActionService
{
    public function lockUnlock(Request $request, Student $student)
    {
        if ($student->getMeta('fee_locked_at') && $request->action == 'lock') {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        } elseif (! $student->getMeta('fee_locked_at') && $request->action == 'unlock') {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        $student->setMeta([
            'fee_locked_at' => $request->action == 'lock' ? now()->toDateTimeString() : null,
        ]);
        $student->save();
    }
}
