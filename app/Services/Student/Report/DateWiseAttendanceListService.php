<?php

namespace App\Services\Student\Report;

use App\Contracts\ListGenerator;
use App\Enums\OptionType;
use App\Enums\Student\AttendanceSession;
use App\Models\Academic\Batch;
use App\Models\Calendar\Holiday;
use App\Models\Option;
use App\Models\Student\Attendance;
use App\Models\Student\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DateWiseAttendanceListService extends ListGenerator
{
    protected $allowedSorts = ['strength', 'total'];

    protected $defaultSort = 'position';

    protected $defaultOrder = 'asc';

    public function getHeaders(Collection $attendanceTypes): array
    {
        $headers = [
            [
                'key' => 'course_batch',
                'label' => trans('academic.course.course'),
                'print_label' => 'course_batch',
                'print_sub_label' => 'incharge',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'strength',
                'label' => trans('academic.student_strength'),
                'sortable' => true,
                'visibility' => true,
            ],
        ];

        foreach ($attendanceTypes as $attendanceType) {
            $headers[] = [
                'key' => $attendanceType['key'],
                'label' => $attendanceType['label'],
                'print_label' => $attendanceType['key'],
                'sortable' => true,
                'visibility' => true,
            ];
        }

        $headers[] = [
            'key' => 'total',
            'label' => trans('general.total'),
            'sortable' => true,
            'visibility' => true,
        ];

        if (request()->ajax()) {
            $headers[] = $this->actionHeader;
        }

        return $headers;
    }

    public function filter(Request $request): array
    {
        $batches = Str::toArray($request->query('batches'));

        $date = $request->query('date', today()->toDateString());
        $status = $request->query('status', 'all');

        $batches = Batch::query()
            ->select('batches.id', 'batches.uuid', 'courses.name as course_name', 'batches.name as batch_name')
            ->join('courses', 'courses.id', '=', 'batches.course_id')
            ->byPeriod()
            ->filterAccessible()
            ->withCurrentIncharges()
            ->when($batches, function ($q) use ($batches) {
                $q->whereIn('batches.uuid', $batches);
            })
            ->orderBy('courses.position', 'asc')
            ->orderBy('batches.position', 'asc')
            ->get();

        $attendanceTypes = collect([
            ['code' => 'P', 'label' => trans('student.attendance.types.present'), 'color' => 'bg-success', 'key' => 'present'],
            ['code' => 'A', 'label' => trans('student.attendance.types.absent'), 'color' => 'bg-danger', 'key' => 'absent'],
            ['code' => 'H', 'label' => trans('student.attendance.types.holiday'), 'color' => 'bg-info', 'key' => 'holiday'],
        ]);

        $dbAttendanceTypes = Option::query()
            ->byTeam()
            ->where('type', OptionType::STUDENT_ATTENDANCE_TYPE)
            ->get()
            ->map(function ($attendanceType) {
                return [
                    'code' => $attendanceType->getMeta('code'),
                    'label' => $attendanceType->name,
                    'color' => $attendanceType->getMeta('color') ?? 'bg-primary',
                    'key' => Str::camel($attendanceType->name),
                ];
            });

        $attendanceTypes = $attendanceTypes->concat($dbAttendanceTypes);

        $attendances = Attendance::query()
            ->where('date', $date)
            ->whereIn('batch_id', $batches->pluck('id')->toArray())
            ->whereNull('subject_id')
            ->whereSession(AttendanceSession::FIRST)
            ->whereIsDefault(true)
            ->get();

        $holiday = Holiday::query()
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();

        $students = Student::query()
            ->select('students.id', 'students.batch_id')
            ->with('admission')
            ->join('admissions', 'admissions.id', '=', 'students.admission_id')
            ->whereIn('students.batch_id', $batches->pluck('id')->toArray())
            ->filterByStatus('studying')
            ->get();

        $grandTotalStrength = $students->count();

        $grandTotalCount = [];
        foreach ($attendanceTypes as $attendanceType) {
            $grandTotalCount[$attendanceType['key']] = 0;
        }

        $grandTotal = 0;
        $rows = [];
        $position = 1;
        foreach ($batches as $batch) {
            $row = [];

            $row['position'] = $position++;
            $row['total'] = 0;

            foreach ($attendanceTypes as $attendanceType) {
                $row[$attendanceType['key']] = 0;
            }

            $incharges = $batch->incharges->pluck('employee.name')->toArray();
            $incharge = implode(', ', $incharges);

            $row['batch_uuid'] = $batch->uuid;
            $row['course_batch'] = $batch->course_name.' - '.$batch->batch_name;
            $row['incharge'] = $incharge;
            $row['strength'] = $students->where('batch_id', $batch->id)->count();

            $attendance = $attendances->where('batch_id', $batch->id)->first();

            if (! $attendance && $holiday) {
                if ($status != 'marked') {
                    $rows[] = $row;
                }

                continue;
            }

            if (! $attendance) {
                if ($status != 'marked') {
                    $rows[] = $row;
                }

                continue;
            }

            if ($status == 'not_marked') {
                continue;
            }

            $values = collect($attendance->values);

            foreach ($attendanceTypes as $attendanceType) {
                $count = count(Arr::get($values->firstWhere('code', $attendanceType['code']), 'uuids', []));
                $row[$attendanceType['key']] = $count;
                $row['total'] += $count;
                $grandTotalCount[$attendanceType['key']] += $count;
                $grandTotal += $count;
            }

            $rows[] = $row;
        }

        $sortBy = $this->getSort();

        if ($request->query('sort')) {
            usort($rows, function ($a, $b) use ($sortBy) {
                return $a[$sortBy] <=> $b[$sortBy];
            });

            if ($request->query('order') === 'desc') {
                $rows = array_reverse($rows);
            }
        }

        $footers = [
            ['key' => 'course_batch', 'label' => trans('general.total')],
            ['key' => 'strength', 'label' => $grandTotalStrength],
        ];

        foreach ($attendanceTypes as $attendanceType) {
            $footers[] = ['key' => $attendanceType['key'], 'label' => $grandTotalCount[$attendanceType['key']]];
        }

        $footers[] = ['key' => 'total', 'label' => $grandTotal];

        return [
            'headers' => $this->getHeaders($attendanceTypes),
            'data' => $rows,
            'meta' => [
                'filename' => 'Date Wise Attendance Report',
                'total' => $batches->count(),
                'has_footer' => true,
                'attendance_types' => $attendanceTypes->toArray(),
            ],
            'footers' => $footers,
        ];
    }

    public function list(Request $request): array
    {
        return $this->filter($request);
    }
}
