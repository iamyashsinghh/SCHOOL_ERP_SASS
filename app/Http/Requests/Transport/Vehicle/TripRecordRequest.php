<?php

namespace App\Http\Requests\Transport\Vehicle;

use App\Enums\OptionType;
use App\Models\Tenant\Option;
use App\Models\Tenant\Transport\Vehicle\TripRecord;
use App\Models\Tenant\Transport\Vehicle\Vehicle;
use Illuminate\Foundation\Http\FormRequest;

class TripRecordRequest extends FormRequest
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
            'purpose' => 'nullable|uuid',
            'log' => 'required|numeric|min:0',
            'date' => 'required|date|before_or_equal:today',
            'remarks' => 'nullable|min:2|max:1000',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $mediaModel = (new TripRecord)->getModelName();

            $vehicleTripRecordUuid = $this->route('trip_record');

            $vehicle = Vehicle::query()
                ->byTeam()
                ->whereUuid($this->vehicle)
                ->getOrFail(__('transport.vehicle.vehicle'), 'vehicle');

            $purpose = $this->purpose ? Option::query()
                ->byTeam()
                ->whereType(OptionType::VEHICLE_TRIP_PURPOSE->value)
                ->whereUuid($this->purpose)
                ->getOrFail(__('transport.vehicle.trip_purpose.trip_purpose'), 'purpose') : null;

            $previousRecord = TripRecord::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('date', '<=', $this->date)
                ->when($vehicleTripRecordUuid, function ($q) use ($vehicleTripRecordUuid) {
                    $q->where('uuid', '!=', $vehicleTripRecordUuid);
                })
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($previousRecord && $previousRecord->log >= $this->log) {
                $validator->errors()->add('log', __('transport.vehicle.trip_record.log_should_be_greater_than_previous_record', ['attribute' => $previousRecord->log]));
            }

            $this->merge([
                'vehicle_id' => $vehicle->id,
                'purpose_id' => $purpose?->id,
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
            'purpose' => __('transport.vehicle.trip_purpose.trip_purpose'),
            'log' => __('transport.vehicle.trip_record.props.log'),
            'date' => __('transport.vehicle.trip_record.props.date'),
            'remarks' => __('transport.vehicle.trip_record.props.remarks'),
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
