<?php

namespace App\Http\Controllers\Custom;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Student\Student;
use App\Models\Tenant\User;
use Illuminate\Http\Request;

class ForceChangePasswordController extends Controller
{
    public function __construct()
    {
        $this->middleware('super.admin');
    }

    public function __invoke(Request $request)
    {
        $date = today()->toDateString();
        $skip = (int) $request->input('skip', 0);
        $limit = (int) $request->input('limit', 500);
        $type = $request->input('type');
        $passwordType = $request->input('password_type');

        if ($limit > 500) {
            return view('custom.force-change-password', ['error' => 'Limit cannot be more than 500.', 'rows' => []]);
        }

        if (! $type || ! $passwordType) {
            return view('custom.force-change-password', ['error' => 'Type and Password Type are required.', 'rows' => []]);
        }

        if ($type == 'student') {
            $records = Student::query()
                ->byPeriod()
                ->select('students.id', 'users.id as user_id', 'users.username', 'contacts.birth_date', \DB::raw('REGEXP_REPLACE(CONCAT_WS(" ", first_name, middle_name, third_name, last_name), "[[:space:]]+", " ") as name'), 'contacts.contact_number', 'batches.name as batch_name', 'courses.name as course_name', 'admissions.code_number')
                ->leftJoin('contacts', 'students.contact_id', '=', 'contacts.id')
                ->leftJoin('users', 'contacts.user_id', '=', 'users.id')
                ->leftJoin('batches', 'students.batch_id', '=', 'batches.id')
                ->leftJoin('courses', 'batches.course_id', '=', 'courses.id')
                ->leftJoin('admissions', 'students.admission_id', '=', 'admissions.id')
                ->whereNull('students.end_date')
                ->orderBy('students.id')
                ->skip($skip)
                ->limit($limit)
                ->get();
        } elseif ($type == 'employee') {
            $records = Employee::query()
                ->select('employees.id', 'employees.code_number', 'users.id as user_id', 'users.username', 'contacts.birth_date', \DB::raw('REGEXP_REPLACE(CONCAT_WS(" ", first_name, middle_name, third_name, last_name), "[[:space:]]+", " ") as name'), 'contacts.contact_number', 'designations.name as designation')
                ->leftJoin('employee_records', function ($join) use ($date) {
                    $join->on('employees.id', '=', 'employee_records.employee_id')
                        ->on('start_date', '=', \DB::raw("(select start_date from employee_records where employees.id = employee_records.employee_id and start_date <= '".$date."' order by start_date desc limit 1)"))
                        ->join('designations', 'employee_records.designation_id', '=', 'designations.id');
                })
                ->leftJoin('contacts', 'employees.contact_id', '=', 'contacts.id')
                ->leftJoin('users', 'contacts.user_id', '=', 'users.id')
                ->whereNull('employees.leaving_date')
                ->orderBy('employees.id')
                ->skip($skip)
                ->limit($limit)
                ->get();
        }

        $users = User::whereHas('roles', function ($query) use ($type) {
            $query->when($type == 'student', function ($q) {
                $q->where('name', 'student');
            })->when($type == 'employee', function ($q) {
                $q->whereNotIn('name', ['admin', 'guardian', 'student']);
            });
        })->whereIn('id', $records->pluck('user_id'));

        // if ($type) {
        // dd($records->pluck('user_id'));
        //     dd($users->get());
        // }

        $rows = [];
        $users->chunk(100, function ($usersChunk) use ($records, &$rows, $passwordType) {
            foreach ($usersChunk as $user) {
                $record = $records->firstWhere('user_id', $user->id);
                if ($record) {
                    if ($passwordType == 'birth_date') {
                        $newPassword = date('Ymd', strtotime($record->birth_date));
                    } elseif ($passwordType == 'contact_number') {
                        $newPassword = $record->contact_number;
                    } else {
                        $newPassword = rand(100000, 999999);
                    }
                    $rows[] = [
                        ...$record->toArray(),
                        'password' => $newPassword,
                    ];
                    $user->password = bcrypt($newPassword);
                    $user->setMeta(['force_change_password' => true]);
                    $user->save();
                }
            }
        });

        return view('custom.force-change-password', compact('rows'));
    }
}
