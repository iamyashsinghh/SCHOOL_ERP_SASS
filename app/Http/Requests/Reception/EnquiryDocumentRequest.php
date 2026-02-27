<?php

namespace App\Http\Requests\Reception;

use App\Enums\OptionType;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Option;
use App\Models\Reception\Enquiry;
use App\Rules\SafeRegex;
use Illuminate\Foundation\Http\FormRequest;

class EnquiryDocumentRequest extends FormRequest
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
            'type' => 'required',
            'title' => 'nullable|min:2|max:100',
            'number' => 'nullable|min:2|max:100',
            'description' => 'nullable|min:2|max:1000',
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
            $enquiryUuid = $this->route('enquiry');
            $documentUuid = $this->route('document');

            $enquiry = Enquiry::query()
                ->whereUuid($enquiryUuid)
                ->firstOrFail();

            $documentType = Option::query()
                ->byTeam()
                ->whereIn('type', [OptionType::DOCUMENT_TYPE, OptionType::STUDENT_DOCUMENT_TYPE])
                ->whereUuid($this->type)
                ->getOrFail(__('student.document_type.document_type'), 'type');

            if ($documentType->getMeta('has_expiry_date')) {
                // if (empty($this->start_date)) {
                //     $validator->errors()->add('start_date', trans('validation.required', ['attribute' => __('student.document.props.start_date')]));
                // }

                if (empty($this->end_date)) {
                    $validator->errors()->add('end_date', trans('validation.required', ['attribute' => __('student.document.props.end_date')]));
                }
            }

            if ($documentType->getMeta('has_number')) {
                if (empty($this->number)) {
                    $validator->errors()->add('number', trans('validation.required', ['attribute' => __('student.document.props.number')]));
                }

                $validPattern = $documentType->getMeta('number_format');
                if ($validPattern && SafeRegex::isValidRegex(SafeRegex::prepare($validPattern))) {
                    $pattern = SafeRegex::prepare($validPattern);
                    if (! preg_match($pattern, $this->number)) {
                        $validator->errors()->add('number', trans('validation.regex', ['attribute' => __('student.document.props.number')]));
                    }
                }
            }

            $existingDocument = Document::whereHasMorph(
                'documentable', [Contact::class],
                function ($q) use ($enquiry) {
                    $q->whereId($enquiry->contact_id);
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
                $validator->errors()->add('title', trans('validation.unique', ['attribute' => __('student.document.props.title')]));
            }

            $this->merge([
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
            'type' => __('student.document_type.document_type'),
            'title' => __('student.document.props.title'),
            'number' => __('student.document.props.number'),
            'description' => __('student.document.props.description'),
            'issue_date' => __('student.document.props.issue_date'),
            'start_date' => __('student.document.props.start_date'),
            'end_date' => __('student.document.props.end_date'),
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
