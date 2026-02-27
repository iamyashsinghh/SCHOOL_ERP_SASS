<?php

namespace App\Services\Exam;

use App\Actions\Exam\GetAvailableSubjectForStudent;
use App\Actions\Exam\GetReassessmentSubjectForStudent;
use App\Helpers\CalHelper;
use App\Http\Resources\Exam\AssessmentResource;
use App\Http\Resources\Exam\CompetencyResource;
use App\Http\Resources\Exam\ExamResource;
use App\Http\Resources\Exam\GradeResource;
use App\Http\Resources\Exam\ObservationResource;
use App\Models\Employee\Employee;
use App\Models\Exam\Assessment;
use App\Models\Exam\Competency;
use App\Models\Exam\Exam;
use App\Models\Exam\Form;
use App\Models\Exam\Grade;
use App\Models\Exam\Observation;
use App\Models\Exam\Record;
use App\Models\Exam\Result as ExamResult;
use App\Models\Exam\Schedule;
use App\Models\Incharge;
use App\Models\Student\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ScheduleService
{
    public function preRequisite(Request $request)
    {
        $exams = ExamResource::collection(Exam::query()
            ->with('term.division')
            ->byPeriod()
            ->get());

        $assessments = AssessmentResource::collection(Assessment::query()
            ->byPeriod()
            ->get());

        $grades = GradeResource::collection(Grade::query()
            ->byPeriod()
            ->get());

        $observations = ObservationResource::collection(Observation::query()
            ->byPeriod()
            ->get());

        $competencies = CompetencyResource::collection(Competency::query()
            ->byPeriod()
            ->get());

        return compact('exams', 'assessments', 'grades', 'observations', 'competencies');
    }

    public function getFormSubmissionData(Schedule $schedule)
    {
        if (! auth()->user()->hasRole('student')) {
            return $schedule;
        }

        if (! $schedule->has_form) {
            $schedule->has_form = false;

            return $schedule;
        }

        $student = Student::query()
            ->auth()
            ->first();

        $reassessmentSubjects = (new GetReassessmentSubjectForStudent)->execute($student, $schedule);

        $availableSubjects = (new GetAvailableSubjectForStudent)->execute($student, $schedule);

        $payableFee = 0;

        foreach ($reassessmentSubjects as $record) {
            $payableFee += Arr::get($record, 'exam_fee')?->value ?? 0;
        }

        foreach ($availableSubjects as $record) {
            $payableFee += Arr::get($record, 'exam_fee')?->value ?? 0;
        }

        $examFormFee = Arr::get($schedule->exam->config, 'exam_form_fee', 0);
        $examFormLateFee = Arr::get($schedule->exam->config, 'exam_form_late_fee', 0);

        $payableFee += $examFormFee;
        $payableFee += $examFormLateFee;

        $form = Form::query()
            ->where('schedule_id', $schedule->id)
            ->where('student_id', $student->id)
            ->first();

        $schedule->form_uuid = $form?->uuid;
        $schedule->reassessment_subjects = $reassessmentSubjects;
        $schedule->available_subjects = $availableSubjects;
        $schedule->payable_fee = $payableFee;
        $schedule->confirmed_at = $form?->confirmed_at;
        $schedule->submitted_at = $form?->submitted_at;
        $schedule->approved_at = $form?->approved_at;

        return $schedule;
    }

    public function getIncharges(Schedule $schedule): Collection
    {
        $incharges = Incharge::query()
            ->with('model')
            ->where(function ($q) use ($schedule) {
                $q->where('model_type', 'Batch')
                    ->where('model_id', $schedule->batch_id);
            })
            ->orWhere(function ($q) use ($schedule) {
                $q->where('detail_type', 'Batch')
                    ->where('detail_id', $schedule->batch_id);
            })
            ->get();

        $employees = Employee::query()
            ->summary()
            ->whereIn('employee_id', $incharges->pluck('employee_id'))
            ->get();

        return $incharges->map(function ($incharge) use ($employees) {
            $employee = $employees->firstWhere('id', $incharge->employee_id);

            return [
                'uuid' => $incharge->uuid,
                'start_date' => $incharge->start_date,
                'end_date' => $incharge->end_date,
                'type' => $incharge->model_type == 'Batch' ? 'batch' : 'subject',
                'type_uuid' => $incharge->model->uuid,
                'employee' => [
                    'name' => $employee?->name,
                    'code_number' => $employee?->code_number,
                    'designation' => $employee?->designation,
                    'department' => $employee?->department,
                ],
            ];
        });
    }

    public function create(Request $request): Schedule
    {
        \DB::beginTransaction();

        $schedule = Schedule::forceCreate($this->formatParams($request));

        $this->updateRecords($request, $schedule);

        $this->updateAdditionalSubjects($request, $schedule);

        \DB::commit();

        return $schedule;
    }

    private function formatParams(Request $request, ?Schedule $schedule = null): array
    {
        $formatted = [
            'exam_id' => $request->exam_id,
            'batch_id' => $request->batch_id,
            'grade_id' => $request->grade_id,
            'assessment_id' => $request->assessment_id,
            'observation_id' => $request->observation_id,
            'competency_id' => $request->competency_id,
            'description' => $request->description,
        ];

        if (! $schedule) {
            $formatted['is_reassessment'] = $request->boolean('is_reassessment');
            $formatted['attempt'] = $request->attempt ?? 'first';
        }

        $config = $schedule?->config ?? [];
        $config['last_exam_date'] = $request->last_exam_date;
        $formatted['config'] = $config;

        return $formatted;
    }

    private function updateAdditionalSubjects(Request $request, Schedule $schedule): void
    {
        $subjectNames = [];
        foreach ($request->additional_subjects as $subject) {
            $subjectNames[] = Arr::get($subject, 'name');

            $examRecord = Record::query()
                ->whereScheduleId($schedule->id)
                ->where('config->subject_name', Arr::get($subject, 'name'))
                ->first();

            if (! $examRecord) {
                $date = Arr::get($subject, 'date') ?: null;
                $startTime = null;
                if (! empty($date) && Arr::get($subject, 'start_time')) {
                    $startTime = CalHelper::storeDateTime($date.' '.Arr::get($subject, 'start_time'))?->toTimeString();
                }

                $examRecord = Record::forceCreate([
                    'schedule_id' => $schedule->id,
                    'config' => [
                        'subject_name' => Arr::get($subject, 'name'),
                        'subject_code' => Arr::get($subject, 'code'),
                    ],
                    'date' => $date,
                    'start_time' => $startTime,
                    'duration' => Arr::get($subject, 'duration') ?: null,
                ]);
            } else {
                $date = Arr::get($subject, 'date') ?: null;
                $startTime = null;
                if (! empty($date) && Arr::get($subject, 'start_time')) {
                    $startTime = CalHelper::storeDateTime($date.' '.Arr::get($subject, 'start_time'))?->toTimeString();
                }

                $config = $examRecord->config;
                $config['subject_code'] = Arr::get($subject, 'code');
                $examRecord->config = $config;

                $examRecord->date = $date;
                $examRecord->start_time = $startTime;
                $examRecord->duration = Arr::get($subject, 'duration') ?: null;
                $examRecord->save();
            }
        }

        Record::query()
            ->whereScheduleId($schedule->id)
            ->whereNull('subject_id')
            ->whereNotIn('config->subject_name', $subjectNames)
            ->delete();
    }

    private function updateRecords(Request $request, Schedule $schedule): void
    {
        $subjectIds = [];
        foreach ($request->records as $index => $record) {
            $subjectIds[] = Arr::get($record, 'subject_id');

            $examRecord = Record::firstOrCreate([
                'schedule_id' => $schedule->id,
                'subject_id' => Arr::get($record, 'subject_id'),
            ]);

            $config = $examRecord->config ?? [];
            $hasExam = (bool) Arr::get($record, 'has_exam');

            if (! $hasExam) {
                $examRecord->date = null;
                $examRecord->start_time = null;
                $examRecord->duration = null;
                $config['has_exam'] = false;
                $config['assessments'] = [];
            } else {
                $date = Arr::get($record, 'date') ?: null;

                $startTime = null;
                if (! empty($date) && Arr::get($record, 'start_time')) {
                    $startTime = CalHelper::storeDateTime($date.' '.Arr::get($record, 'start_time'))?->toTimeString();
                }

                $examRecord->date = $date;
                $examRecord->start_time = $startTime;
                $examRecord->duration = Arr::get($record, 'duration') ?: null;
                $config['has_exam'] = true;

                if (! empty($examRecord->marks)) {
                    $assessments = Arr::get($record, 'assessments', []);
                    foreach ($assessments as $assessmentIndex => $assessment) {
                        $assessmentCode = Arr::get($assessment, 'code');
                        $maxMark = Arr::get($assessment, 'max_mark', 0);

                        $examRecordMarks = Arr::get(collect($examRecord->marks)->firstWhere('code', $assessmentCode), 'marks', []);

                        $examRecordMarks = collect(Arr::pluck($examRecordMarks, 'obtained_mark'));

                        $maxObtainedMark = collect($examRecordMarks)->filter(fn ($mark) => is_numeric($mark))->max();

                        if ($maxMark < $maxObtainedMark) {
                            throw ValidationException::withMessages(['records.'.$index.'.assessments.'.$assessmentIndex.'.marks' => trans('exam.schedule.marks_lt_obtained_mark', ['attribute' => $maxObtainedMark])]);
                        }
                    }
                }

                $config['assessments'] = Arr::get($record, 'assessments', []);
            }

            $examRecord->config = $config;
            $examRecord->save();
        }

        Record::query()
            ->whereScheduleId($schedule->id)
            ->whereNotNull('subject_id')
            ->whereNotIn('subject_id', $subjectIds)
            ->delete();
    }

    private function isEditable(Schedule $schedule): void
    {
        $publishMarksheet = (bool) Arr::get($schedule->exam->config, $schedule->attempt->value.'_attempt.publish_marksheet');

        if ($publishMarksheet) {
            throw ValidationException::withMessages(['message' => trans('exam.schedule.cannot_alter_after_marksheet_published')]);
        }

        // Allow editing for default admin
        if (auth()->user()->is_default) {
            return;
        }

        $marksheetStatus = Arr::get($schedule->config, 'marksheet_status');

        if ($marksheetStatus == 'processed') {
            throw ValidationException::withMessages(['message' => trans('exam.schedule.cannot_alter_after_marksheet_processed')]);
        }
    }

    private function getMarkRecorded(Schedule $schedule): bool
    {
        $marksCount = 0;

        foreach ($schedule->records as $record) {
            if (count($record->marks ?? []) || $record->getConfig('mark_recorded')) {
                $marksCount++;
            }
        }

        return $marksCount > 0;
    }

    public function update(Request $request, Schedule $schedule): void
    {
        $this->isEditable($schedule);

        $markRecorded = $this->getMarkRecorded($schedule);
        $request->merge([
            'mark_recorded' => $markRecorded,
        ]);

        \DB::beginTransaction();

        if (! $markRecorded) {
            $schedule->forceFill($this->formatParams($request, $schedule))->save();
        }

        $this->updateRecords($request, $schedule);

        $this->updateAdditionalSubjects($request, $schedule);

        // Allow editing schedule even if marksheet is processed
        if (Arr::get($schedule->config, 'marksheet_status') == 'processed') {
            $config = $schedule->config;
            $config['marksheet_status'] = 'pending';
            $schedule->config = $config;
            $schedule->save();

            $examId = $schedule->exam_id;
            $batchId = $schedule->batch_id;

            $studentIds = Student::query()
                ->select('id')
                ->where('batch_id', $batchId)
                ->pluck('id')
                ->all();

            ExamResult::query()
                ->where('attempt', $schedule->attempt->value)
                ->where('exam_id', $examId)
                ->whereIn('student_id', $studentIds)
                ->delete();
        }

        \DB::commit();
    }

    public function deletable(Request $request, Schedule $schedule): bool
    {
        $this->isEditable($schedule);

        if ($request->boolean('force')) {
            return true;
        }

        $examRecordExists = \DB::table('exam_records')
            ->whereScheduleId($schedule->id)
            ->whereNotNull('marks')
            ->exists();

        if ($examRecordExists) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('exam.schedule.schedule'), 'dependency' => trans('exam.assessment.props.mark')])]);
        }

        return true;
    }

    public function delete(Schedule $schedule)
    {
        $schedule->delete();
    }
}
