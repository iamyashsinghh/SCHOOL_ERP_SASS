<?php

namespace App\Services\Exam;

use App\Concerns\Exam\HasExamMarkLock;
use App\Http\Resources\Exam\ExamResource;
use App\Models\Academic\Batch;
use App\Models\Exam\Competency;
use App\Models\Exam\CompetencyRecord;
use App\Models\Exam\Exam;
use App\Models\Exam\Schedule;
use App\Models\Student\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CompetencyEvaluationService
{
    use HasExamMarkLock;

    public function preRequisite(Request $request)
    {
        $exams = ExamResource::collection(Exam::query()
            ->with('term.division')
            ->byPeriod()
            ->get());

        return compact('exams');
    }

    private function validateInput(Request $request): array
    {
        $request->validate([
            'exam' => 'required|uuid',
            'batch' => 'required|uuid',
            'student' => 'required|uuid',
        ]);

        $exam = Exam::query()
            ->with('term.division')
            ->byPeriod()
            ->whereUuid($request->exam)
            ->getOrFail(trans('exam.exam'), 'exam');

        $batch = Batch::query()
            ->byPeriod()
            ->filterAccessible()
            ->whereUuid($request->batch)
            ->getOrFail(trans('academic.batch.batch'), 'batch');

        $schedule = Schedule::query()
            ->whereExamId($exam->id)
            ->whereBatchId($batch->id)
            ->getOrFail(trans('exam.schedule.schedule'));

        $student = Student::query()
            ->summary()
            ->where('students.uuid', $request->student)
            ->getOrFail(trans('student.student'), 'student');

        if (empty($schedule->competency_id)) {
            throw ValidationException::withMessages(['message' => trans('exam.schedule.competency_evaluation_not_configured_for_this_schedule')]);
        }

        $competency = Competency::query()
            ->with('grade')
            ->whereId($schedule->competency_id)
            ->firstOrFail();

        $competencyRecord = CompetencyRecord::query()
            ->whereScheduleId($schedule->id)
            ->whereStudentId($student->id)
            ->first();

        return [
            'exam' => $exam,
            'batch' => $batch,
            'schedule' => $schedule,
            'student' => $student,
            'competency' => $competency,
            'competency_record' => $competencyRecord,
        ];
    }

    public function fetch(Request $request)
    {
        $data = $this->validateInput($request);

        $exam = $data['exam'];
        $batch = $data['batch'];
        $schedule = $data['schedule'];
        $student = $data['student'];
        $competency = $data['competency'];
        $competencyRecord = $data['competency_record'];

        $domains = collect($competency->domains)->map(function ($domain) use ($competencyRecord) {
            $domainCode = Arr::get($domain, 'code');

            $record = collect($competencyRecord?->records ?? [])->firstWhere('code', $domainCode);

            $indicators = collect(Arr::get($domain, 'indicators'))->map(function ($indicator) use ($record) {
                $indicatorCode = Arr::get($indicator, 'code');

                $record = collect(Arr::get($record, 'indicators') ?? [])->firstWhere('code', $indicatorCode);

                return [
                    'code' => $indicatorCode,
                    'position' => Arr::get($indicator, 'position'),
                    'name' => Arr::get($indicator, 'name'),
                    'obtained_grade' => Arr::get($record, 'obtained_grade', ''),
                ];
            });

            return [
                'code' => $domainCode,
                'position' => Arr::get($domain, 'position'),
                'name' => Arr::get($domain, 'name'),
                'indicators' => $indicators,
            ];
        });

        $grades = collect($competency->grade->records)->map(function ($record) {
            return [
                'label' => Arr::get($record, 'code'),
                'name' => Arr::get($record, 'name'),
                'value' => Arr::get($record, 'value'),
                'min_score' => Arr::get($record, 'min_score'),
                'max_score' => Arr::get($record, 'max_score'),
            ];
        });

        $markRecorded = $competencyRecord ? true : false;

        return compact('domains', 'grades', 'markRecorded');
    }

    public function store(Request $request)
    {
        $data = $this->validateInput($request);
        $input = $request->domains;

        $exam = $data['exam'];
        $batch = $data['batch'];
        $schedule = $data['schedule'];
        $student = $data['student'];

        $this->validateExamMarkLock($schedule);

        $competency = $data['competency'];
        $competencyRecord = $data['competency_record'];

        $grades = collect($competency->grade->records)->pluck('value')->toArray();

        $domains = collect($competency->domains)->map(function ($domain) use ($competencyRecord, $input, $grades) {
            $domainCode = Arr::get($domain, 'code');

            $records = collect($competencyRecord?->records ?? [])->firstWhere('code', $domainCode);
            $indicators = collect(Arr::get($domain, 'indicators'))->map(function ($indicator) use ($records, $domainCode, $input, $grades) {
                $indicatorCode = Arr::get($indicator, 'code');

                $record = collect(Arr::get($records, 'indicators') ?? [])->firstWhere('code', $indicatorCode);

                $inputDomain = collect($input)
                    ->firstWhere('code', $domainCode) ?? [];
                $inputIndicators = Arr::get($inputDomain, 'indicators', []);
                $inputIndicator = collect($inputIndicators)
                    ->firstWhere('code', $indicatorCode);
                $inputGrade = Arr::get($inputIndicator, 'obtained_grade');

                $obtainedGrade = '';
                if (in_array($inputGrade, $grades)) {
                    $obtainedGrade = (int) $inputGrade;
                }

                return [
                    'code' => $indicatorCode,
                    'position' => Arr::get($indicator, 'position'),
                    'name' => Arr::get($indicator, 'name'),
                    'obtained_grade' => $obtainedGrade,
                ];
            });

            return [
                'code' => $domainCode,
                'position' => Arr::get($domain, 'position'),
                'name' => Arr::get($domain, 'name'),
                'indicators' => $indicators,
            ];
        });

        if ($competencyRecord) {
            $competencyRecord->update([
                'records' => $domains,
            ]);
        } else {
            CompetencyRecord::create([
                'schedule_id' => $schedule->id,
                'student_id' => $student->id,
                'records' => $domains,
            ]);
        }
    }

    public function remove(Request $request)
    {
        $data = $this->validateInput($request);

        $schedule = $data['schedule'];
        $student = $data['student'];

        $this->validateExamMarkLock($schedule);

        $this->validateRemovalExamMark($schedule);

        CompetencyRecord::query()
            ->whereScheduleId($schedule->id)
            ->whereStudentId($student->id)
            ->delete();
    }
}
