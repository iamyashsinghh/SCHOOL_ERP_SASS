<?php

namespace App\Services\Student;

use App\Contracts\ListGenerator;
use App\Http\Resources\Student\AccountsResource;
use App\Models\Tenant\Account;
use App\Models\Tenant\Contact;
use App\Models\Tenant\Student\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class AccountsListService extends ListGenerator
{
    protected $allowedSorts = ['created_at'];

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
                'print_label' => 'student.course_name',
                'print_sub_label' => 'student.batch_code',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'name',
                'label' => trans('finance.account.props.name'),
                'print_label' => 'name',
                'print_sub_label' => 'alias',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'number',
                'label' => trans('finance.account.props.number'),
                'print_label' => 'number',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'bankName',
                'label' => trans('finance.account.props.bank_name'),
                'print_label' => 'bank_name',
                'print_sub_label' => 'branch_name',
                'sortable' => false,
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

        return Account::query()
            ->with(['accountable'])
            ->whereHasMorph(
                'accountable', [Contact::class],
                function ($q) use ($accessibleStudentContactIds, $filteredStudentContactIds) {
                    $q->whereIn('id', $accessibleStudentContactIds)->when($filteredStudentContactIds, function ($q) use ($filteredStudentContactIds) {
                        $q->whereIn('id', $filteredStudentContactIds);
                    });
                }
            )
            ->filter([
                'App\QueryFilters\UuidMatch',
                'App\QueryFilters\ExactMatch:number',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        $records = $this->filter($request)
            ->orderBy($this->getSort(), $this->getOrder())
            ->paginate((int) $this->getPageLength(), ['*'], 'current_page');

        $contactIds = $records->pluck('accountable_id')->unique()->all();

        $students = Student::query()
            ->summary()
            ->whereIn('contact_id', $contactIds)
            ->get();

        $request->merge([
            'students' => $students,
        ]);

        return AccountsResource::collection($records)
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
