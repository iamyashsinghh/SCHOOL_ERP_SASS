<?php

namespace App\Services\Finance\Report;

use App\Contracts\ListGenerator;
use App\Http\Resources\Finance\Report\FeeConcessionSummaryListResource;
use App\Models\Student\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class FeeConcessionSummaryListService extends ListGenerator
{
    protected $allowedSorts = ['created_at', 'code_number', 'name', 'concession'];

    protected $defaultSort = 'code_number';

    protected $defaultOrder = 'asc';

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'codeNumber',
                'label' => trans('student.admission.props.code_number'),
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'name',
                'label' => trans('student.props.name'),
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'fatherName',
                'label' => trans('contact.props.father_name'),
                'print_label' => 'father_name',
                'print_sub_label' => 'contact_number',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'course',
                'label' => trans('academic.course.course'),
                'print_label' => 'course_name + batch_name',
                // 'print_sub_label' => 'batch_name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'category',
                'label' => trans('contact.category.category'),
                'print_label' => 'category_name',
                'sortable' => false,
                'visibility' => false,
            ],
            [
                'key' => 'concession',
                'label' => trans('finance.fee.concession'),
                'print_label' => 'concession.formatted',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'feeConcessionType',
                'label' => trans('finance.fee_concession.type.type'),
                'print_label' => 'fee_concession_type',
                'sortable' => false,
                'visibility' => true,
            ],
        ];

        // if (request()->ajax()) {
        //     $headers[] = $this->actionHeader;
        // }

        return $headers;
    }

    public function filter(Request $request): Builder
    {
        $concessionSubquery = \DB::table('student_fee_records')
            ->select('student_fees.student_id', \DB::raw('SUM(student_fee_records.concession) as total_concession'))
            ->join('student_fees', 'student_fees.id', '=', 'student_fee_records.student_fee_id')
            ->groupBy('student_fees.student_id');

        return Student::query()
            ->select('students.id', 'students.uuid', 'students.roll_number', 'students.batch_id', 'students.contact_id', \DB::raw('REGEXP_REPLACE(CONCAT_WS(" ", first_name, middle_name, third_name, last_name), "[[:space:]]+", " ") as name'), 'admissions.code_number', 'admissions.joining_date', 'admissions.leaving_date', 'batches.uuid as batch_uuid', 'batches.name as batch_name', 'courses.uuid as course_uuid', 'courses.name as course_name', 'contacts.father_name', 'contacts.contact_number', 'categories.name as category_name', 'concessions.total_concession as concession', 'fee_concession_types.name as fee_concession_type')
            ->join('contacts', 'contacts.id', '=', 'students.contact_id')
            ->join('admissions', 'admissions.id', '=', 'students.admission_id')
            ->join('batches', 'batches.id', '=', 'students.batch_id')
            ->join('courses', 'courses.id', '=', 'batches.course_id')
            ->leftJoin('options as fee_concession_types', 'fee_concession_types.id', '=', 'students.fee_concession_type_id')
            ->leftJoin('options as categories', 'contacts.category_id', '=', 'categories.id')
            ->leftJoinSub($concessionSubquery, 'concessions', function ($join) {
                $join->on('concessions.student_id', '=', 'students.id');
            })
            ->havingRaw('concession > 0')
            ->when($request->query('name'), function ($q, $name) {
                $q->where(\DB::raw('REGEXP_REPLACE(CONCAT_WS(" ", first_name, middle_name, third_name, last_name), "[[:space:]]+", " ")'), 'like', "%{$name}%");
            })
            ->when($request->query('fee_concession_type'), function ($q, $feeConcessionType) {
                $q->where('fee_concession_types.uuid', $feeConcessionType);
            })
            ->when($request->query('category'), function ($q, $categoryUuid) {
                $q->where('categories.uuid', '=', $categoryUuid);
            })
            ->filter([
                'App\QueryFilters\LikeMatch:code_number',
                'App\QueryFilters\WhereInMatch:batches.uuid,batches',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        $batches = Str::toArray($request->query('batches'));

        $studentIds = Student::query()
            ->select('students.id')
            ->byPeriod()
            ->filterAccessible()
            ->pluck('id')
            ->all();

        $summary = \DB::table('student_fee_records')
            ->join('student_fees', 'student_fees.id', '=', 'student_fee_records.student_fee_id')
            ->join('students', 'students.id', '=', 'student_fees.student_id')
            ->join('contacts', 'contacts.id', '=', 'students.contact_id')
            ->join('admissions', 'admissions.id', '=', 'students.admission_id')
            ->join('batches', 'batches.id', '=', 'students.batch_id')
            ->leftJoin('options as fee_concession_types', 'fee_concession_types.id', '=', 'students.fee_concession_type_id')
            ->leftJoin('options as categories', 'contacts.category_id', '=', 'categories.id')
            ->whereIn('student_fees.student_id', $studentIds)
            ->when($request->query('name'), function ($q, $name) {
                $q->where(\DB::raw('REGEXP_REPLACE(CONCAT_WS(" ", first_name, middle_name, third_name, last_name), "[[:space:]]+", " ")'), 'like', "%{$name}%");
            })
            ->when($request->query('code_number'), function ($q, $codeNumber) {
                $q->where('admissions.code_number', $codeNumber);
            })
            ->when($batches, function ($q, $batches) {
                $q->whereIn('batches.uuid', $batches);
            })
            ->when($request->query('category'), function ($q, $categoryUuid) {
                $q->where('categories.uuid', '=', $categoryUuid);
            })
            ->when($request->query('fee_concession_type'), function ($q, $feeConcessionType) {
                $q->where('fee_concession_types.uuid', $feeConcessionType);
            })
            ->selectRaw('SUM(student_fee_records.concession) as total_concession')
            ->first();

        return FeeConcessionSummaryListResource::collection($this->filter($request)
            ->whereIn('student_id', $studentIds)
            ->orderBy($this->getSort(), $this->getOrder())
            ->paginate((int) $this->getPageLength(), ['*'], 'current_page'))
            ->additional([
                'headers' => $this->getHeaders(),
                'meta' => [
                    'filename' => 'Fee Concession Report',
                    'sno' => $this->getSno(),
                    'allowed_sorts' => $this->allowedSorts,
                    'default_sort' => $this->defaultSort,
                    'default_order' => $this->defaultOrder,
                    'has_footer' => true,
                ],
                'footers' => [
                    ['key' => 'codeNumber', 'label' => trans('general.total')],
                    ['key' => 'name', 'label' => ''],
                    ['key' => 'fatherName', 'label' => ''],
                    ['key' => 'course', 'label' => ''],
                    ['key' => 'concession', 'label' => \Price::from($summary->total_concession)->formatted],
                ],
            ]);
    }

    public function list(Request $request): AnonymousResourceCollection
    {
        return $this->paginate($request);
    }
}
