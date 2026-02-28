<?php

namespace App\Http\Requests\Transport\Vehicle;

use App\Models\Tenant\Document;
use App\Models\Tenant\Media;
use App\Models\Tenant\Option;
use App\Models\Tenant\Transport\Vehicle\Vehicle;
use App\Rules\SafeRegex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class DocumentRequest extends FormRequest
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
            'vehicle' => 'required',
            'type' => 'required',
            'title' => 'nullable|min:2|max:200',
            'number' => 'nullable|min:2|max:100',
            'description' => 'nullable|min:2|max:1000',
            'start_date' => 'required|date',
            'issue_date' => 'nullable|date_format:Y-m-d|before_or_equal:start_date',
            'end_date' => 'nullable|date|after:start_date',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $mediaModel = (new Document)->getModelName();

            $vehicleDocumentUuid = $this->route('document');

            $documentType = Option::query()
                ->whereType('vehicle_document_type')
                ->whereUuid($this->type)
                ->getOrFail(__('transport.vehicle.document.props.type'), 'type');

            if ($documentType->getMeta('has_expiry_date')) {
                // if (empty($this->start_date)) {
                //     $validator->errors()->add('start_date', trans('validation.required', ['attribute' => __('transport.vehicle.document.props.start_date')]));
                // }

                if (empty($this->end_date)) {
                    $validator->errors()->add('end_date', trans('validation.required', ['attribute' => __('transport.vehicle.document.props.end_date')]));
                }
            }

            if ($documentType->getMeta('has_number')) {
                if (empty($this->number)) {
                    $validator->errors()->add('number', trans('validation.required', ['attribute' => __('transport.vehicle.document.props.number')]));
                }

                $validPattern = $documentType->getMeta('number_format');
                if ($validPattern && SafeRegex::isValidRegex(SafeRegex::prepare($validPattern))) {
                    $pattern = SafeRegex::prepare($validPattern);
                    if (! preg_match($pattern, $this->number)) {
                        $validator->errors()->add('number', trans('validation.regex', ['attribute' => __('transport.vehicle.document.props.number')]));
                    }
                }
            }

            $vehicle = Vehicle::query()
                ->byTeam()
                ->whereUuid($this->vehicle)
                ->getOrFail(__('transport.vehicle.vehicle'), 'vehicle');

            $existingDocument = Document::query()
                ->whereHasMorph(
                    'documentable',
                    [Vehicle::class],
                    function (Builder $query) {
                        $query->whereUuid($this->vehicle);
                    }
                )
                ->when($vehicleDocumentUuid, function ($q, $vehicleDocumentUuid) {
                    $q->where('uuid', '!=', $vehicleDocumentUuid);
                })
                ->when($documentType->getMeta('has_number'), function ($q) {
                    $q->whereNumber($this->number);
                }, function ($q) {
                    $q->whereTitle($this->title);
                })
                ->exists();

            if ($existingDocument) {
                $validator->errors()->add('title', trans('validation.unique', ['attribute' => __('transport.vehicle.document.props.title')]));
            }

            $attachedMedia = Media::whereModelType($mediaModel)
                ->whereToken($this->media_token)
                // ->where('meta->hash', $this->media_hash)
                ->where('meta->is_temp_deleted', false)
                ->where(function ($q) use ($vehicleDocumentUuid) {
                    $q->whereStatus(0)
                        ->when($vehicleDocumentUuid, function ($q) {
                            $q->orWhere('status', 1);
                        });
                })
                ->exists();

            if (! $attachedMedia && $documentType->getMeta('is_document_required')) {
                $validator->errors()->add('media', trans('validation.required', ['attribute' => __('general.attachment')]));
            }

            $this->merge([
                'type_id' => $documentType->id,
                'vehicle_id' => $vehicle->id,
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
            'vehicle' => __('transport.vehicle.vehicle'),
            'type' => __('transport.vehicle.document.props.type'),
            'title' => __('transport.vehicle.document.props.title'),
            'number' => __('transport.vehicle.document.props.number'),
            'description' => __('transport.vehicle.document.props.description'),
            'issue_date' => __('transport.vehicle.document.props.issue_date'),
            'start_date' => __('transport.vehicle.document.props.start_date'),
            'end_date' => __('transport.vehicle.document.props.end_date'),
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
