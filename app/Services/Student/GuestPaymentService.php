<?php

namespace App\Services\Student;

use App\Actions\Finance\GetPaymentGateway;
use App\Http\Resources\Academic\CourseForGuestResource;
use App\Http\Resources\Academic\PeriodForGuestResource;
use App\Http\Resources\TeamForGuestResource;
use App\Models\Tenant\Academic\Course;
use App\Models\Tenant\Academic\Period;
use App\Models\Tenant\Finance\FeeHead;
use App\Models\Tenant\Student\Student;
use App\Models\Tenant\Team;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GuestPaymentService
{
    public function preRequisite()
    {
        $types = [
            ['label' => trans('global.existing', ['attribute' => trans('student.student')]), 'value' => 'existing'],
            // ['label' => trans('global.new', ['attribute' => trans('student.student')]), 'value' => 'new'],
        ];

        $teams = TeamForGuestResource::collection(Team::query()
            ->get());

        $instruction = nl2br(config('config.feature.guest_payment_instruction'));

        return compact('types', 'teams', 'instruction');
    }

    public function getPeriods(Team $team)
    {
        $periods = PeriodForGuestResource::collection(Period::query()
            ->where('team_id', $team->id)
            ->get());

        $paymentGateways = (new GetPaymentGateway)->execute($team->id);

        return compact('periods', 'paymentGateways');
    }

    public function getCourses(Team $team, string $uuid)
    {
        $period = Period::query()
            ->where('team_id', $team->id)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $courses = CourseForGuestResource::collection(Course::query()
            ->with('batches')
            ->whereHas('division', function ($q) use ($period) {
                $q->where('period_id', $period->id);
            })
            ->get());

        $feeHeads = FeeHead::query()
            ->with('group')
            ->whereHas('group', function ($q) use ($period) {
                $q->where('period_id', $period->id);
            })
            ->get()
            ->map(function ($feeHead) {
                return [
                    'uuid' => $feeHead->uuid,
                    'name' => $feeHead->name,
                    'group' => $feeHead->group->name,
                ];
            });

        return compact('courses', 'feeHeads');
    }

    public function getStudent(Request $request)
    {
        $request->validate([
            'team' => 'required|uuid',
            'period' => 'required|uuid',
            'course' => 'required|uuid',
            'code_number' => 'required',
            'birth_date' => 'required|date_format:Y-m-d',
        ]);

        $codeNumber = $request->code_number;

        $students = Student::query()
            ->select('students.id', 'students.uuid', 'students.contact_id', 'students.start_date', 'admissions.code_number', 'courses.name as course_name', 'batches.name as batch_name', 'contacts.team_id', 'students.period_id', \DB::raw('REGEXP_REPLACE(CONCAT_WS(" ", first_name, middle_name, third_name, last_name), "[[:space:]]+", " ") as name'))
            ->join('periods', 'periods.id', '=', 'students.period_id')
            ->join('batches', 'batches.id', '=', 'students.batch_id')
            ->join('courses', 'courses.id', '=', 'batches.course_id')
            ->join('admissions', 'admissions.id', '=', 'students.admission_id')
            ->join('contacts', 'contacts.id', '=', 'students.contact_id')
            ->join('teams', 'teams.id', '=', 'contacts.team_id')
            ->where('periods.uuid', $request->period)
            ->where('courses.uuid', $request->course)
            ->where(function ($q) use ($codeNumber) {
                $q->where('admissions.code_number', $codeNumber)
                    ->orWhere(\DB::raw('REGEXP_REPLACE(CONCAT_WS(" ", first_name, middle_name, third_name, last_name), "[[:space:]]+", " ")'), '=', "{$codeNumber}");
            })
            ->where('contacts.birth_date', $request->birth_date)
            ->where('teams.uuid', $request->team)
            ->get();

        if ($students->isEmpty()) {
            throw ValidationException::withMessages(['message' => trans('global.could_not_find', ['attribute' => trans('student.student')])]);
        }

        if ($students->count() > 1) {
            throw ValidationException::withMessages(['message' => trans('student.multiple_records_found')]);
        }

        return $students->first();
    }
}
