<?php

namespace App\Services\Student;

use App\Contracts\ListGenerator;
use App\Http\Resources\Student\QualificationsResource;
use App\Models\Tenant\Contact;
use App\Models\Tenant\Qualification;
use App\Models\Tenant\Student\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class QualificationsListService extends ListGenerator
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
                'key' => 'batch',
                'label' => trans('academic.batch.batch'),
                'print_label' => 'student.course_name',
                'print_sub_label' => 'student.batch_code',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'course',
                'label' => trans('student.qualification.props.course'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'institute',
                'label' => trans('student.qualification.props.institute'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'level',
                'label' => trans('student.qualification_level.qualification_level'),
                'print_label' => 'level.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'period',
                'label' => trans('general.period'),
                'print_label' => 'period',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'result',
                'label' => trans('student.qualification.props.result'),
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

        return Qualification::query()
            ->with(['model', 'level'])
            ->whereHasMorph(
                'model', [Contact::class],
                function ($q) use ($accessibleStudentContactIds, $filteredStudentContactIds) {
                    $q->whereIn('id', $accessibleStudentContactIds)->when($filteredStudentContactIds, function ($q) use ($filteredStudentContactIds) {
                        $q->whereIn('id', $filteredStudentContactIds);
                    });
                }
            )
            ->filter([
                'App\QueryFilters\UuidMatch',
                'App\QueryFilters\ExactMatch:course',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        $records = $this->filter($request)
            ->orderBy($this->getSort(), $this->getOrder())
            ->paginate((int) $this->getPageLength(), ['*'], 'current_page');

        $contactIds = $records->pluck('model_id')->unique()->all();

        $students = Student::query()
            ->summary()
            ->whereIn('contact_id', $contactIds)
            ->get();

        $request->merge([
            'students' => $students,
        ]);

        return QualificationsResource::collection($records)
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
