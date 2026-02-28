<?php

namespace App\Actions\Student;

use App\Models\Tenant\Student\Admission;
use App\Models\Tenant\Student\Student;
use Illuminate\Support\Arr;

class TransferStudent
{
    public function execute(Student $student, array $params = []): void
    {
        $date = Arr::get($params, 'date');
        $transferCertificateNumber = Arr::get($params, 'transfer_certificate_number');
        $transferRequest = (bool) Arr::get($params, 'transfer_request');
        $reasonId = Arr::get($params, 'reason_id');
        $remarks = Arr::get($params, 'remarks');

        $student->end_date = $date;
        $student->setMeta([
            'transfer_certificate_number' => $transferCertificateNumber,
            'transfer_request' => $transferRequest,
        ]);
        $student->save();

        $admission = Admission::query()
            ->whereId($student->admission_id)
            ->first();

        $admission->leaving_date = $date;
        $admission->transfer_reason_id = $reasonId;
        $admission->leaving_remarks = $remarks;
        $admission->save();

        foreach ($student->fees as $studentFee) {
            $studentFee->setMeta([
                'total_before_transfer' => $studentFee->total->value,
            ]);
            $studentFee->total = $studentFee->paid->value;
            $studentFee->save();

            foreach ($studentFee->records as $record) {
                $record->setMeta([
                    'amount_before_transfer' => $record->amount->value,
                ]);
                $record->amount = $record->paid->value;
                $record->save();
            }
        }
    }
}
