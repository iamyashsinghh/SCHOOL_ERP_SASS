<?php

namespace App\Http\Requests\Resource;

use App\Enums\Resource\AudienceType;
use App\Models\Tenant\Academic\Batch;
use App\Models\Tenant\Academic\Subject;
use App\Models\Tenant\Resource\Diary;
use App\Models\Tenant\Student\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

class DiaryRequest extends FormRequest
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
            'audience_type' => ['nullable', new Enum(AudienceType::class)],
            'batches' => 'required_if:audience_type,batch_wise|array',
            'subject' => 'nullable|uuid',
            'students' => 'required_if:audience_type,student_wise|array',
            'date' => 'required|date_format:Y-m-d',
            'details' => 'required|array|min:1',
            'details.*.heading' => 'required|min:2|max:255|distinct',
            'details.*.description' => 'nullable|max:10000',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $mediaModel = (new Diary)->getModelName();

            $diaryUuid = $this->route('diary');

            $this->audience_type = $this->audience_type ?? 'batch_wise';

            $batches = $this->audience_type == 'batch_wise' ? Batch::query()
                ->byPeriod()
                ->filterAccessible()
                ->whereIn('uuid', $this->batches)
                ->listOrFail(trans('academic.batch.batch'), 'batches') : null;

            if ($this->audience_type == 'batch_wise' && ! $batches) {
                $validator->errors()->add('batches', trans('validation.required', [
                    'attribute' => trans('academic.batch.batch'),
                ]));
            }

            $subject = null;
            if ($this->audience_type == 'batch_wise' && $this->subject) {
                foreach ($batches as $batch) {
                    $subject = Subject::query()
                        ->findByBatchOrFail($batch->id, $batch->course_id, $this->subject);
                }
            }

            $students = $this->audience_type == 'student_wise' ? Student::query()
                ->byPeriod()
                ->filterAccessible()
                ->whereIn('uuid', $this->students)
                ->listOrFail(trans('student.student'), 'students') : null;

            if ($this->audience_type == 'student_wise' && ! $students) {
                $validator->errors()->add('students', trans('validation.required', [
                    'attribute' => trans('student.student'),
                ]));
            }

            // Could not check becase of multiple batches
            // $existingRecord = Diary::query()
            //     ->where('batch_id', $batch->id)
            //     ->where('subject_id', $subject?->id)
            //     ->where('date', $this->date)
            //     ->where('uuid', '!=', $diaryUuid)
            //     ->exists();

            // if ($existingRecord) {
            //     throw ValidationException::withMessages(['message' => trans('resource.diary.duplicate_record')]);
            // }

            $this->merge([
                'batch_ids' => $batches?->pluck('id')->all(),
                'subject_id' => $subject?->id,
                'student_ids' => $students?->pluck('id')->all(),
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
            'audience_type' => __('resource.props.audience_type'),
            'batches' => __('academic.batch.batch'),
            'subject' => __('academic.subject.subject'),
            'students' => __('student.student'),
            'date' => __('resource.diary.props.date'),
            'details' => __('resource.diary.props.details'),
            'details.*.heading' => __('resource.diary.props.heading'),
            '.*.description' => __('resource.diary.props.description'),
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
            'batches.required_if' => trans('validation.required', [
                'attribute' => trans('academic.batch.batch'),
            ]),
            'students.required_if' => trans('validation.required', [
                'attribute' => trans('student.student'),
            ]),
        ];
    }
}
