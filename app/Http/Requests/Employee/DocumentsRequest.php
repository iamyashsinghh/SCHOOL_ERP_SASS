<?php

namespace App\Http\Requests\Employee;

use App\Enums\OptionType;
use App\Models\Tenant\Contact;
use App\Models\Tenant\Document;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Media;
use App\Models\Tenant\Option;
use App\Rules\SafeRegex;
use Illuminate\Foundation\Http\FormRequest;

class DocumentsRequest extends FormRequest
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
            'employee' => 'required|uuid',
            'type' => 'required|uuid',
            'title' => 'required|min:2|max:100',
            'number' => 'nullable|min:2|max:100',
            'description' => 'nullable|min:2|max:500',
            'issue_date' => 'nullable|date_format:Y-m-d|before_or_equal:start_date',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $documentUuid = $this->route('document');

            $mediaModel = (new Document)->getModelName();

            $employee = Employee::query()
                ->summary()
                ->byTeam()
                ->filterAccessible()
                ->where('employees.uuid', $this->employee)
                ->getOrFail(__('employee.employee'), 'employee');

            $documentType = Option::query()
                ->byTeam()
                ->whereIn('type', [OptionType::DOCUMENT_TYPE, OptionType::EMPLOYEE_DOCUMENT_TYPE])
                ->whereUuid($this->type)
                ->getOrFail(__('employee.document_type.document_type'), 'type');

            if ($documentType->getMeta('has_expiry_date')) {
                // if (empty($this->start_date)) {
                //     $validator->errors()->add('start_date', trans('validation.required', ['attribute' => __('employee.document.props.start_date')]));
                // }

                if (empty($this->end_date)) {
                    $validator->errors()->add('end_date', trans('validation.required', ['attribute' => __('employee.document.props.end_date')]));
                }
            }

            if ($documentType->getMeta('has_number')) {
                if (empty($this->number)) {
                    $validator->errors()->add('number', trans('validation.required', ['attribute' => __('employee.document.props.number')]));
                }

                $validPattern = $documentType->getMeta('number_format');
                if ($validPattern && SafeRegex::isValidRegex(SafeRegex::prepare($validPattern))) {
                    $pattern = SafeRegex::prepare($validPattern);
                    if (! preg_match($pattern, $this->number)) {
                        $validator->errors()->add('number', trans('validation.regex', ['attribute' => __('employee.document.props.number')]));
                    }
                }
            }

            $existingDocument = Document::whereHasMorph(
                'documentable', [Contact::class],
                function ($q) use ($employee) {
                    $q->whereId($employee->contact_id);
                }
            )
                ->when($documentUuid, function ($q, $documentUuid) {
                    $q->where('uuid', '!=', $documentUuid);
                })
                ->whereTypeId($documentType->id)
                ->when($documentType->getMeta('has_number'), function ($q) {
                    $q->whereNumber($this->number);
                }, function ($q) {
                    $q->whereTitle($this->title);
                })
                ->exists();

            if ($existingDocument) {
                $validator->errors()->add('title', trans('validation.unique', ['attribute' => __('employee.document.props.title')]));
            }

            $attachedMedia = Media::whereModelType($mediaModel)
                ->whereToken($this->media_token)
                // ->where('meta->hash', $this->media_hash)
                ->where('meta->is_temp_deleted', false)
                ->where(function ($q) use ($documentUuid) {
                    $q->whereStatus(0)
                        ->when($documentUuid, function ($q) {
                            $q->orWhere('status', 1);
                        });
                })
                ->exists();

            if (! $attachedMedia && $documentType->getMeta('is_document_required')) {
                $validator->errors()->add('media', trans('validation.required', ['attribute' => __('general.attachment')]));
            }

            $this->merge([
                'contact_id' => $employee->contact_id,
                'employee_id' => $employee->id,
                'user_id' => $employee->user_id,
                'type_id' => $documentType->id,
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
            'employee' => __('employee.employee'),
            'type' => __('employee.document_type.document_type'),
            'number' => __('employee.document.props.number'),
            'title' => __('employee.document.props.title'),
            'description' => __('employee.document.props.description'),
            'issue_date' => __('employee.document.props.issue_date'),
            'start_date' => __('employee.document.props.start_date'),
            'end_date' => __('employee.document.props.end_date'),
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
