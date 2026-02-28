<?php

namespace App\Services\Student\Report;

use App\Enums\Gender;
use App\Exports\Student\AllSubjectExport;
use App\Exports\Student\SubjectExport;
use App\Http\Resources\Academic\SubjectResource;
use App\Models\Tenant\Academic\Batch;
use App\Models\Tenant\Academic\Subject;
use App\Models\Tenant\Academic\SubjectRecord;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Incharge;
use App\Models\Tenant\Student\Student;
use App\Models\Tenant\Student\SubjectWiseStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class SubjectWiseStudentService
{
    public function preRequisite(): array
    {
        $batches = Batch::getList();

        $subjects = SubjectResource::collection(Subject::query()
            ->byPeriod()
            ->orderBy('position', 'asc')
            ->get());

        return compact('batches', 'subjects');
    }

    public function export(Request $request)
    {
        $subjectWiseStudents = collect([]);

        $batches = Str::toArray($request->query('batches'));
        $subjects = Str::toArray($request->query('subjects'));

        $batches = Batch::query()
            ->byPeriod()
            ->whereIn('uuid', $batches)
            ->get();

        if ($batches->isEmpty()) {
            abort(404);
        }

        if (empty($subjects)) {
            return $this->exportAllSubjects($request, $batches);
        }

        $subjects = Subject::query()
            ->byPeriod()
            ->when($subjects, function ($query, $subjects) {
                $query->whereIn('uuid', $subjects);
            })
            ->orderBy('position', 'asc')
            ->get();

        $subjectRecords = SubjectRecord::query()
            ->whereIn('subject_id', $subjects->pluck('id'))
            ->get();

        $subjectIncharges = Incharge::query()
            ->where('model_type', 'Subject')
            ->whereIn('model_id', $subjects->pluck('id'))
            ->where(function ($q) {
                $q->where('start_date', '<=', today()->toDateString())
                    ->where(function ($q) {
                        $q->whereNull('end_date')
                            ->orWhere('end_date', '>=', today()->toDateString());
                    });
            })
            ->get();

        $employees = Employee::query()
            ->summary()
            ->whereIn('employees.id', $subjectIncharges->pluck('employee_id'))
            ->get();

        $subjectWiseStudents = SubjectWiseStudent::query()
            ->whereIn('batch_id', $batches->pluck('id'))
            ->when($request->query('subject'), function ($query, $subject) {
                $query->whereHas('subject', function ($query) use ($subject) {
                    $query->where('uuid', $subject);
                });
            })
            ->get();

        $students = Student::query()
            ->byPeriod()
            ->detail()
            ->filterAccessible()
            ->filterStudying()
            ->whereIn('students.batch_id', $batches->pluck('id'))
            ->when($request->query('subject'), function ($query, $subject) use ($subjectWiseStudents) {
                $query->whereIn('students.id', $subjectWiseStudents->pluck('student_id'));
            })
            ->get();

        $header = [
            trans('student.admission.props.code_number'),
            trans('student.roll_number.roll_number'),
            trans('student.props.name'),
            trans('academic.course.course'),
            trans('contact.props.gender'),
            trans('contact.category.category'),
            trans('contact.props.address.address'),
            trans('academic.subject.subject'),
            trans('academic.subject.props.is_elective'),
            trans('academic.subject.props.credit'),
            trans('academic.subject.props.max_class_per_week'),
            trans('academic.subject_incharge.subject_incharge'),
        ];

        $studentsGroupByBatch = $students->groupBy('batch_id');

        $data = [];

        foreach ($studentsGroupByBatch as $batchId => $students) {
            $batch = $batches->where('id', $batchId)->first();

            foreach ($students as $student) {
                $address = json_decode($student->address, true);

                $row = [];
                $studentRow = [
                    $student->code_number,
                    $student->roll_number,
                    $student->name,
                    $student->course_name.' - '.$student->batch_name,
                    Arr::get(Gender::getDetail($student->gender), 'label'),
                    $student->category_name,
                    Arr::toAddress([
                        'address_line1' => Arr::get($address, 'present.address_line1'),
                        'address_line2' => Arr::get($address, 'present.address_line2'),
                        'city' => Arr::get($address, 'present.city'),
                        'state' => Arr::get($address, 'present.state'),
                        'zipcode' => Arr::get($address, 'present.zipcode'),
                        'country' => Arr::get($address, 'present.country'),
                    ]),
                ];

                foreach ($subjects as $subject) {
                    $subjectRecord = $subjectRecords->filter(function ($record) use ($subject, $student, $batch) {
                        return $record->subject_id == $subject->id && ($record->course_id == $batch?->course_id || $record->batch_id == $student->batch_id);
                    })->first();

                    if (! $subjectRecord) {
                        continue;
                    }

                    $incharges = $subjectIncharges->filter(function ($incharge) use ($subject, $student) {
                        return $incharge->model_id == $subject->id && $incharge->detail_id == $student->batch_id;
                    });

                    $incharge = $incharges->isNotEmpty() ? $employees->whereIn('id', $incharges->pluck('employee_id'))->pluck('name')->implode(', ') : null;

                    if (! $subjectRecord->is_elective) {
                        $row[] = [
                            ...$studentRow,
                            $subject->name,
                            'Compulsory',
                            $subjectRecord->credit,
                            $subjectRecord->max_class_per_week,
                            $incharge,
                        ];
                    } else {

                        if (! $subjectWiseStudents->where('student_id', $student->id)->where('subject_id', $subject->id)->first()) {
                            continue;
                        }

                        $row[] = [
                            ...$studentRow,
                            $subject->name,
                            'Elective',
                            $subjectRecord->credit,
                            $subjectRecord->max_class_per_week,
                            $incharge,
                        ];
                    }
                }

                $data[] = $row;
            }
        }

        array_unshift($data, $header);

        return Excel::download(new SubjectExport($data), 'Subject Export.xlsx');
    }

    private function exportAllSubjects(Request $request, Collection $batches)
    {
        $subjects = Subject::query()
            ->byPeriod()
            ->orderBy('position', 'asc')
            ->get();

        $subjectRecords = SubjectRecord::query()
            ->whereIn('course_id', $batches->pluck('course_id'))
            ->orWhereIn('batch_id', $batches->pluck('id'))
            ->get();

        $filteredSubjectIds = $subjectRecords->pluck('subject_id')->unique();

        $subjectIncharges = Incharge::query()
            ->where('model_type', 'Subject')
            ->whereIn('model_id', $filteredSubjectIds)
            ->where(function ($q) {
                $q->where('start_date', '<=', today()->toDateString())
                    ->where(function ($q) {
                        $q->whereNull('end_date')
                            ->orWhere('end_date', '>=', today()->toDateString());
                    });
            })
            ->get();

        $employees = Employee::query()
            ->summary()
            ->whereIn('employees.id', $subjectIncharges->pluck('employee_id'))
            ->get();

        $subjectWiseStudents = SubjectWiseStudent::query()
            ->whereIn('batch_id', $batches->pluck('id'))
            ->get();

        $students = Student::query()
            ->byPeriod()
            ->detail()
            ->filterAccessible()
            ->filterStudying()
            ->whereIn('students.batch_id', $batches->pluck('id'))
            ->get();

        $header = [
            trans('student.admission.props.code_number'),
            trans('student.roll_number.roll_number'),
            trans('student.props.name'),
            trans('academic.course.course'),
            trans('contact.props.gender'),
            trans('contact.category.category'),
            trans('contact.props.address.address'),
            // trans('academic.subject.subject'),
            // trans('academic.subject.props.is_elective'),
            // trans('academic.subject.props.credit'),
            // trans('academic.subject.props.max_class_per_week'),
            // trans('academic.subject_incharge.subject_incharge'),
        ];

        $studentsGroupByBatch = $students->groupBy('batch_id');

        $data = [];

        $maxSubjectsCount = 0;
        foreach ($studentsGroupByBatch as $batchId => $students) {
            $batch = $batches->where('id', $batchId)->first();

            $compulsorySubjectRecords = $subjectRecords->filter(function ($record) use ($batch) {
                return ($record->batch_id == $batch->id || $record->course_id == $batch->course_id) && ! $record->is_elective;
            });

            $electiveSubjectRecords = $subjectRecords->filter(function ($record) use ($batch) {
                return ($record->batch_id == $batch->id || $record->course_id == $batch->course_id) && $record->is_elective;
            });

            foreach ($students as $student) {
                $address = json_decode($student->address, true);

                $row = [];
                $studentRow = [
                    $student->code_number,
                    $student->roll_number,
                    $student->name,
                    $student->course_name.' - '.$student->batch_name,
                    Arr::get(Gender::getDetail($student->gender), 'label'),
                    $student->category_name,
                    Arr::toAddress([
                        'address_line1' => Arr::get($address, 'present.address_line1'),
                        'address_line2' => Arr::get($address, 'present.address_line2'),
                        'city' => Arr::get($address, 'present.city'),
                        'state' => Arr::get($address, 'present.state'),
                        'zipcode' => Arr::get($address, 'present.zipcode'),
                        'country' => Arr::get($address, 'present.country'),
                    ]),
                ];

                $subjectData = [];
                $subjectCount = 0;
                foreach ($compulsorySubjectRecords as $subjectRecord) {
                    $subjectName = $subjects->firstWhere('id', $subjectRecord->subject_id)?->name;

                    $subjectCount++;

                    $incharge = $this->getInchargeName($subjectIncharges, $employees, $subjectRecord->subject_id, $student);

                    // $subjectDetail .= ' ' . trans('academic.subject.props.credit').': '.$subjectRecord->credit;
                    // $subjectDetail .= ' ' . trans('academic.subject.props.max_class_per_week').': '.$subjectRecord->max_class_per_week;
                    // if ($incharge) {
                    //     $subjectDetail .= ' ' . trans('academic.subject_incharge.subject_incharge').': '.$incharge;
                    // }

                    $subjectData[] = $subjectName;
                    $subjectData[] = $subjectRecord->credit;
                    $subjectData[] = $subjectRecord->max_class_per_week;
                    $subjectData[] = $incharge;
                }

                foreach ($electiveSubjectRecords as $subjectRecord) {
                    if (! $subjectWiseStudents->where('student_id', $student->id)->where('subject_id', $subjectRecord->subject_id)->first()) {
                        continue;
                    }

                    $subjectName = $subjects->firstWhere('id', $subjectRecord->subject_id)?->name;

                    $subjectCount++;

                    $incharge = $this->getInchargeName($subjectIncharges, $employees, $subjectRecord->subject_id, $student);

                    // $subjectDetail .= ' ('.trans('academic.subject.elective').')';
                    // $subjectDetail .= ' ' . trans('academic.subject.props.credit').': '.$subjectRecord->credit;
                    // $subjectDetail .= ' ' . trans('academic.subject.props.max_class_per_week').': '.$subjectRecord->max_class_per_week;
                    // if ($incharge) {
                    //     $subjectDetail .= ' ' . trans('academic.subject_incharge.subject_incharge').': '.$incharge;
                    // }

                    $subjectData[] = $subjectName.' '.trans('academic.subject.elective');
                    $subjectData[] = $subjectRecord->credit;
                    $subjectData[] = $subjectRecord->max_class_per_week;
                    $subjectData[] = $incharge;
                }

                if ($subjectCount > $maxSubjectsCount) {
                    $maxSubjectsCount = $subjectCount;
                }

                $row[] = [
                    ...$studentRow,
                    ...$subjectData,
                ];

                $data[] = $row;
            }
        }

        for ($i = 1; $i <= $maxSubjectsCount; $i++) {
            array_push($header, trans('academic.subject.subject').' '.$i);
            // array_push($header, trans('academic.subject.props.credit').' '.$i);
            // array_push($header, trans('academic.subject.props.max_class_per_week').' '.$i);
            // array_push($header, trans('academic.subject_incharge.subject_incharge').' '.$i);
            array_push($header, '');
            array_push($header, '');
            array_push($header, '');
        }

        $subHeader = array_fill(0, 7, '');
        for ($i = 1; $i <= $maxSubjectsCount; $i++) {
            array_push($subHeader, trans('academic.subject.props.name'));
            array_push($subHeader, trans('academic.subject.props.credit'));
            array_push($subHeader, trans('academic.subject.props.max_class_per_week'));
            array_push($subHeader, trans('academic.subject_incharge.subject_incharge'));
        }

        logger($subHeader);

        array_unshift($data, $subHeader);
        array_unshift($data, $header);

        return Excel::download(new AllSubjectExport($data, $maxSubjectsCount), 'Subject Export.xlsx');
    }

    private function getInchargeName(Collection $subjectIncharges, Collection $employees, $subjectId, $student): ?string
    {
        $incharges = $subjectIncharges->filter(function ($incharge) use ($subjectId, $student) {
            return $incharge->model_id == $subjectId && $incharge->detail_id == $student->batch_id;
        });

        $incharge = $incharges->isNotEmpty() ? $employees->whereIn('id', $incharges->pluck('employee_id'))->pluck('name')->implode(', ') : null;

        return $incharge;
    }
}
