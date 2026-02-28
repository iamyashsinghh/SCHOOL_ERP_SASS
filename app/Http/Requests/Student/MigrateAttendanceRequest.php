<?php

namespace App\Http\Requests\Student;

use App\Models\Tenant\Academic\Batch;
use App\Models\Tenant\Student\Student;
use Illuminate\Foundation\Http\FormRequest;

class MigrateAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'student' => 'required|uuid',
            'previous_batch' => 'required|uuid',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after:start_date',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $student = Student::query()
                ->byPeriod()
                ->where('students.uuid', $this->student)
                ->getOrFail(trans('student.student'), 'student');

            $previousBatch = Batch::query()
                ->byPeriod()
                ->where('batches.uuid', $this->previous_batch)
                ->getOrFail(trans('academic.batch.batch'), 'previous_batch');

            if ($student->batch_id == $previousBatch->id) {
                $validator->errors()->add('previous_batch', trans('student.attendance.same_batch'));
            }

            $this->merge([
                'student_id' => $student->id,
                'batch_id' => $student->batch_id,
                'previous_batch_id' => $previousBatch->id,
            ]);
        });
    }

    /**
     * Translate fields with user friendly name.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'student' => trans('student.student'),
            'previous_batch' => trans('academic.batch.batch'),
            'start_date' => trans('general.start_date'),
            'end_date' => trans('general.end_date'),
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            //
        ];
    }
}
