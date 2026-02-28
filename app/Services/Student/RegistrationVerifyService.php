<?php

namespace App\Services\Student;

use App\Enums\Student\RegistrationStatus;
use App\Models\Tenant\Student\Registration;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RegistrationVerifyService
{
    public function verify(Request $request, Registration $registration): void
    {
        if ($registration->status != RegistrationStatus::PENDING) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        $registration->status = RegistrationStatus::VERIFIED;
        $registration->setMeta([
            'verified_by_uuid' => auth()->user()->uuid,
            'verified_by' => auth()->user()->name,
            'verified_at' => now()->toDateTimeString(),
        ]);
        $registration->save();
    }
}
