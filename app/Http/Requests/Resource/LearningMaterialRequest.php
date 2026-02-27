<?php

namespace App\Http\Requests\Resource;

use App\Enums\Resource\AudienceType;
use App\Models\Academic\Batch;
use App\Models\Academic\Subject;
use App\Models\Media;
use App\Models\Resource\LearningMaterial;
use App\Models\Student\Student;
use App\Support\HasAudience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

class LearningMaterialRequest extends FormRequest
{
    use HasAudience;

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
            'title' => 'required|max:255',
            'batches' => 'required_if:audience_type,batch_wise|array',
            'subject' => 'nullable|uuid',
            'students' => 'required_if:audience_type,student_wise|array',
            'description' => 'nullable|max:10000',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $mediaModel = (new LearningMaterial)->getModelName();

            $learningMaterialUuid = $this->route('learning_material');

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

            $attachedMedia = Media::whereModelType($mediaModel)
                ->whereToken($this->media_token)
                // ->where('meta->hash', $this->media_hash)
                ->where('meta->is_temp_deleted', false)
                ->where(function ($q) use ($learningMaterialUuid) {
                    $q->whereStatus(0)
                        ->when($learningMaterialUuid, function ($q) {
                            $q->orWhere('status', 1);
                        });
                })
                ->exists();

            if (! $attachedMedia) {
                throw ValidationException::withMessages(['message' => trans('global.could_not_find', ['attribute' => trans('general.attachment')])]);
            }

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
            'title' => __('resource.learning_material.props.title'),
            'batches' => __('academic.batch.batch'),
            'subject' => __('academic.subject.subject'),
            'students' => __('student.student'),
            'description' => __('resource.learning_material.props.description'),
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
