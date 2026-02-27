<?php

namespace App\Http\Requests\Finance;

use App\Concerns\SimpleValidation;
use App\Enums\OptionType;
use App\Models\Employee\Employee;
use App\Models\Finance\Ledger;
use App\Models\Finance\PaymentMethod;
use App\Models\Option;
use App\Models\Student\Student;
use Illuminate\Foundation\Http\FormRequest;

class ReceiptRequest extends FormRequest
{
    use SimpleValidation;

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
            'type' => ['required', 'in:student,employee,other'],
            'primary_ledger' => 'required|uuid',
            'date' => 'required|date_format:Y-m-d',
            'category' => 'nullable|uuid',
            'secondary_ledger' => 'required|uuid',
            'amount' => 'required|numeric|min:0.01',
            'name' => 'required_if:type,other|min:2|max:100',
            'contact_number' => 'required_if:type,other|min:5|max:20',
            'payment_method' => 'required|uuid',
            'instrument_number' => 'nullable|max:20',
            'instrument_date' => 'nullable|date_format:Y-m-d',
            'clearing_date' => 'nullable|date_format:Y-m-d',
            'bank_detail' => 'nullable|min:2|max:100',
            'branch_detail' => 'nullable|min:1|max:100',
            'reference_number' => 'nullable|max:20',
            'card_provider' => 'nullable|min:1|max:100',
            'description' => 'nullable|min:2|max:1000',
            'remarks' => 'nullable|min:2|max:1000',
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $uuid = $this->route('transaction.uuid');

            $category = $this->category ? Option::query()
                ->byTeam()
                ->where('type', OptionType::TRANSACTION_CATEGORY)
                ->whereUuid($this->category)
                ->getOrFail(trans('finance.transaction.category.category'), 'category') : null;

            $paymentMethod = PaymentMethod::query()
                ->byTeam()
                ->where('is_payment_gateway', false)
                ->whereUuid($this->payment_method)
                ->getOrFail(trans('finance.payment_method.payment_method'), 'payment_method');

            $primaryLedger = Ledger::query()
                ->byTeam()
                ->subType('primary')
                ->whereUuid($this->primary_ledger)
                ->getOrFail(trans('finance.ledger.ledger'), 'primary_ledger');

            $secondaryLedger = Ledger::query()
                ->byTeam()
                ->subType('income')
                ->whereUuid($this->secondary_ledger)
                ->getOrFail(trans('finance.transaction.props.head'), 'secondary_ledger');

            $student = $this->type == 'student' ? Student::query()
                ->byTeam()
                ->whereUuid($this->student)
                ->getOrFail(trans('student.student'), 'student') : null;

            $employee = $this->type == 'employee' ? Employee::query()
                ->byTeam()
                ->whereUuid($this->employee)
                ->getOrFail(trans('employee.employee'), 'employee') : null;

            $paymentMethods[] = [
                'payment_method_id' => $paymentMethod?->id,
                'amount' => $this->amount,
                'details' => [
                    'instrument_number' => $this->instrument_number,
                    'instrument_date' => $this->instrument_date,
                    'clearing_date' => $this->clearing_date,
                    'bank_detail' => $this->bank_detail,
                    'branch_detail' => $this->branch_detail,
                    'reference_number' => $this->reference_number,
                    'card_provider' => $this->card_provider,
                ],
            ];

            $this->merge([
                'student_id' => $student?->id,
                'employee_id' => $employee?->id,
                'category_id' => $category?->id,
                'payment_methods' => $paymentMethods,
                'payment_method_code' => $paymentMethod->code,
                'primary_ledger' => $primaryLedger,
                'ledger_code' => $primaryLedger?->code,
                'secondary_ledger' => $secondaryLedger,
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
            'type' => __('finance.transaction.props.type'),
            'primary_ledger' => __('finance.ledger.ledger'),
            'date' => __('finance.transaction.props.date'),
            'category' => __('finance.transaction.category.category'),
            'secondary_ledger' => __('finance.ledger.ledger'),
            'amount' => __('finance.transaction.props.amount'),
            'name' => __('contact.props.name'),
            'contact_number' => __('contact.props.contact_number'),
            'student' => __('student.student'),
            'employee' => __('employee.employee'),
            'payment_method' => __('finance.payment_method.payment_method'),
            'instrument_number' => __('finance.transaction.props.instrument_number'),
            'instrument_date' => __('finance.transaction.props.instrument_date'),
            'clearing_date' => __('finance.transaction.props.clearing_date'),
            'bank_detail' => __('finance.transaction.props.bank_detail'),
            'branch_detail' => __('finance.transaction.props.branch_detail'),
            'reference_number' => __('finance.transaction.props.reference_number'),
            'card_provider' => __('finance.transaction.props.card_provider'),
            'description' => __('finance.transaction.props.description'),
            'remarks' => __('finance.transaction.props.remarks'),
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
