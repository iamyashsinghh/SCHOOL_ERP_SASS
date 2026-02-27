<?php

namespace App\Http\Requests\Student;

use App\Enums\Finance\PaymentStatus;
use App\Enums\Student\RegistrationStatus;
use App\Enums\Student\StudentType;
use App\Enums\Transport\Direction;
use App\Models\Finance\FeeConcession;
use App\Models\Finance\FeeStructure;
use App\Models\Transport\Circle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

class RegistrationAssignFeeRequest extends FormRequest
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
        $rules = [
            'fee_concession' => 'nullable|uuid',
            'student_type' => ['required', new Enum(StudentType::class)],
            'transport_circle' => 'nullable|uuid',
            'direction' => ['nullable', new Enum(Direction::class)],
        ];

        if (! $this->assign_fee_later) {
            $rules['fee_structure'] = 'required|uuid';
        }

        return $rules;
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $registration = $this->route('registration');

            if ($registration->status != RegistrationStatus::PENDING) {
                $validator->errors()->add('status', trans('general.errors.invalid_action'));
            }

            if ($registration->fee->value > 0 && $registration->payment_status != PaymentStatus::PAID) {
                throw ValidationException::withMessages(['message' => trans('student.registration.fee_unpaid')]);
            }

            $feeStructure = ! $this->assign_fee_later ? FeeStructure::query()
                ->byPeriod($registration->period_id)
                ->whereUuid($this->fee_structure)
                ->getOrFail(trans('finance.fee_structure.fee_structure')) : null;

            $feeConcession = $this->fee_concession ? FeeConcession::query()
                ->byPeriod($registration->period_id)
                ->whereUuid($this->fee_concession)
                ->getOrFail(trans('finance.fee_concession.fee_concession')) : null;

            $transportCircle = $this->transport_circle ? Circle::query()
                ->byPeriod($registration->period_id)
                ->whereUuid($this->transport_circle)
                ->getOrFail(trans('transport.circle.circle')) : null;

            if ($this->transport_circle && empty($this->direction)) {
                throw ValidationException::withMessages(['direction' => trans('validation.required', ['attribute' => trans('transport.circle.direction')])]);
            }

            $this->merge([
                'fee_structure_uuid' => $feeStructure?->uuid,
                'fee_concession_uuid' => $feeConcession?->uuid,
                'transport_circle_uuid' => $transportCircle?->uuid,
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
        ];
    }
}
