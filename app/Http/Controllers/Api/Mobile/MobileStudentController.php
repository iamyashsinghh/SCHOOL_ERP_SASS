<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Student\Student;
use App\Models\Tenant\Student\Fee;
use App\Models\Tenant\Student\Attendance;
use Illuminate\Http\Request;

class MobileStudentController extends Controller
{
    public function index(Request $request)
    {
        // Simple search by name and filtering by batch
        $query = Student::query()
            ->with(['contact', 'batch.course'])
            ->byPeriod();

        if ($request->filled('q')) {
            $query->whereHas('contact', function ($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->q}%")
                  ->orWhere('last_name', 'like', "%{$request->q}%");
            });
        }

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        $students = $query->paginate(20);

        return response()->json([
            'data' => collect($students->items())->map(function($student) {
                return [
                    'id' => $student->id,
                    'uuid' => $student->uuid,
                    'name' => $student->contact?->name,
                    'avatar' => $student->photo_url,
                    'enrollment_number' => $student->enrollment_number,
                    'course' => $student->batch?->course?->name,
                    'batch' => $student->batch?->name,
                ];
            }),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'total' => $students->total(),
            ]
        ]);
    }

    public function show($id)
    {
        $student = Student::with(['contact', 'batch.course', 'guardian.contact'])
            ->byPeriod()
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $student->id,
                'uuid' => $student->uuid,
                'name' => $student->contact?->name,
                'avatar' => $student->photo_url,
                'enrollment_number' => $student->enrollment_number,
                'email' => $student->contact?->email,
                'phone' => $student->contact?->contact_number,
                'gender' => $student->contact?->gender,
                'birth_date' => $student->contact?->birth_date?->format('Y-m-d'),
                'course' => $student->batch?->course?->name,
                'batch' => $student->batch?->name,
                'blood_group' => $student->contact?->blood_group,
                'guardian_name' => $student->guardian?->contact?->name,
                'guardian_phone' => $student->guardian?->contact?->contact_number,
            ]
        ]);
    }

    public function attendance($id)
    {
        $student = Student::findOrFail($id);
        
        // This relies on the system's Student model helper
        $summary = $student->getAttendanceSummary();

        return response()->json([
            'summary' => $summary,
            'records' => [] // Will fetch detailed logs if schema details map correctly later
        ]);
    }

    public function fees($id)
    {
        $student = Student::findOrFail($id);
        
        $summary = $student->getFeeSummary();

        return response()->json([
            'summary' => [
                'total' => $summary['total_fee']->value,
                'paid' => $summary['paid_fee']->value,
                'balance' => $summary['balance_fee']->value,
            ],
            // 'installments' could be fetched from Fee model relations
            'installments' => []
        ]);
    }
}
