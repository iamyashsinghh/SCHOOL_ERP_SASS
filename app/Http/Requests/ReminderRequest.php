<?php

namespace App\Http\Requests;

use App\Models\Tenant\Employee\Employee;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class ReminderRequest extends FormRequest
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
            'title' => ['required', 'min:2', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d', 'after:today'],
            'notify_before' => ['required', 'integer', 'min:0'],
            'users' => ['required', 'array'],
            'note' => ['nullable', 'max:1000'],
        ];
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $uuid = $this->route('reminder.uuid');

            $employees = Employee::query()
                ->select('employees.id', 'contacts.id as contact_id', 'contacts.user_id')
                ->join('contacts', 'employees.contact_id', '=', 'contacts.id')
                ->whereNotNull('contacts.user_id')
                ->whereIn('employees.uuid', $this->users)
                ->get();

            if (! $employees->count()) {
                $validator->errors()->add('users', trans('global.could_not_find', ['attribute' => __('user.user')]));
            }

            $date = Carbon::parse($this->date);

            if (abs($date->diffInDays(today())) < $this->notify_before) {
                $validator->errors()->add('notify_before', trans('reminder.could_not_notify_in_past'));
            }

            $this->merge([
                'employees' => $employees,
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
            'title' => __('reminder.props.title'),
            'date' => __('reminder.props.date'),
            'notify_before' => __('reminder.props.notify_before'),
            'users' => __('reminder.props.users'),
            'note' => __('reminder.props.note'),
        ];
    }
}
