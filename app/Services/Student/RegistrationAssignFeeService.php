<?php

namespace App\Services\Student;

use App\Enums\Student\StudentType;
use App\Enums\Transport\Direction;
use App\Http\Resources\Finance\FeeConcessionResource;
use App\Http\Resources\Finance\FeeStructureResource;
use App\Http\Resources\Transport\CircleResource;
use App\Models\Finance\FeeConcession;
use App\Models\Finance\FeeHead;
use App\Models\Finance\FeeStructure;
use App\Models\Student\Registration;
use App\Models\Transport\Circle;
use Illuminate\Http\Request;

class RegistrationAssignFeeService
{
    public function preRequisite(Request $request, Registration $registration): array
    {
        $feeStructures = FeeStructureResource::collection(FeeStructure::query()
            ->byPeriod($registration->period_id)
            ->get());

        $studentTypes = StudentType::getOptions();

        $directions = Direction::getOptions();

        $transportCircles = CircleResource::collection(Circle::query()
            ->byPeriod($registration->period_id)
            ->get());

        $feeConcessions = FeeConcessionResource::collection(FeeConcession::query()
            ->byPeriod($registration->period_id)
            ->get());

        return compact('studentTypes', 'directions', 'transportCircles', 'feeStructures', 'feeConcessions');
    }

    public function assignFee(Request $request, Registration $registration): void
    {
        $feeHeads = FeeHead::query()
            ->byPeriod($registration->period_id)
            ->get();

        $registration->setMeta([
            'fee_assignment' => [
                'assign_fee_later' => $request->boolean('assign_fee_later'),
                'fee_structure_uuid' => $request->fee_structure_uuid,
                'fee_concession_uuid' => $request->fee_concession_uuid,
                'transport_circle_uuid' => $request->transport_circle_uuid,
                'direction' => $request->direction,
                'opted_fee_heads' => $feeHeads->whereIn('uuid', $request->opted_fee_heads)->pluck('uuid')->values()->all(),
                'student_type' => $request->student_type,
            ],
        ]);
        $registration->save();
    }
}
