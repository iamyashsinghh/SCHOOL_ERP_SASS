<?php

namespace App\Http\Resources;

use App\Models\Tenant\Employee\Employee;
use Illuminate\Http\Resources\Json\JsonResource;

class ReminderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'sub_title' => $this->getMeta('sub_title'),
            'description' => $this->description,
            'date' => $this->date,
            'users_count' => $this->users_count,
            'notify_before' => $this->notify_before,
            'note' => $this->note,
            'is_owner' => $this->user_id === auth()->id(),
            'is_editable' => $this->is_editable,
            'users' => $this->getUsers(),
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }

    private function getUsers()
    {
        $employees = Employee::query()
            ->select('employees.id', 'employees.uuid', 'employees.code_number', \DB::raw('REGEXP_REPLACE(CONCAT_WS(" ", first_name, middle_name, third_name, last_name), "[[:space:]]+", " ") as name'))
            ->join('contacts', 'employees.contact_id', '=', 'contacts.id')
            ->whereIn('contacts.user_id', $this->users->pluck('id'))
            ->get();

        return $employees->map(function ($employee) {
            return [
                'uuid' => $employee->uuid,
                'name' => $employee->name,
                'code_number' => $employee->code_number,
            ];
        });
    }
}
