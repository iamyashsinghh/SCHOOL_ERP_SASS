<?php

namespace App\Services\Student;

use App\Actions\Student\TransferStudent;
use App\Enums\Student\TransferRequestStatus;
use App\Models\Student\TransferRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TransferRequestActionService
{
    public function preRequisite(Request $request): array
    {
        $statuses = TransferRequestStatus::getOptions();

        return compact('statuses');
    }

    public function action(Request $request, TransferRequest $transferRequest): void
    {
        \DB::beginTransaction();

        if ($request->status != TransferRequestStatus::APPROVED->value) {
            $transferRequest->setMeta([
                'comment' => $request->comment,
            ]);
            $transferRequest->status = $request->status;
            $transferRequest->save();

            \DB::commit();

            return;
        }

        $transferRequest->setMeta([
            'comment' => $request->comment,
            'processed_by' => auth()->user()->name,
        ]);
        $transferRequest->processed_at = now()->toDateTimeString();
        $transferRequest->status = $request->status;
        $transferRequest->save();

        $student = $transferRequest->student;

        (new TransferStudent)->execute($student, [
            'date' => $request->date,
            'transfer_certificate_number' => $request->transfer_certificate_number,
            'transfer_request' => true,
            'reason_id' => $request->reason_id,
        ]);

        \DB::commit();
    }
}
