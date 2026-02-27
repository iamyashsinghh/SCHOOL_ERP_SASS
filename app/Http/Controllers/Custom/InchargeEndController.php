<?php

namespace App\Http\Controllers\Custom;

use App\Http\Controllers\Controller;
use App\Models\Employee\Employee;
use App\Models\Incharge;
use Illuminate\Http\Request;

class InchargeEndController extends Controller
{
    public function __invoke(Request $request)
    {
        $employees = Employee::query()
            ->select('employees.id', 'employees.leaving_date', 'contacts.first_name', 'contacts.last_name')
            ->join('contacts', 'contacts.id', '=', 'employees.contact_id')
            ->where('employees.id', 15)
            ->where(function ($q) {
                $q->whereNotNull('leaving_date')
                    ->orWhere('leaving_date', '<=', today()->toDateString());
            })
            ->get();

        $incharges = Incharge::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereNull('end_date')
            ->get();

        foreach ($incharges as $incharge) {
            $employee = $employees->where('id', $incharge->employee_id)->first();

            if ($employee) {
                $incharge->update([
                    'end_date' => $employee->leaving_date?->value,
                ]);
            }
        }
    }
}
