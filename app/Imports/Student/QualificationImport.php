<?php

namespace App\Imports\Student;

use App\Concerns\ItemImport;
use App\Enums\OptionType;
use App\Enums\QualificationResult;
use App\Helpers\CalHelper;
use App\Models\Tenant\Option;
use App\Models\Tenant\Qualification;
use App\Models\Tenant\Student\Student;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class QualificationImport implements ToCollection, WithHeadingRow
{
    use ItemImport;

    protected $limit = 1000;

    public function collection(Collection $rows)
    {
        $this->validateHeadings($rows);

        if (count($rows) > $this->limit) {
            throw ValidationException::withMessages(['message' => trans('general.errors.max_import_limit_crossed', ['attribute' => $this->limit])]);
        }

        $logFile = $this->getLogFile('student_qualification');

        [$errors, $rows] = $this->validate($rows);

        $this->checkForErrors('student_qualification', $errors);

        if (! request()->boolean('validate') && ! \Storage::disk('local')->exists($logFile)) {
            $this->import($rows);
        }
    }

    private function import(Collection $rows)
    {
        $students = Student::query()
            ->byPeriod()
            ->whereIn('id', $rows->pluck('student_id'))
            ->get();

        activity()->disableLogging();

        \DB::beginTransaction();

        foreach ($rows as $row) {
            $course = Arr::get($row, 'course');
            $session = Arr::get($row, 'session');
            $institute = Arr::get($row, 'institute');
            $instituteAddress = Arr::get($row, 'institute_address');
            $affiliatedTo = Arr::get($row, 'affiliated_to');
            $startDate = Arr::get($row, 'start_date');
            $endDate = Arr::get($row, 'end_date');
            $result = Arr::get($row, 'result');
            $totalMarks = Arr::get($row, 'total_marks');
            $obtainedMarks = Arr::get($row, 'obtained_marks');
            $percentage = Arr::get($row, 'percentage');
            $failedSubjects = Arr::get($row, 'failed_subjects');

            $student = $students->firstWhere('id', Arr::get($row, 'student_id'));

            if ($startDate) {
                if (is_int($startDate)) {
                    $startDate = Date::excelToDateTimeObject($startDate)->format('Y-m-d');
                } else {
                    $startDate = Carbon::parse($startDate)->toDateString();
                }
            }

            if ($endDate) {
                if (is_int($endDate)) {
                    $endDate = Date::excelToDateTimeObject($endDate)->format('Y-m-d');
                } else {
                    $endDate = Carbon::parse($endDate)->toDateString();
                }
            }

            $percentage = 0;
            if (is_numeric($totalMarks) && is_numeric($obtainedMarks)) {
                $percentage = $totalMarks ? round($obtainedMarks / $totalMarks * 100, 2) : 0;
            }

            $qualification = Qualification::query()
                ->where('model_type', 'Contact')
                ->where('model_id', $student->contact_id)
                ->where('level_id', $row['level_id'])
                ->where('course', $course)
                ->first();

            if ($qualification) {
                $qualification->update([
                    'institute' => $institute,
                    'affiliated_to' => $affiliatedTo,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'result' => strtolower($result),
                    'meta' => [
                        'institute_address' => $instituteAddress,
                        'session' => $session,
                        'total_marks' => $totalMarks,
                        'obtained_marks' => $obtainedMarks,
                        'percentage' => $percentage,
                        'failed_subjects' => $failedSubjects,
                    ],
                ]);
            } else {
                $qualification = Qualification::forceCreate([
                    'model_type' => 'Contact',
                    'model_id' => $student->contact_id,
                    'level_id' => $row['level_id'],
                    'course' => $course,
                    'institute' => $institute,
                    'affiliated_to' => $affiliatedTo,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'result' => strtolower($result),
                    'meta' => [
                        'media_token' => (string) Str::uuid(),
                        'institute_address' => $instituteAddress,
                        'session' => $session,
                        'total_marks' => $totalMarks,
                        'obtained_marks' => $obtainedMarks,
                        'percentage' => $percentage,
                        'failed_subjects' => $failedSubjects,
                    ],
                ]);
            }
        }

        \DB::commit();

        activity()->enableLogging();
    }

    private function validate(Collection $rows)
    {
        $errors = [];

        $qualificationLevels = Option::query()
            ->byTeam()
            ->where('type', OptionType::QUALIFICATION_LEVEL)
            ->get();

        $students = Student::query()
            ->byPeriod()
            ->summary()
            ->get();

        $newRows = [];
        $newRecords = [];
        foreach ($rows as $index => $row) {
            $rowNo = $index + 2;

            $name = Arr::get($row, 'student');
            $level = Arr::get($row, 'level');
            $course = Arr::get($row, 'course');
            $session = Arr::get($row, 'session');
            $institute = Arr::get($row, 'institute');
            $instituteAddress = Arr::get($row, 'institute_address');
            $affiliatedTo = Arr::get($row, 'affiliated_to');
            $startDate = Arr::get($row, 'start_date');
            $endDate = Arr::get($row, 'end_date');
            $result = Arr::get($row, 'result');
            $totalMarks = Arr::get($row, 'total_marks');
            $obtainedMarks = Arr::get($row, 'obtained_marks');
            $failedSubjects = Arr::get($row, 'failed_subjects');

            if (! $name) {
                $errors[] = $this->setError($rowNo, trans('student.props.name'), 'required');
            } elseif (! $students->filter(function ($item) use ($name) {
                return strtolower($item->name) == strtolower($name) || $item->code_number == $name;
            })->first()) {
                $errors[] = $this->setError($rowNo, trans('student.props.name'), 'invalid');
            }

            if (! $level) {
                $errors[] = $this->setError($rowNo, trans('contact.qualification_level.qualification_level'), 'required');
            }

            $qualificationLevel = null;
            if ($level) {
                $qualificationLevel = $qualificationLevels->filter(function ($item) use ($level) {
                    return strtolower($item->name) == strtolower($level);
                })->first();

                if (! $qualificationLevel) {
                    $errors[] = $this->setError($rowNo, trans('contact.qualification_level.qualification_level'), 'invalid');
                }
            }

            if ($course && strlen($course) > 100) {
                $errors[] = $this->setError($rowNo, trans('student.qualification.props.course'), 'max', ['max' => 100]);
            }

            if ($session && strlen($session) > 100) {
                $errors[] = $this->setError($rowNo, trans('student.qualification.props.session'), 'max', ['max' => 100]);
            }

            if ($institute && strlen($institute) > 100) {
                $errors[] = $this->setError($rowNo, trans('student.qualification.props.institute'), 'max', ['max' => 100]);
            }

            if ($instituteAddress && strlen($instituteAddress) > 100) {
                $errors[] = $this->setError($rowNo, trans('student.qualification.props.institute_address'), 'max', ['max' => 100]);
            }

            if ($affiliatedTo && strlen($affiliatedTo) > 100) {
                $errors[] = $this->setError($rowNo, trans('student.qualification.props.affiliated_to'), 'max', ['max' => 100]);
            }

            if (! $result) {
                $errors[] = $this->setError($rowNo, trans('student.qualification.props.result'), 'required');
            } elseif (! in_array(strtolower($result), QualificationResult::getKeys())) {
                $errors[] = $this->setError($rowNo, trans('student.qualification.props.result'), 'invalid');
            }

            if ($startDate && is_int($startDate)) {
                $startDate = Date::excelToDateTimeObject($startDate)->format('Y-m-d');
            }

            if ($startDate && ! CalHelper::validateDate($startDate)) {
                $errors[] = $this->setError($rowNo, trans('student.qualification.props.start_date'), 'invalid');
            }

            if ($endDate && is_int($endDate)) {
                $endDate = Date::excelToDateTimeObject($endDate)->format('Y-m-d');
            }

            if ($endDate && ! CalHelper::validateDate($endDate)) {
                $errors[] = $this->setError($rowNo, trans('student.qualification.props.end_date'), 'invalid');
            }

            if ($startDate && $endDate && $startDate > $endDate) {
                $errors[] = $this->setError($rowNo, trans('student.qualification.props.start_date'), 'date_before', ['date' => trans('student.qualification.props.end_date')]);
            }

            if ($result == QualificationResult::PASS->value) {
                if (! $totalMarks) {
                    $errors[] = $this->setError($rowNo, trans('student.qualification.props.total_marks'), 'required');
                } elseif (! is_numeric($totalMarks)) {
                    $errors[] = $this->setError($rowNo, trans('student.qualification.props.total_marks'), 'numeric');
                }

                if (! $obtainedMarks) {
                    $errors[] = $this->setError($rowNo, trans('student.qualification.props.obtained_marks'), 'required');
                } elseif (! is_numeric($obtainedMarks)) {
                    $errors[] = $this->setError($rowNo, trans('student.qualification.props.obtained_marks'), 'numeric');
                }

                if ($totalMarks && $obtainedMarks && $totalMarks < $obtainedMarks) {
                    $errors[] = $this->setError($rowNo, trans('student.qualification.props.total_marks'), 'min', ['min' => $obtainedMarks]);
                }
            }

            if ($result == QualificationResult::FAIL->value) {
                if (! $failedSubjects) {
                    $errors[] = $this->setError($rowNo, trans('student.qualification.props.failed_subjects'), 'required');
                }
            }

            $student = $students->filter(function ($item) use ($name) {
                return strtolower($item->name) == strtolower($name) || $item->code_number == $name;
            })->first();

            $row['student_id'] = $student?->id;
            $row['contact_id'] = $student?->contact_id;
            $row['level_id'] = $qualificationLevel?->id;
            $newRows[] = $row;
        }

        $rows = collect($newRows);

        return [$errors, $rows];
    }
}
