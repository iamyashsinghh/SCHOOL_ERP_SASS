<?php

namespace App\Http\Requests\Task;

use App\Models\Task\Checklist;
use Illuminate\Foundation\Http\FormRequest;

class ChecklistRequest extends FormRequest
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
            'title' => 'required|min:2|max:200',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable|date_format:H:i:s',
            'description' => 'nullable|min:2|max:10000',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $taskUuid = $this->route('task');
            $checklistUuid = $this->route('checklist');

            $existingChecklist = Checklist::whereHas('task', function ($q) use ($taskUuid) {
                $q->whereUuid($taskUuid);
            })
                ->when($checklistUuid, function ($q, $checklistUuid) {
                    $q->where('uuid', '!=', $checklistUuid);
                })
                ->whereTitle($this->title)
                ->exists();

            if ($existingChecklist) {
                $validator->errors()->add('title', trans('validation.unique', ['attribute' => __('task.checklist.props.title')]));
            }
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
            'title' => __('task.checklist.props.title'),
            'due_date' => __('task.checklist.props.due_date'),
            'due_time' => __('task.checklist.props.due_time'),
            'description' => __('task.checklist.props.description'),
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [];
    }
}
