<?php

namespace App\Http\Requests\Employee;

use App\Models\Tenant\Account;
use App\Models\Tenant\Contact;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Media;
use Illuminate\Foundation\Http\FormRequest;

class AccountsRequest extends FormRequest
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
            'employee' => 'required|uuid',
            'name' => 'required|min:2|max:100',
            'alias' => 'nullable|min:2|max:100',
            'number' => 'required|min:2|max:100',
            'bank_name' => 'required|min:2|max:100',
            'branch_name' => 'required|min:2|max:100',
        ];

        if (config('config.finance.enable_bank_code1')) {
            $rules['bank_code1'] = [config('config.finance.is_bank_code1_required') ? 'required' : 'nullable', 'min:2', 'max:100'];
        }

        if (config('config.finance.enable_bank_code2')) {
            $rules['bank_code2'] = [config('config.finance.is_bank_code2_required') ? 'required' : 'nullable', 'min:2', 'max:100'];
        }

        if (config('config.finance.enable_bank_code3')) {
            $rules['bank_code3'] = [config('config.finance.is_bank_code3_required') ? 'required' : 'nullable', 'min:2', 'max:100'];
        }

        return $rules;
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $accountUuid = $this->route('account');

            $mediaModel = (new Account)->getModelName();

            $employee = Employee::query()
                ->summary()
                ->byTeam()
                ->filterAccessible()
                ->where('employees.uuid', $this->employee)
                ->getOrFail(__('employee.employee'), 'employee');

            $existingAccount = Account::whereHasMorph(
                'accountable', [Contact::class],
                function ($q) use ($employee) {
                    $q->whereId($employee->contact_id);
                }
            )
                ->when($accountUuid, function ($q, $accountUuid) {
                    $q->where('uuid', '!=', $accountUuid);
                })
                ->whereNumber($this->number)
                ->exists();

            if ($existingAccount) {
                $validator->errors()->add('number', trans('validation.unique', ['attribute' => __('finance.account.props.number')]));
            }

            $attachedMedia = Media::whereModelType($mediaModel)
                ->whereToken($this->media_token)
                // ->where('meta->hash', $this->media_hash)
                ->where('meta->is_temp_deleted', false)
                ->where(function ($q) use ($accountUuid) {
                    $q->whereStatus(0)
                        ->when($accountUuid, function ($q) {
                            $q->orWhere('status', 1);
                        });
                })
                ->exists();

            if (! $attachedMedia) {
                $validator->errors()->add('media', trans('validation.required', ['attribute' => __('general.attachment')]));
            }

            $this->merge([
                'contact_id' => $employee->contact_id,
                'employee_id' => $employee->id,
                'user_id' => $employee->user_id,
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
            'employee' => __('employee.employee'),
            'name' => __('finance.account.props.name'),
            'alias' => __('finance.account.props.alias'),
            'number' => __('finance.account.props.number'),
            'bank_name' => __('finance.account.props.bank_name'),
            'branch_name' => __('finance.account.props.branch_name'),
            'bank_code1' => config('config.finance.bank_code1_label'),
            'bank_code2' => config('config.finance.bank_code2_label'),
            'bank_code3' => config('config.finance.bank_code3_label'),
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
