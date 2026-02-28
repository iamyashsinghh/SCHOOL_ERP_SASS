<?php

namespace App\Services\Student;

use App\Contracts\ListGenerator;
use App\Http\Resources\Student\DocumentsResource;
use App\Models\Tenant\Contact;
use App\Models\Tenant\Document;
use App\Models\Tenant\Student\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class DocumentsListService extends ListGenerator
{
    protected $allowedSorts = ['created_at', 'issue_date', 'start_date', 'end_date', 'expiry_in_days'];

    protected $defaultSort = 'created_at';

    protected $defaultOrder = 'desc';

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'student',
                'label' => trans('student.student'),
                'print_label' => 'student.name',
                'print_sub_label' => 'student.code_number',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'course',
                'label' => trans('academic.course.course'),
                'print_label' => 'student.course_name + student.batch_name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'documentType',
                'label' => trans('student.document_type.document_type'),
                'print_label' => 'type.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'title',
                'label' => trans('student.document.props.title'),
                'print_label' => 'title',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'number',
                'label' => trans('student.document.props.number'),
                'print_label' => 'number',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'startDate',
                'label' => trans('student.document.props.start_date'),
                'print_label' => 'start_date.formatted',
                'print_sub_label' => 'issue_date.formatted',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'endDate',
                'label' => trans('student.document.props.end_date'),
                'print_label' => 'end_date.formatted',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'expiryInDays',
                'label' => trans('student.document.props.expiry_in_days'),
                'print_label' => 'expiry_in_days',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'createdAt',
                'label' => trans('general.created_at'),
                'print_label' => 'created_at.formatted',
                'sortable' => true,
                'visibility' => true,
            ],
        ];

        if (request()->ajax()) {
            $headers[] = $this->actionHeader;
        }

        return $headers;
    }

    public function filter(Request $request): Builder
    {
        $accessibleStudents = Student::query()
            ->basic()
            ->byPeriod()
            ->filterAccessible()
            ->get();

        $accessibleStudentContactIds = $accessibleStudents->pluck('contact_id')->all();

        $students = Str::toArray($request->query('students'));
        $filteredStudentContactIds = [];
        if ($students) {
            $filteredStudentContactIds = $accessibleStudents->whereIn('uuid', $students)->pluck('contact_id')->all();
        }

        $documentTypes = Str::toArray($request->query('types'));
        $expiryInDays = $request->query('expiry_in_days');

        return Document::query()
            ->with(['documentable', 'type'])
            ->select('documents.*')
            ->selectRaw('DATEDIFF(end_date, CURDATE()) as expiry_in_days')
            ->whereHasMorph(
                'documentable', [Contact::class],
                function ($q) use ($accessibleStudentContactIds, $filteredStudentContactIds) {
                    $q->whereIn('id', $accessibleStudentContactIds)->when($filteredStudentContactIds, function ($q) use ($filteredStudentContactIds) {
                        $q->whereIn('id', $filteredStudentContactIds);
                    });
                }
            )
            ->when($documentTypes, function ($q, $documentTypes) {
                $q->whereHas('type', function ($q) use ($documentTypes) {
                    $q->whereIn('uuid', $documentTypes);
                });
            })
            ->when($expiryInDays, function ($q, $expiryInDays) {
                $q->havingRaw('DATEDIFF(end_date, CURDATE()) <= ?', [$expiryInDays]);
            })
            ->filter([
                'App\QueryFilters\UuidMatch',
                'App\QueryFilters\ExactMatch:number',
                'App\QueryFilters\DateBetween:issue_start_date,issue_end_date,issue_date',
                'App\QueryFilters\DateBetween:start_start_date,start_end_date,start_date',
                'App\QueryFilters\DateBetween:end_start_date,end_end_date,end_date',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        $records = $this->filter($request)
            ->orderBy($this->getSort(), $this->getOrder())
            ->paginate((int) $this->getPageLength(), ['*'], 'current_page');

        $contactIds = $records->pluck('documentable_id')->unique()->all();

        $students = Student::query()
            ->summary()
            ->whereIn('contact_id', $contactIds)
            ->get();

        $request->merge([
            'students' => $students,
        ]);

        return DocumentsResource::collection($records)
            ->additional([
                'headers' => $this->getHeaders(),
                'meta' => [
                    'allowed_sorts' => $this->allowedSorts,
                    'default_sort' => $this->defaultSort,
                    'default_order' => $this->defaultOrder,
                ],
            ]);
    }

    public function list(Request $request): AnonymousResourceCollection
    {
        return $this->paginate($request);
    }
}
