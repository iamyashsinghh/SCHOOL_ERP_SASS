<?php

namespace App\Http\Controllers\Custom;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contact;
use App\Models\Tenant\Student\Student;
use Illuminate\Http\Request;

class SiblingGuardianController extends Controller
{
    public function __invoke(Request $request)
    {
        $students = Student::query()
            ->select('id', 'contact_id')
            ->get();

        $orderBy = $request->query('order_by', 'students_count');
        $sortBy = $request->query('sort_by', 'asc');

        $uniqueParents = Contact::query()
            ->select('father_name', 'mother_name', \DB::raw('COUNT(*) as students_count'))
            ->whereIn('id', $students->pluck('contact_id'))
            ->groupBy('father_name', 'mother_name')
            ->orderBy($orderBy, $sortBy)
            ->get();

        return view('custom.sibling-guardian', compact('uniqueParents', 'orderBy', 'sortBy'));
    }
}
