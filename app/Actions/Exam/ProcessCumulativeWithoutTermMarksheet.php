<?php

namespace App\Actions\Exam;

use App\Enums\Exam\AssessmentAttempt;
use App\Enums\Exam\ReportType;
use App\Enums\Exam\Result;
use App\Enums\OptionType;
use App\Enums\Student\AttendanceSession;
use App\Models\Tenant\Academic\Batch;
use App\Models\Tenant\Academic\Subject;
use App\Models\Tenant\Exam\Observation;
use App\Models\Tenant\Exam\Result as ExamResult;
use App\Models\Tenant\Exam\Schedule;
use App\Models\Tenant\HealthRecord;
use App\Models\Tenant\Option;
use App\Models\Tenant\Student\Attendance;
use App\Models\Tenant\Student\Student;
use App\Models\Tenant\Student\SubjectWiseStudent;
use App\Support\HasGrade;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProcessCumulativeWithoutTermMarksheet
{
    use HasGrade;

    public function execute(Batch $batch, Collection $students, array $params)
    {
        $params['subject_absent_criteria'] = 'all'; // all | any
        $params['cumulative_assessment'] = true; // show exam wise total and percentage
        $params['overall_assessment'] = true; // Show subject wise total and percentage

        if ($params['overall_assessment']) {
            $params['cumulative_assessment'] = true;
        }

        $schedules = Schedule::query()
            ->select('exam_schedules.*')
            ->with('exam', 'records', 'assessment', 'grade')
            ->join('exams', 'exam_schedules.exam_id', '=', 'exams.id')
            ->whereBatchId($batch->id)
            ->whereHas('exam', function ($q) {
                $q->where('period_id', auth()->user()->current_period_id);
            })
            ->where(function ($q) {
                $q->where('is_reassessment', false)
                    ->orWhere('is_reassessment', null);
            })
            ->where('attempt', AssessmentAttempt::FIRST->value)
            ->orderBy('exams.position')
            ->get();

        if (! $schedules->count()) {
            throw ValidationException::withMessages(['message' => trans('global.could_not_find', ['attribute' => trans('exam.exam')])]);
        }

        $lastSchedule = $schedules->last();

        $examGrade = $lastSchedule->grade;
        $failGrades = collect($examGrade->records)->where('is_fail_grade', true)->pluck('code')->all();

        $lastExamDate = $lastSchedule->records->where('config.has_exam', true)->sortByDesc('date.value')->first()?->date;

        $healthRecords = HealthRecord::query()
            ->whereModelType('Student')
            ->whereIn('model_id', $students->pluck('id')->all())
            ->where('date', '<=', $lastExamDate->value)
            ->orderBy('date', 'desc')
            ->get();

        $comments = collect(Arr::get($lastSchedule->details, 'comments', []));

        $attendanceTypes = Option::query()
            ->byTeam()
            ->where('type', OptionType::STUDENT_ATTENDANCE_TYPE)
            ->get();

        $presentAttendanceTypes = $attendanceTypes->filter(function ($attendanceType) {
            return $attendanceType->getMeta('sub_type') == 'present';
        })->map(function ($attendanceType) {
            return $attendanceType->getMeta('code');
        })->values()->all();

        array_unshift($presentAttendanceTypes, 'P');

        $overallAttendances = Attendance::query()
            ->whereBatchId($batch->id)
            ->whereNull('subject_id')
            ->whereSession(AttendanceSession::FIRST)
            ->whereIsDefault(true)
            ->when(Arr::get($params, 'attendance_till_last_exam_date'), function ($q) use ($lastExamDate) {
                $q->where('date', '<=', $lastExamDate->value);
            })
            ->where(function ($q) {
                $q->whereNull('meta->is_holiday')
                    ->orWhere('meta->is_holiday', false);
            })
            ->get();

        $cumulativeAttendances = [];
        foreach ($overallAttendances as $overallAttendance) {
            $values = Arr::get($overallAttendance, 'values', []);
            foreach ($values as $value) {
                foreach (Arr::get($value, 'uuids', []) as $uuid) {
                    $cumulativeAttendances[$uuid][] = Arr::get($value, 'code');
                }
            }
        }

        $examAttendances = collect(Arr::get($lastSchedule->details, 'attendances', []));

        $totalWorkingDays = 0;
        if (! Arr::get($params, 'student_wise_attendance_working_days')) {
            $totalWorkingDays = $overallAttendances->count();
            if (! empty(Arr::get($lastSchedule->details, 'total_working_days'))) {
                $totalWorkingDays = Arr::get($lastSchedule->details, 'total_working_days');
            }
        }

        $subjects = Subject::query()
            ->withSubjectRecord($batch->id, $batch->course_id)
            ->orderBy('subjects.position', 'asc')
            ->get();

        $examRecords = [];
        $examAssessments = [];
        foreach ($schedules as $schedule) {
            $scheduleAssessmentRecords = collect($schedule->assessment->records ?? []);
            foreach ($schedule->records as $record) {
                $subject = $subjects->firstWhere('id', $record->subject_id);

                if (! $subject) {
                    continue;
                }

                $hasExam = $record->getConfig('has_exam');

                if (! $hasExam) {
                    continue;
                }

                $recordMarks = $record->marks;
                $recordAssessments = $record->getConfig('assessments', []);

                $assessments = [];
                foreach ($recordAssessments as $recordAssessment) {
                    $code = Arr::get($recordAssessment, 'code');

                    $assessmentMaxMark = Arr::get($recordAssessment, 'max_mark', 0);
                    $originalAssessmentMaxMark = $assessmentMaxMark;

                    $scheduleAssessmentRecord = $scheduleAssessmentRecords->firstWhere('code', $code);
                    $assessments[] = [
                        'code' => $code,
                        'name' => Arr::get($scheduleAssessmentRecord, 'name'),
                        'position' => Arr::get($scheduleAssessmentRecord, 'position', 0),
                        'max_mark' => $assessmentMaxMark,
                        'original_max_mark' => $originalAssessmentMaxMark,
                    ];
                }

                $assessments = collect($assessments)->sortBy('position')->values()->all();

                $examRecords[] = [
                    'exam_id' => $schedule->exam_id,
                    'schedule_id' => $schedule->id,
                    'subject_id' => $record->subject_id,
                    'has_grading' => $subject->has_grading,
                    'is_elective' => $subject->is_elective,
                    'assessments' => $assessments,
                    'not_applicable_students' => $record->getConfig('not_applicable_students', []),
                    'marks' => $recordMarks,
                ];
            }
        }

        $examRecords = collect($examRecords);

        $observation = Observation::query()
            ->with('grade')
            ->whereIn('id', $lastSchedule->pluck('observation_id')->all())
            ->first();

        $observationRecords = [];
        foreach ($observation?->records ?? [] as $observationRecord) {
            $observationRecords[] = [
                'name' => Arr::get($observationRecord, 'name'),
                'code' => Arr::get($observationRecord, 'code'),
                'max_mark' => Arr::get($observationRecord, 'max_mark'),
            ];
        }
        $observationRecords = collect($observationRecords);

        $observationMarks = collect(Arr::get($schedule->details, 'observation_marks', []));

        $subjectWiseStudents = SubjectWiseStudent::query()
            ->whereBatchId($batch->id)
            ->whereIn('student_id', $students->pluck('id')->all())
            ->get();

        $headingRows = [];
        $row = [];

        array_push($row, [
            'key' => 'subject',
            'type' => 'heading',
            'label' => trans('academic.subject.subject'),
            'rowspan' => 1,
        ]);

        $excludeGroupedExams = [];
        foreach ($schedules as $schedule) {
            $excludeGroupedExams = array_merge($excludeGroupedExams, $schedule->grouped_exams);

            if (in_array($schedule->exam->code, $excludeGroupedExams)) {
                continue;
            }

            array_push($row, [
                'key' => 'exam',
                'type' => 'heading',
                'label' => empty($schedule->exam->display_name) ? $schedule->exam->name : $schedule->exam->display_name,
                'colspan' => 1,
            ]);
        }

        array_push($row, [
            'key' => 'total',
            'type' => 'heading',
            'label' => trans('exam.total_short'),
        ]);

        array_push($row, [
            'key' => 'percentage',
            'type' => 'heading',
            'label' => trans('exam.percentage_short'),
        ]);

        array_push($row, [
            'key' => 'grade',
            'type' => 'heading',
            'label' => trans('exam.grade_short'),
        ]);

        array_push($headingRows, $row);

        $primarySubjects = $subjects->where('has_grading', 0);
        $gradingSubjects = $subjects->where('has_grading', 1);

        $compulsorySubjects = $primarySubjects->where('is_elective', 0);
        $electiveSubjects = $primarySubjects->where('is_elective', 1);

        $allSubjectObtainedMarks = [];
        $allSubjectMaxMarks = [];

        foreach ($students as $student) {
            $rows = [];

            foreach ($headingRows as $headingRow) {
                array_push($rows, $headingRow);
            }

            $subjectWiseStudent = $subjectWiseStudents->filter(function ($subjectWiseStudent) use ($student, $electiveSubjects) {
                return in_array($subjectWiseStudent->subject_id, $electiveSubjects->pluck('id')->all()) && $subjectWiseStudent->student_id == $student->id;
            });

            $electedSubjects = $electiveSubjects->filter(function ($electiveSubject) use ($subjectWiseStudent) {
                return in_array($electiveSubject->id, $subjectWiseStudent->pluck('subject_id')->all());
            });

            $studentSubjects = $compulsorySubjects->merge($electedSubjects);

            $studentPrimarySubjects = $primarySubjects->filter(function ($primarySubject) use ($studentSubjects) {
                return in_array($primarySubject->id, $studentSubjects->pluck('id')->all());
            });

            $failedGrade = 0;
            $totalAbsentCount = 0;
            $absentSubjects = [];
            $failedGradeSubjects = [];
            $reassessmentSubjects = [];
            $grandMaxMarks = 0;
            $grandTotal = 0;

            $scheduleWiseTotal = [];
            foreach ($schedules as $schedule) {
                $scheduleWiseTotal[$schedule->id] = 0;
            }

            foreach ($studentPrimarySubjects as $subject) {
                $row = [];

                array_push($row, [
                    'key' => 'subject',
                    'label' => $subject->name,
                ]);

                $subjectWiseTotal = 0;
                $subjectWiseMaxMarks = 0;

                $excludeGroupedExams = [];
                foreach ($schedules as $schedule) {

                    $groupedExams = $schedule->grouped_exams;
                    $excludeGroupedExams = array_merge($excludeGroupedExams, $groupedExams);

                    if ($groupedExams) {
                        $groupedSchedules = $schedules->whereIn('exam.code', $groupedExams);

                        $groupedMarksData = [];

                        foreach ($groupedSchedules as $groupedSchedule) {
                            $marksData = $this->getMarksData($groupedSchedule, $subject, $examRecords, $student);

                            $marksData['code'] = $groupedSchedule->exam->code;

                            $groupedMarksData[] = $marksData;
                        }
                    }

                    if (in_array($schedule->exam->code, $excludeGroupedExams)) {
                        continue;
                    }

                    $examRecord = $examRecords->where('exam_id', $schedule->exam_id)
                        ->where('subject_id', $subject->id)
                        ->first();

                    if (! $examRecord) {
                        array_push($row, [
                            'key' => 'subject_total',
                            'label' => '-',
                        ]);

                        continue;
                    }

                    $assessments = $examRecord['assessments'];

                    $marks = $examRecord['marks'] ?? [];

                    $isAbsent = false;
                    $subjectMaxMarks = 0;
                    $subjectTotal = 0;
                    $absentCount = 0;
                    $markNotRecorded = 0;

                    foreach ($assessments as $assessment) {
                        $assessmentMark = collect($marks)
                            ->firstWhere('code', $assessment['code']);

                        $obtainedMark = collect(Arr::get($assessmentMark, 'marks') ?? [])
                            ->firstWhere('uuid', $student->uuid)['obtained_mark'] ?? '';

                        if (in_array($obtainedMark, ['A', 'a'])) {
                            $absentCount++;
                        } elseif (empty($obtainedMark)) {
                            $markNotRecorded++;
                        }

                        if (Arr::get($params, 'overall_assessment')) {
                        } else {
                            if (! Arr::get($params, 'cumulative_assessment')) {
                                if (empty($assessmentMark)) {
                                    array_push($row, [
                                        'key' => 'max_mark',
                                        'label' => '-',
                                    ]);

                                    array_push($row, [
                                        'key' => 'obtained_mark',
                                        'label' => '-',
                                    ]);
                                } else {
                                    array_push($row, [
                                        'key' => 'max_mark',
                                        'label' => $assessment['max_mark'],
                                    ]);

                                    array_push($row, [
                                        'key' => 'obtained_mark',
                                        'label' => $obtainedMark,
                                    ]);
                                }
                            }
                        }

                        $subjectMaxMarks += $assessment['max_mark'];
                        $subjectTotal += is_numeric($obtainedMark) ? $obtainedMark : 0;
                    }

                    if ($groupedExams) {
                        $groupedMarksData[] = [
                            'code' => $schedule->exam->code,
                            'obtained_marks' => $subjectTotal,
                            'max_marks' => $subjectMaxMarks,
                        ];

                        // get top x marks for this subject
                        $topMarks = $this->getTopMarksSummary($groupedMarksData, $schedule->exam->filter_top_x_marks);

                        $subjectTotal = Arr::get($topMarks, 'obtained', 0);
                        $subjectMaxMarks = Arr::get($topMarks, 'max', 0);
                    }

                    $weightage = $schedule->exam->weightage ?? 100;

                    $subjectTotal = round(($subjectTotal / 100) * $weightage, 2);
                    $subjectMaxMarks = round(($subjectMaxMarks / 100) * $weightage, 2);

                    $scheduleWiseTotal[$schedule->id] += $subjectTotal;

                    $subjectWiseTotal += $subjectTotal;
                    $subjectWiseMaxMarks += $subjectMaxMarks;

                    $subjectWiseTotal = round($subjectWiseTotal, 2);
                    $subjectWiseMaxMarks = round($subjectWiseMaxMarks, 2);

                    $grandMaxMarks += $subjectMaxMarks;
                    $grandTotal += $subjectTotal;

                    array_push($row, [
                        'key' => 'subject_total',
                        'type' => 'marks',
                        'label' => round($subjectTotal, 2),
                    ]);

                    if (Arr::get($params, 'subject_absent_criteria') == 'all' && $absentCount == count($assessments)) {
                        $absentSubjects[] = $subject->code;
                        $isAbsent = true;
                    } elseif (Arr::get($params, 'subject_absent_criteria') == 'any' && $absentCount) {
                        $absentSubjects[] = $subject->code;
                        $isAbsent = true;
                    }

                    $subjectGrade = $this->getGrade($schedule->grade, $subjectMaxMarks, $subjectTotal, 'grade');

                    if ($isAbsent) {
                        $subjectGrade = ['code' => 'Ab', 'value' => 0];
                        $totalAbsentCount++;
                    }

                    if (in_array(Arr::get($subjectGrade, 'code'), $failGrades)) {
                        $failedGradeSubjects[] = $subject->code;
                        $failedGrade++;
                    }
                }

                $allSubjectObtainedMarks[$subject->code][] = $subjectWiseTotal;
                $allSubjectMaxMarks[$subject->code] = $subjectWiseMaxMarks;

                $subjectWisePercentage = $subjectWiseMaxMarks ? round(($subjectWiseTotal / $subjectWiseMaxMarks) * 100, 2) : 0;
                $subjectWiseGrade = $this->getGrade($lastSchedule->grade, $subjectWiseMaxMarks, $subjectWiseTotal, 'grade');

                if (Arr::get($params, 'overall_assessment')) {
                    array_push($row, [
                        'key' => 'subject_total',
                        'type' => 'summary',
                        'label' => $subjectWiseTotal,
                    ]);

                    array_push($row, [
                        'key' => 'subject_percentage',
                        'type' => 'summary',
                        'label' => $subjectWisePercentage,
                    ]);

                    array_push($row, [
                        'key' => 'obtained_grade',
                        'type' => 'summary',
                        'label' => Arr::get($subjectWiseGrade, 'code'),
                    ]);
                }

                $rows[] = $row;
            }

            $finalPercentage = $grandMaxMarks ? round(($grandTotal / $grandMaxMarks) * 100, 2) : 0;

            $result = $failedGrade ? Result::FAIL->value : Result::PASS->value;

            // custom result logic

            // end custom result logic

            $resultLabel = trans('exam.results.'.$result);

            $gradingRows = $this->getGradingRows($student, $lastSchedule, $gradingSubjects, $subjects, $subjectWiseStudents);

            $observationRows = $this->getObservationRows($student, $observation, $observationRecords, $observationMarks);

            $scheduleConfig = $lastSchedule->config;
            $scheduleConfig['marksheet_status'] = 'processed';
            $scheduleConfig['marksheet_type'] = 'cumulative';
            $scheduleConfig['result_date'] = Arr::get($params, 'result_date');
            $scheduleConfig['marksheet_template'] = Arr::get($params, 'template');
            $scheduleConfig['report_type'] = ReportType::MARK_BASED->value;
            $scheduleConfig['report_sub_type'] = 'cumulative_without_term';
            $lastSchedule->config = $scheduleConfig;
            $lastSchedule->save();

            $examResult = ExamResult::firstOrCreate([
                'student_id' => $student->id,
                'is_cumulative' => true,
                'attempt' => Arr::get($params, 'attempt'),
            ]);

            if (Arr::get($params, 'student_wise_attendance_working_days')) {
                $totalWorkingDays = $overallAttendances->where('date.value', '>=', $student->start_date->value)->count();
            }

            $totalPresent = collect($cumulativeAttendances[$student->uuid] ?? [])->filter(function ($value) use ($presentAttendanceTypes) {
                return in_array($value, $presentAttendanceTypes);
            })->count();

            $totalAbsent = $totalWorkingDays - $totalPresent;

            $examAttendance = $examAttendances->firstWhere('uuid', $student->uuid);

            if (! empty(Arr::get($examAttendance, 'attendance'))) {
                $totalPresent = Arr::get($examAttendance, 'attendance');
                $totalAbsent = $totalWorkingDays - $totalPresent;
            }

            $examResult->setMeta([
                'grading_marks' => $gradingRows,
                'observation_marks' => $observationRows,
                'attendance' => [
                    'present' => $totalPresent,
                    'present_percentage' => $totalWorkingDays ? round(($totalPresent / $totalWorkingDays) * 100, 2) : '',
                    'absent' => $totalAbsent,
                    'absent_percentage' => $totalWorkingDays ? round(($totalAbsent / $totalWorkingDays) * 100, 2) : '',
                    'working_days' => $totalWorkingDays,
                ],
                'comment' => $comments->firstWhere('uuid', $student->uuid),
                'health_record' => $healthRecords->firstWhere('model_id', $student->id)?->details ?? [],
            ]);

            $examResult->result = $result;
            $examResult->marks = $rows;
            $examResult->total_marks = $grandMaxMarks;
            $examResult->obtained_marks = $grandTotal;
            $examResult->percentage = $finalPercentage;
            $examResult->subjects = [
                'failed' => $failedGradeSubjects,
                'absent' => $absentSubjects,
                'reassessment' => $reassessmentSubjects,
            ];
            $examResult->generated_at = now()->toDateTimeString();
            $examResult->save();
        }

        foreach ($primarySubjects as $primarySubject) {
            if ($primarySubject->is_elective) {
                continue;
            }

            $subjectWiseMarks = Arr::get($allSubjectObtainedMarks, $primarySubject->code, []);

            $highestMarks = count($subjectWiseMarks) ? max($subjectWiseMarks) : 0;
            $lowestMarks = count($subjectWiseMarks) ? min($subjectWiseMarks) : 0;
            $averageMarks = count($subjectWiseMarks) ? round(array_sum($subjectWiseMarks) / count($subjectWiseMarks), 2) : 0;

            $overallResult[] = [
                'code' => $primarySubject->code,
                'highest_marks' => $highestMarks,
                'highest_grade' => $this->getGrade($lastSchedule->grade, Arr::get($allSubjectMaxMarks, $primarySubject->code, 0), $highestMarks, 'code'),
                'lowest_marks' => $lowestMarks,
                'average_marks' => $averageMarks,
                'average_grade' => $this->getGrade($lastSchedule->grade, Arr::get($allSubjectMaxMarks, $primarySubject->code, 0), $averageMarks, 'code'),
            ];
        }

        $lastSchedule->setMeta([
            'overall_result' => $overallResult,
        ]);
        $lastSchedule->save();
    }

    private function getMarksData(Schedule $schedule, Subject $subject, Collection $examRecords, Student $student): array
    {
        $examRecord = $examRecords->where('exam_id', $schedule->exam_id)
            ->where('subject_id', $subject->id)
            ->first();

        if (! $examRecord) {
            return [
                'obtained_marks' => 0,
                'max_marks' => 0,
            ];
        }

        $assessments = $examRecord['assessments'];

        $marks = $examRecord['marks'] ?? [];

        $subjectMaxMarks = 0;
        $subjectTotal = 0;

        foreach ($assessments as $assessment) {
            $assessmentMark = collect($marks)
                ->firstWhere('code', $assessment['code']);

            $obtainedMark = collect(Arr::get($assessmentMark, 'marks') ?? [])
                ->firstWhere('uuid', $student->uuid)['obtained_mark'] ?? '';

            $subjectMaxMarks += $assessment['max_mark'];
            $subjectTotal += is_numeric($obtainedMark) ? $obtainedMark : 0;
        }

        return [
            'obtained_marks' => $subjectTotal,
            'max_marks' => $subjectMaxMarks,
        ];
    }

    private function getTopMarksSummary(Collection|array $data, int $count = 1): array
    {
        if (is_array($data)) {
            $data = collect($data);
        }

        $top = $data
            ->sortByDesc('obtained_marks')
            ->take($count);

        return [
            'obtained' => $top->sum('obtained_marks'),
            'max' => $top->sum('max_marks'),
        ];
    }

    private function getGradingRows(Student $student, Schedule $schedule, Collection $gradingSubjects, Collection $subjects, Collection $subjectWiseStudents)
    {
        $gradingRows = [];
        foreach ($gradingSubjects as $examRecord) {

            if ($examRecord['is_elective'] && ! $subjectWiseStudents->where('student_id', $student->id)->firstWhere('subject_id', $examRecord['subject_id'])) {
                continue;
            }

            $row = [];
            $subject = $subjects->firstWhere('id', $examRecord['subject_id']);

            array_push($row, [
                'key' => 'subject',
                'label' => $subject->name,
            ]);

            $assessments = $examRecord['assessments'];

            $subjectMaxMarks = 0;
            $subjectTotal = 0;
            foreach ($assessments as $assessment) {
                $assessmentMark = collect(Arr::get($examRecord, 'marks', []))
                    ->firstWhere('code', $assessment['code']);

                $obtainedMark = collect(Arr::get($assessmentMark, 'marks') ?? [])
                    ->firstWhere('uuid', $student->uuid)['obtained_mark'] ?? '';

                $subjectMaxMarks += $assessment['max_mark'];
                $subjectTotal += is_numeric($obtainedMark) ? $obtainedMark : 0;
            }

            $subjectGrade = $this->getGrade($schedule->grade, $subjectMaxMarks, $subjectTotal, 'code');

            array_push($row, [
                'key' => 'obtained_grade',
                'label' => $subjectGrade,
            ]);

            $gradingRows[] = $row;
        }

        return $gradingRows;
    }

    private function getObservationRows(Student $student, ?Observation $observation, Collection $observationRecords, Collection $observationMarks)
    {
        $observationRows = [];

        if (! $observation) {
            return $observationRows;
        }

        foreach ($observationRecords as $index => $observationParameterRecord) {
            $observationRow = [];

            $observationRow[] = [
                'label' => Arr::get($observationParameterRecord, 'name'),
                'class' => 'text-center',
            ];

            $observationMaxMark = Arr::get($observationParameterRecord, 'max_mark');

            $observationMark = $observationMarks->firstWhere('code', Arr::get($observationParameterRecord, 'code'))['marks'] ?? [];

            $obtainedObservationMark = collect($observationMark)->firstWhere('uuid', $student->uuid)['obtained_mark'] ?? '';

            $observationRow[] = [
                'label' => $this->getGrade($observation->grade, $observationMaxMark, $obtainedObservationMark),
                'class' => 'text-center',
            ];

            $observationRows[] = $observationRow;
        }

        return $observationRows;
    }
}
