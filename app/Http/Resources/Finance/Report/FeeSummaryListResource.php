<?php

namespace App\Http\Resources\Finance\Report;

use App\Enums\Gender;
use App\Http\Resources\Finance\FeeStructureResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class FeeSummaryListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // $category = $request->contacts->where('id', $this->contact_id)->first()?->category;

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'father_name' => $this->father_name,
            'code_number' => $this->code_number,
            'joining_date' => \Cal::date($this->joining_date),
            'batch_name' => $this->batch_name,
            'course_name' => $this->course_name,
            'gender' => Gender::getDetail($this->gender),
            'birth_date' => \Cal::date($this->birth_date),
            'contact_number' => $this->contact_number,
            'category' => [
                'name' => $this->category_name,
            ],
            'fee_structure' => FeeStructureResource::make($this->whenLoaded('feeStructure')),
            $this->mergeWhen($request->boolean('head_wise_detail'), [
                ...$this->getHeadWiseRecords($request),
            ]),
            'total' => \Price::from($this->total_fee),
            'concession' => \Price::from($this->concession_fee),
            'paid' => \Price::from($this->paid_fee),
            'balance' => \Price::from($this->balance_fee),
        ];
    }

    private function getHeadWiseRecords($request)
    {
        if (! $request->boolean('head_wise_detail')) {
            return [];
        }

        $headWiseRecords = $request->head_wise_records ?? collect([]);
        $studentHeadWiseRecord = $headWiseRecords->firstWhere('student_id', $this->id);

        $records = [];
        foreach ($request->fee_heads ?? [] as $feeHead) {
            $feeHeadSlug = Str::camel($feeHead->slug);

            $records[$feeHeadSlug] = \Price::from($studentHeadWiseRecord?->$feeHeadSlug ?? 0);
        }

        $records['transportFee'] = \Price::from($studentHeadWiseRecord?->transportFee ?? 0);

        $records['lateFee'] = \Price::from($studentHeadWiseRecord?->lateFee ?? 0);

        return $records;
    }
}
