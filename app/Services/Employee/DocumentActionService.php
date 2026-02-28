<?php

namespace App\Services\Employee;

use App\Enums\VerificationStatus;
use App\Models\Tenant\Contact;
use App\Models\Tenant\Document;
use App\Models\Tenant\Employee\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentActionService
{
    public function action(Request $request, Employee $employee, string $document): void
    {
        $request->validate([
            'status' => 'required|in:verify,reject',
            'comment' => 'required_if:status,reject|max:200',
        ]);

        $document = Document::query()
            ->whereHasMorph(
                'documentable',
                [Contact::class],
                function ($q) use ($employee) {
                    $q->whereId($employee->contact_id);
                }
            )
            ->whereUuid($document)
            ->getOrFail(trans('employee.document.document'));

        if (! $document->getMeta('self_upload')) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
        }

        if ($document->verification_status != VerificationStatus::PENDING) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_operation')]);
        }

        if ($request->status == 'reject') {
            $document->setMeta([
                'status' => 'rejected',
                'comment' => $request->comment,
            ]);
            $document->save();

            return;
        }

        $document->verified_at = now()->toDateTimeString();
        $document->setMeta([
            'comment' => $request->comment,
            'verified_by' => auth()->user()?->name,
        ]);
        $document->save();
    }
}
