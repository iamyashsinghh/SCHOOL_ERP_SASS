<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class BatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $subjects = collect([]);
        $subjectRecords = $request->subject_records ?? collect([]);

        if ($request->query('list_subjects')) {
            $subjects = $subjectRecords->filter(function ($subjectRecord) {
                return $subjectRecord->batch_id == $this->id || $subjectRecord->course_id == $this->course_id;
            });
        }

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            $this->mergeWhen($request->query('details'), [
                'incharge' => BatchInchargeResource::make($this->whenLoaded('incharge')),
                'incharges' => DivisionInchargeResource::collection($this->whenLoaded('incharges')),
                'period_history' => collect($this->getMeta('period_history', []))->map(function ($period) {
                    return [
                        'name' => Arr::get($period, 'name'),
                        'datetime' => \Cal::dateTime(Arr::get($period, 'datetime')),
                    ];
                }),
            ]),
            'max_strength' => $this->max_strength,
            'current_strength' => $this->current_strength,
            $this->mergeWhen($request->query('with_subjects'), [
                'subject_records' => SubjectRecordResource::collection($this->whenLoaded('subjectRecords')),
            ]),
            'subject_records' => SubjectRecordResource::collection($this->whenLoaded('subjectRecords')),
            'subjects' => $subjects->map(function ($subject) {
                return [
                    'uuid' => $subject->uuid,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'credit' => $subject->credit,
                    'is_elective' => (bool) $subject->is_elective,
                    'max_class_per_week' => $subject->max_class_per_week,
                    'has_no_exam' => (bool) $subject->has_no_exam,
                    'has_grading' => (bool) $subject->has_grading,
                    'position' => $subject->subject_record_position,
                ];
            })->values(),
            'roll_number_prefix' => Arr::get($this->config, 'roll_number_prefix'),
            'course' => CourseResource::make($this->whenLoaded('course')),
            'position' => $this->position,
            'pg_account' => $this->getMeta('pg_account'),
            'description' => $this->description,
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
