<?php

namespace App\Http\Controllers\Custom;

use App\Http\Controllers\Controller;
use App\Models\Finance\FeeConcession;
use Illuminate\Http\Request;

class SiblingConcessionCheckController extends Controller
{
    public function __invoke(Request $request)
    {
        $feeConcession = FeeConcession::query()
            ->byPeriod()
            ->where('name', $request->query('concession'))
            ->first();

        if (! $feeConcession) {
            return view('custom.sibling-concession-check', [
                'students' => [],
                'feeConcession' => null,
            ]);
        }

        $students = \DB::table('students')
            ->join('contacts', 'contacts.id', '=', 'students.contact_id')
            ->join('admissions', 'admissions.id', '=', 'students.admission_id')
            ->where('students.period_id', auth()->user()->current_period_id)
            ->where(function ($q) {
                $q->whereNull('admissions.leaving_date')
                    ->orWhere('admissions.leaving_date', '>', today()->toDateString());
            })
            ->whereExists(function ($q) use ($feeConcession) {
                $q->from('student_fees')
                    ->whereColumn('student_fees.student_id', 'students.id')
                    ->where('student_fees.fee_concession_id', $feeConcession->id);
            })
            ->whereRaw('
                (
                    SELECT COUNT(DISTINCT students2.id)
                    FROM students students2
                    JOIN contacts contacts2 ON contacts2.id = students2.contact_id
                    WHERE contacts2.father_name = contacts.father_name
                    AND contacts2.mother_name = contacts.mother_name
                ) = 1
            ')
            ->select('students.id', 'admissions.code_number', \DB::raw('CONCAT_WS(" ", contacts.first_name, contacts.middle_name, contacts.third_name, contacts.last_name) as name'), 'students.contact_id', 'contacts.father_name', 'contacts.mother_name')
            ->get();

        return view('custom.sibling-concession-check', compact('students', 'feeConcession'));
    }
}
