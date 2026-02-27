<?php

namespace App\Http\Resources\Employee;

use App\Helpers\CalHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class InchargeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $courses = $request->courses ?? collect([]);

        $model = $this->model?->name;
        $detail = $this->detail?->name;

        $subDetail = '';
        if ($this->detail_type == 'Batch') {
            $subDetail = $courses->where('id', $this->detail->course_id)->first()?->name;
            if ($this->model_type == 'Subject') {
                $detail = $subDetail.' - '.$detail;
            }
        } elseif ($this->model_type == 'Batch') {
            $subDetail = $courses->where('id', $this->model->course_id)->first()?->name;

            $model = $subDetail.' - '.$model;
        }

        return [
            'uuid' => $this->uuid,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'name' => $model,
            'detail' => $detail,
            'type' => match ($this->model_type) {
                'AcademicDepartment' => trans('academic.department_incharge.department_incharge'),
                'Program' => trans('academic.program_incharge.program_incharge'),
                'Division' => trans('academic.division_incharge.division_incharge'),
                'Course' => trans('academic.course_incharge.course_incharge'),
                'Batch' => trans('academic.batch_incharge.batch_incharge'),
                'Subject' => trans('academic.subject_incharge.subject_incharge'),
                default => '',
            },
            'period' => CalHelper::getPeriod($this->start_date->value, $this->end_date->value),
            'duration' => CalHelper::getDuration($this->start_date->value, $this->end_date->value),
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
