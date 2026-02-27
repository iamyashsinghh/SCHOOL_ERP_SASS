<?php

namespace App\Http\Requests\Transport\Vehicle;

use App\Models\Finance\Ledger;
use App\Models\Transport\Vehicle\FuelRecord;
use App\Models\Transport\Vehicle\Vehicle;
use Illuminate\Foundation\Http\FormRequest;

class FuelRecordRequest extends FormRequest
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
            'vendor' => 'nullable|uuid',
            'quantity' => 'required|numeric|min:0.01',
            'price_per_unit' => 'required|numeric|min:0',
            'previous_log' => 'nullable|numeric|min:0|lt:log',
            'log' => 'required|numeric|min:0',
            'date' => 'required|date|before_or_equal:today',
            'bill_number' => 'nullable|min:1|max:100',
            'remarks' => 'nullable|min:2|max:1000',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $mediaModel = (new FuelRecord)->getModelName();

            $vehicleFuelRecordUuid = $this->route('fuel_record');

            $vehicle = Vehicle::query()
                ->byTeam()
                ->whereUuid($this->vehicle)
                ->getOrFail(__('transport.vehicle.vehicle'), 'vehicle');

            $vendor = $this->vendor ? Ledger::query()
                ->byTeam()
                ->subType('vendor')
                ->where('uuid', $this->vendor)
                ->getOrFail(__('inventory.vendor.vendor'), 'vendor') : null;

            $existingRecord = FuelRecord::query()
                ->when($vehicleFuelRecordUuid, function ($q) use ($vehicleFuelRecordUuid) {
                    $q->where('uuid', '!=', $vehicleFuelRecordUuid);
                })
                ->where('vehicle_id', $vehicle->id)
                ->where('date', $this->date)
                ->where('quantity', $this->quantity)
                ->exists();

            if ($existingRecord) {
                $validator->errors()->add('vehicle', __('transport.vehicle.fuel_record.record_exists'));
            }

            $log = $this->previous_log ?: $this->log;

            $previousRecord = FuelRecord::query()
                ->when($vehicleFuelRecordUuid, function ($q) use ($vehicleFuelRecordUuid) {
                    $q->where('uuid', '!=', $vehicleFuelRecordUuid);
                })
                ->where('vehicle_id', $vehicle->id)
                ->where('date', '<=', $this->date)
                ->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($previousRecord && $previousRecord->log > $log) {
                if ($this->previous_log) {
                    $validator->errors()->add('previous_log', __('transport.vehicle.fuel_record.must_be_gt_previous_log', ['attribute' => $previousRecord->log, 'date' => $previousRecord->date->formatted]));
                } else {
                    $validator->errors()->add('log', __('transport.vehicle.fuel_record.must_be_gt_previous_log', ['attribute' => $previousRecord->log, 'date' => $previousRecord->date->formatted]));
                }
            }

            $nextRecord = FuelRecord::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('date', '>=', $this->date)
                ->orderBy('date', 'asc')
                ->orderBy('created_at', 'asc')
                ->first();

            if ($nextRecord && $nextRecord->log <= $log) {
                if ($this->previous_log) {
                    $validator->errors()->add('previous_log', __('transport.vehicle.fuel_record.must_be_lt_next_log', ['attribute' => $nextRecord->log, 'date' => $nextRecord->date->formatted]));
                } else {
                    $validator->errors()->add('log', __('transport.vehicle.fuel_record.must_be_lt_next_log', ['attribute' => $nextRecord->log, 'date' => $nextRecord->date->formatted]));
                }
            }

            if ($this->previous_log) {
                $distanceCovered = $this->log - $this->previous_log;
                $mileage = round($distanceCovered / $this->quantity, 2);

                $this->merge([
                    'distance_covered' => $distanceCovered,
                    'mileage' => $mileage,
                ]);
            }

            $this->merge([
                'fuel_type' => $vehicle->fuel_type->value,
                'vehicle_id' => $vehicle->id,
                'vendor_id' => $vendor?->id,
                'ledger' => $vendor,
                'cost' => round($this->quantity * $this->price_per_unit, 2),
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
            'vendor' => __('inventory.vendor.vendor'),
            'quantity' => __('transport.vehicle.fuel_record.props.quantity'),
            'price_per_unit' => __('transport.vehicle.fuel_record.props.price_per_unit'),
            'previous_log' => __('transport.vehicle.fuel_record.props.previous_log'),
            'log' => __('transport.vehicle.fuel_record.props.log'),
            'date' => __('transport.vehicle.fuel_record.props.date'),
            'bill_number' => __('transport.vehicle.fuel_record.props.bill_number'),
            'remarks' => __('transport.vehicle.fuel_record.props.remarks'),
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
