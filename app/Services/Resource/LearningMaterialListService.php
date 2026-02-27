<?php

namespace App\Services\Resource;

use App\Contracts\ListGenerator;
use App\Http\Resources\Resource\LearningMaterialResource;
use App\Models\Resource\LearningMaterial;
use App\Models\Student\Student;
use App\Support\HasFilterByAssignedSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class LearningMaterialListService extends ListGenerator
{
    use HasFilterByAssignedSubject;

    protected $allowedSorts = ['created_at', 'published_at'];

    protected $defaultSort = 'published_at';

    protected $defaultOrder = 'desc';

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'title',
                'label' => trans('resource.learning_material.props.title'),
                'print_label' => 'title_excerpt',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'records',
                'label' => trans('academic.batch.batch'),
                'type' => 'array',
                'print_key' => 'batch.course.name,batch.name',
                'print_sub_key' => 'subject.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'studentRecords',
                'label' => trans('student.student'),
                'type' => 'array',
                'print_label' => 'students',
                'print_key' => 'name',
                'print_sub_key' => 'course_name,batch_name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'employee',
                'label' => trans('employee.employee'),
                'print_label' => 'employee.name',
                'print_sub_label' => 'employee.code_number',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'publishedAt',
                'label' => trans('resource.learning_material.props.published_at'),
                'print_label' => 'published_at.formatted',
                'sortable' => true,
                'visibility' => true,
            ],
            // [
            //     'key' => 'createdAt',
            //     'label' => trans('general.created_at'),
            //     'print_label' => 'created_at.formatted',
            //     'sortable' => true,
            //     'visibility' => true,
            // ],
        ];

        if (request()->ajax()) {
            $headers[] = $this->actionHeader;
        }

        return $headers;
    }

    public function filter(Request $request): Builder
    {
        $employees = Str::toArray($request->query('employees'));
        $students = Str::toArray($request->query('students'));
        $subjects = Str::toArray($request->query('subjects'));
        $batches = Str::toArray($request->query('batches'));

        $filterSubjectUuids = $this->getFilteredSubjects();

        return LearningMaterial::query()
            ->byPeriod()
            ->withUserId()
            ->filterAccessible()
            ->with([
                'audiences',
                'records.subject',
                'records.batch.course',
                'employee' => fn ($q) => $q->summary(),
            ])
            ->when($employees, function ($q, $employees) {
                $q->whereHas('employee', function ($q) use ($employees) {
                    $q->whereIn('uuid', $employees);
                });
            })
            ->when($subjects, function ($q, $subjects) {
                $q->whereHas('records', function ($q) use ($subjects) {
                    $q->whereHas('subject', function ($q) use ($subjects) {
                        $q->whereIn('uuid', $subjects);
                    });
                });
            })
            ->when($filterSubjectUuids, function ($q, $filterSubjectUuids) {
                $q->whereHas('records', function ($q) use ($filterSubjectUuids) {
                    $q->whereHas('subject', function ($q) use ($filterSubjectUuids) {
                        $q->whereIn('uuid', $filterSubjectUuids);
                    });
                });
            })
            ->when($batches, function ($q, $batches) {
                $q->whereHas('records', function ($q) use ($batches) {
                    $q->whereHas('batch', function ($q) use ($batches) {
                        $q->whereIn('uuid', $batches);
                    });
                });
            })
            ->filter([
                'App\QueryFilters\LikeMatch:search,title,description',
                'App\QueryFilters\LikeMatch:title',
                'App\QueryFilters\UuidMatch',
                'App\QueryFilters\DateBetween:start_date,end_date,published_at,datetime',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        if (auth()->user()->is_student_or_guardian) {
            $view = $request->query('view', 'card');
            $request->merge(['view' => $view]);
        }

        $records = $this->filter($request)
            ->orderBy($this->getSort(), $this->getOrder())
            ->paginate((int) $this->getPageLength(), ['*'], 'current_page');

        $studentIds = [];
        foreach ($records as $record) {
            if ($record->audiences->count() > 0) {
                $studentIds = array_merge($studentIds, $record->audiences->pluck('audienceable_id')->all());
            }
        }

        $students = Student::query()
            ->byPeriod()
            ->summary()
            ->whereIn('students.id', $studentIds)
            ->get();

        $request->merge([
            'students' => $students,
        ]);

        return LearningMaterialResource::collection($records)
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
