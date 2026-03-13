<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Academic\Course;
use App\Models\Tenant\Academic\Batch;
use App\Models\Tenant\Academic\Subject;
use App\Models\Tenant\Academic\TimetableAllocation;
use Illuminate\Http\Request;

class MobileAcademicController extends Controller
{
    public function courses(Request $request)
    {
        $courses = Course::query()
            ->byPeriod()
            ->with('division:id,name')
            ->orderBy('position', 'asc')
            ->get(['id', 'name', 'code', 'term', 'division_id']);

        return response()->json([
            'data' => $courses->map(function($course) {
                return [
                    'id' => $course->id,
                    'name' => $course->name_with_term_and_division,
                    'code' => $course->code,
                ];
            })
        ]);
    }

    public function batches(Request $request)
    {
        $request->validate([
            'course_id' => 'required|integer|exists:courses,id'
        ]);

        $batches = Batch::query()
            ->where('course_id', $request->course_id)
            ->orderBy('position', 'asc')
            ->get(['id', 'name', 'course_id']);

        return response()->json([
            'data' => $batches->map(function($batch) {
                return [
                    'id' => $batch->id,
                    'name' => $batch->name,
                ];
            })
        ]);
    }

    public function subjects(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|integer|exists:batches,id'
        ]);

        $batch = Batch::with(['subjectRecords.subject' => function($q) {
            $q->select('id', 'name', 'code', 'type');
        }])->findOrFail($request->batch_id);

        $subjects = $batch->subjectRecords->map(function($record) {
            return [
                'id' => $record->subject->id,
                'name' => $record->subject->name,
                'code' => $record->subject->code,
                'type' => $record->subject->type,
                'credits' => $record->credit,
            ];
        });

        return response()->json([
            'data' => $subjects
        ]);
    }

    public function timetable(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|integer|exists:batches,id'
        ]);

        // Simple mock implementation of timetable parsing for now
        // This will heavily depend on `TimetableAllocation` or relevant models in the project
        return response()->json([
            'data' => [
                'monday' => [],
                'tuesday' => [],
                'wednesday' => [],
                'thursday' => [],
                'friday' => [],
                'saturday' => [],
            ]
        ]);
    }
}
