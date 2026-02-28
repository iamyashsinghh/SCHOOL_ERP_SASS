<?php

namespace App\Http\Requests\Transport\Vehicle;

use App\Enums\OptionType;
use App\Models\Tenant\Option;
use App\Models\Tenant\Transport\Vehicle\CaseRecord;
use App\Models\Tenant\Transport\Vehicle\Vehicle;
use Illuminate\Foundation\Http\FormRequest;

class CaseRecordRequest extends FormRequest
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
            'type' => 'required|uuid',
            'title' => 'required|min:2|max:100',
            'date' => 'required|date_format:Y-m-d',
            'penalty' => 'required|numeric|min:0',
            'location' => 'required|min:2|max:100',
            'description' => 'required|min:2|max:1000',
            'action' => 'required|min:2|max:1000',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $mediaModel = (new CaseRecord)->getModelName();

            $vehicleCaseRecordUuid = $this->route('case_record');

            $vehicle = Vehicle::query()
                ->byTeam()
                ->whereUuid($this->vehicle)
                ->getOrFail(__('transport.vehicle.vehicle'), 'vehicle');

            $type = Option::query()
                ->byTeam()
                ->where('type', OptionType::VEHICLE_CASE_TYPE)
                ->whereUuid($this->type)
                ->getOrFail(__('transport.vehicle.case_type.case_type'), 'type');

            $this->merge([
                'type_id' => $type->id,
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
            'type' => __('transport.vehicle.case_type.case_type'),
            'title' => __('transport.vehicle.case_record.props.title'),
            'date' => __('transport.vehicle.case_record.props.date'),
            'penalty' => __('transport.vehicle.case_record.props.penalty'),
            'location' => __('transport.vehicle.case_record.props.location'),
            'description' => __('transport.vehicle.case_record.props.description'),
            'action' => __('transport.vehicle.case_record.props.action'),
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
