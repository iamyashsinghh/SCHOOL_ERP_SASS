<?php

namespace App\Http\Requests\Student;

use App\Enums\OptionType;
use App\Enums\Student\StudentType;
use App\Enums\Transport\Direction;
use App\Models\Tenant\Finance\FeeConcession;
use App\Models\Tenant\Option;
use App\Models\Tenant\Transport\Circle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

class FeeAllocationRequest extends FormRequest
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
            'students' => ['required', 'array'],
            'fee_concession' => 'nullable|uuid',
            'fee_concession_type' => 'nullable|uuid',
            'transport_circle' => 'nullable|uuid',
            'direction' => ['nullable', new Enum(Direction::class)],
            // 'student_type' => ['required', new Enum(StudentType::class)],
        ];

        // only require when fee structure is unassigned
        if ($this->existing_fee_structure == 'unassigned') {
            $rules['student_type'] = ['required', new Enum(StudentType::class)];
        }

        return $rules;
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $feeConcession = $this->fee_concession ? FeeConcession::query()
                ->byPeriod()
                ->whereUuid($this->fee_concession)
                ->getOrFail(trans('finance.fee_concession.fee_concession')) : null;

            $feeConcessionType = $this->fee_concession_type ? Option::query()
                ->byTeam()
                ->whereUuid($this->fee_concession_type)
                ->whereType(OptionType::FEE_CONCESSION_TYPE->value)
                ->getOrFail(trans('finance.fee_concession.type.type')) : null;

            $transportCircle = $this->transport_circle ? Circle::query()
                ->byPeriod()
                ->whereUuid($this->transport_circle)
                ->getOrFail(trans('transport.circle.circle')) : null;

            if ($this->transport_circle && empty($this->direction)) {
                throw ValidationException::withMessages(['direction' => trans('validation.required', ['attribute' => trans('transport.circle.direction')])]);
            }

            $this->merge([
                'fee_concession' => $feeConcession,
                'fee_concession_type' => $feeConcessionType,
                'transport_circle' => $transportCircle,
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
            'students' => __('student.student'),
            'fee_concession' => __('finance.fee_concession.fee_concession'),
            'fee_concession_type' => __('finance.fee_concession.type.type'),
            'transport_circle' => __('transport.circle.circle'),
            'direction' => __('transport.circle.direction'),
            'student_type' => __('student.props.type'),
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
            //
        ];
    }
}
