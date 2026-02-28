<?php

namespace App\Http\Requests\Transport\Vehicle;

use App\Enums\OptionType;
use App\Models\Tenant\Media;
use App\Models\Tenant\Option;
use App\Models\Tenant\Transport\Vehicle\ExpenseRecord;
use App\Models\Tenant\Transport\Vehicle\Vehicle;
use Illuminate\Foundation\Http\FormRequest;

class ExpenseRecordRequest extends FormRequest
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
            'date' => 'required|date_format:Y-m-d',
            'type' => 'required|uuid',
            'quantity' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|min:2|max:20',
            'price_per_unit' => 'nullable|numeric|min:0',
            'amount' => 'nullable|numeric|min:0',
            'log' => 'required|integer|min:0',
            'reminder.date' => 'nullable|date_format:Y-m-d|after:today',
            'reminder.notify_before' => 'nullable|integer|min:0',
            'reminder.users' => 'required_with:reminder.date|array',
            'reminder.note' => 'nullable|min:2|max:1000',
            'remarks' => 'nullable|min:2|max:1000',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $mediaModel = (new ExpenseRecord)->getModelName();

            $vehicleExpenseRecordUuid = $this->route('expense_record');

            $vehicle = Vehicle::query()
                ->byTeam()
                ->whereUuid($this->vehicle)
                ->getOrFail(__('transport.vehicle.vehicle'), 'vehicle');

            $type = Option::query()
                ->byTeam()
                ->where('type', OptionType::VEHICLE_EXPENSE_TYPE)
                ->whereUuid($this->type)
                ->getOrFail(__('transport.vehicle.expense_type.expense_type'), 'type');

            if ($type->getMeta('has_quantity') && empty($this->quantity)) {
                $validator->errors()->add('quantity', __('validation.required', ['attribute' => __('transport.vehicle.expense_record.props.quantity')]));
            }
            if ($type->getMeta('has_quantity') && empty($this->unit)) {
                $validator->errors()->add('unit', __('validation.required', ['attribute' => __('transport.vehicle.expense_record.props.unit')]));
            }
            if ($type->getMeta('has_quantity') && empty($this->price_per_unit)) {
                $validator->errors()->add('price_per_unit', __('validation.required', ['attribute' => __('transport.vehicle.expense_record.props.price_per_unit')]));
            }

            $attachedMedia = Media::whereModelType($mediaModel)
                ->whereToken($this->media_token)
                // ->where('meta->hash', $this->media_hash)
                ->where('meta->is_temp_deleted', false)
                ->where(function ($q) use ($vehicleExpenseRecordUuid) {
                    $q->whereStatus(0)
                        ->when($vehicleExpenseRecordUuid, function ($q) {
                            $q->orWhere('status', 1);
                        });
                })
                ->exists();

            if (! $attachedMedia && $type?->getMeta('is_document_required')) {
                $validator->errors()->add('media', trans('validation.required', ['attribute' => __('general.attachment')]));
            }

            $this->merge([
                'type_id' => $type->id,
                'vehicle_id' => $vehicle->id,
                'has_quantity' => (bool) $type->getMeta('has_quantity'),
                'has_reminder' => (bool) $type->getMeta('has_reminder'),
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
            'type' => __('transport.vehicle.expense_type.expense_type'),
            'date' => __('transport.vehicle.expense_record.props.date'),
            'quantity' => __('transport.vehicle.expense_record.props.quantity'),
            'unit' => __('transport.vehicle.expense_record.props.unit'),
            'price_per_unit' => __('transport.vehicle.expense_record.props.price_per_unit'),
            'amount' => __('transport.vehicle.expense_record.props.amount'),
            'log' => __('transport.vehicle.expense_record.props.log'),
            'reminder.date' => __('reminder.props.date'),
            'reminder.notify_before' => __('reminder.props.notify_before'),
            'reminder.users' => __('reminder.props.users'),
            'reminder.note' => __('reminder.props.note'),
            'remarks' => __('transport.vehicle.expense_record.props.remarks'),
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
