<?php

namespace App\Http\Requests\Student;

use App\Actions\Student\GetHeadWiseFee;
use App\Models\Finance\Ledger;
use App\Models\Finance\PaymentMethod;
use App\Models\Student\Fee;
use App\Models\Student\FeeRecord;
use App\Models\Student\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class MultiHeadWisePaymentRequest extends FormRequest
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
            'fee_heads' => 'required|array|min:1',
            'payments' => 'required|array|min:1',
            'payments.*.ledger' => 'required|uuid',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.payment_method' => 'required',
            'payments.*.payment_method.uuid' => 'required|uuid',
            'amount' => 'required|numeric|min:0.01',
        ];

        return $rules;
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $studentUuid = $this->route('student');

            $student = Student::query()
                ->select('id', 'period_id')
                ->byTeam()
                ->whereUuid($studentUuid)
                ->firstOrFail();

            $date = today()->toDateString();

            $fees = Fee::query()
                ->whereStudentId($student->id)
                ->get();

            $feeRecords = FeeRecord::query()
                ->with('head')
                ->whereIn('student_fee_id', $fees->pluck('id')->all())
                ->get();

            $feeHeads = (new GetHeadWiseFee)->execute(
                student: $student,
                fees: $fees,
                feeRecords: $feeRecords,
                date: $date,
            );

            $feeHeads = collect($feeHeads);

            $newFeeHeads = [];

            $feeHeadTotal = 0;
            foreach ($this->fee_heads as $index => $inputFeeHead) {
                if (Arr::get($inputFeeHead, 'is_selected')) {
                    $feeHead = $feeHeads->filter(function ($head) use ($inputFeeHead) {
                        return Arr::get($head, 'uuid') == Arr::get($inputFeeHead, 'uuid') || (! empty(Arr::get($inputFeeHead, 'default_fee_head')) && Arr::get($head, 'default_fee_head.value') == Arr::get($inputFeeHead, 'default_fee_head'));
                    })->first();

                    if (! $feeHead) {
                        $validator->errors()->add('fee_heads.'.$index.'.uuid', trans('global.could_not_find', ['attribute' => trans('finance.fee_head.fee_head')]));
                    } else {

                        $balance = Arr::get($feeHead, 'balance')?->value ?? 0;
                        $feeHeadAmount = Arr::get($inputFeeHead, 'payable_amount');
                        if ($balance < $feeHeadAmount) {
                            $validator->errors()->add('fee_heads.'.$index.'.payable_amount', trans('student.fee.could_not_make_excess_payment', ['attribute' => \Price::from($balance)->formatted]));
                        }

                        $newFeeHeads[] = [
                            'fee_head' => Arr::get($feeHead, 'uuid'),
                            'default_fee_head' => Arr::get($feeHead, 'default_fee_head')?->value,
                            'amount' => Arr::get($inputFeeHead, 'payable_amount'),
                        ];
                    }

                    $feeHeadTotal += Arr::get($inputFeeHead, 'payable_amount');
                }
            }

            if ($feeHeadTotal != $this->amount) {
                $validator->errors()->add('amount', trans('student.fee.total_mismatch'));
            }

            $this->merge([
                'fee_heads' => $newFeeHeads,
            ]);

            $paymentMethod = PaymentMethod::query()
                ->byTeam()
                ->where('is_payment_gateway', false)
                ->get();

            $ledger = Ledger::query()
                ->byTeam()
                ->subType('primary')
                ->get();

            $newPayments = [];
            $paymentAmount = 0;
            foreach ($this->payments as $index => $payment) {
                $paymentMethod = $paymentMethod->where('uuid', Arr::get($payment, 'payment_method.uuid'))->first();
                $ledger = $ledger->where('uuid', Arr::get($payment, 'ledger'))->first();

                if (! $paymentMethod) {
                    $validator->errors()->add('payments.'.$index.'.payment_method', trans('global.could_not_find', ['attribute' => trans('finance.payment_method.payment_method')]));
                }

                if (! $ledger) {
                    $validator->errors()->add('payments.'.$index.'.ledger', trans('global.could_not_find', ['attribute' => trans('finance.ledger.ledger')]));
                }

                $newPayments[] = [
                    'ledger_id' => $ledger?->id,
                    'payment_method_id' => $paymentMethod?->id,
                    'amount' => Arr::get($payment, 'amount'),
                ];

                $paymentAmount += Arr::get($payment, 'amount');
            }

            if ($paymentAmount != $this->amount) {
                $validator->errors()->add('amount', trans('student.fee.total_mismatch'));
            }

            $this->merge([
                'payments' => $newPayments,
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
            'fee_heads' => __('finance.fee_head.fee_head'),
            'payments' => __('finance.payment.payment'),
            'payments.*.ledger' => __('finance.ledger.ledger'),
            'payments.*.amount' => __('student.fee.props.amount'),
            'payments.*.payment_method' => __('finance.payment_method.payment_method'),
            'amount' => __('student.fee.props.amount'),
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
