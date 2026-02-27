<?php

namespace App\Services\Student;

use App\Http\Resources\Academic\SubjectResource;
use App\Models\Academic\Period;
use App\Models\Academic\Subject;
use App\Models\Student\Student;
use App\Models\Student\SubjectWiseStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StudentWiseSubjectService
{
    public function fetch(Request $request, Student $student)
    {
        $cacheKey = "student_subject_{$student->uuid}";

        $batch = $student->batch;

        $electiveSubjects = SubjectResource::collection(Subject::query()
            ->withSubjectRecord($batch->id, $batch->course_id)
            ->where('subject_records.is_elective', true)
            ->get());

        // return Cache::remember($cacheKey, now()->addHours(24), function () use ($request, $student, $batch, $electiveSubjects) {
        $period = Period::query()
            ->findOrFail($student->period_id);

        $subjects = Subject::query()
            ->withSubjectRecord($batch->id, $batch->course_id)
            ->orderBy('subjects.position', 'asc')
            ->get();

        $subjectWiseStudents = SubjectWiseStudent::query()
            ->whereBatchId($batch->id)
            ->where('student_id', $student->id)
            ->get();

        $subjects = $subjects->filter(function ($subject) use ($subjectWiseStudents) {

            if (! $subject->is_elective) {
                return true;
            } elseif ($subject->is_elective && $subjectWiseStudents->firstWhere('subject_id', $subject->id)) {
                return true;
            }

            return false;
        })
            ->map(function ($subject) {
                return [
                    'uuid' => $subject->uuid,
                    'name' => $subject->name,
                    'alias' => $subject->alias,
                    'code' => $subject->code,
                    'shortcode' => $subject->shortcode,
                    'type' => $subject->type,
                    'position' => $subject->position,
                    'has_grading' => $subject->has_grading,
                    'is_elective' => $subject->is_elective,
                    'has_no_exam' => $subject->has_no_exam,
                    'credit' => $subject->credit,
                    'exam_fee' => $subject->exam_fee,
                    'course_fee' => $subject->course_fee,
                    'max_class_per_week' => $subject->max_class_per_week,
                ];
            })
            ->sortBy('position');

        return compact('subjects', 'electiveSubjects');
        // });
    }

    public function update(Request $request, Student $student)
    {
        $batch = $student->batch;

        $subjectUuids = $request->input('elective_subjects', []);

        $subjects = Subject::query()
            ->withSubjectRecord($batch->id, $batch->course_id)
            ->whereIn('subjects.uuid', $subjectUuids)
            ->get();

        SubjectWiseStudent::query()
            ->whereBatchId($batch->id)
            ->where('student_id', $student->id)
            ->delete();

        foreach ($subjects as $subject) {
            if ($subject->is_elective) {
                SubjectWiseStudent::forceCreate([
                    'student_id' => $student->id,
                    'subject_id' => $subject->id,
                    'batch_id' => $batch->id,
                ]);
            }
        }

        // $cacheKey = "student_subject_{$student->uuid}";
        // Cache::forget($cacheKey);
    }
}
