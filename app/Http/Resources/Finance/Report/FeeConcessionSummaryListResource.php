<?php

namespace App\Http\Resources\Finance\Report;

use Illuminate\Http\Resources\Json\JsonResource;

class FeeConcessionSummaryListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'father_name' => $this->father_name,
            'code_number' => $this->code_number,
            'roll_number' => $this->roll_number,
            'joining_date' => \Cal::date($this->joining_date),
            'batch_name' => $this->batch_name,
            'course_name' => $this->course_name,
            'category_name' => $this->category_name,
            'contact_number' => $this->contact_number,
            'concession' => \Price::from($this->concession),
            'fee_concession_type' => $this->fee_concession_type,
        ];
    }
}
