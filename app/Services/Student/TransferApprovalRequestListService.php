<?php

namespace App\Services\Student;

use App\Contracts\ListGenerator;
use App\Enums\Approval\Category;
use App\Enums\Approval\Event;
use App\Enums\OptionType;
use App\Http\Resources\Student\TransferApprovalRequestResource;
use App\Models\Approval\Request as ApprovalRequest;
use App\Models\Option;
use App\Models\Student\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransferApprovalRequestListService extends ListGenerator
{
    protected $allowedSorts = ['date'];

    protected $defaultSort = 'date';

    protected $defaultOrder = 'desc';

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'codeNumber',
                'label' => trans('approval.request.props.code_number'),
                'print_label' => 'code_number',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'studentCodeNumber',
                'label' => trans('student.admission.props.code_number'),
                'print_label' => 'student_code_number',
                'print_sub_label' => 'transfer_certificate_number',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'name',
                'label' => trans('contact.props.name'),
                'print_label' => 'name',
                'print_sub_label' => 'contact_number',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'parent',
                'label' => trans('student.props.parent'),
                'print_label' => 'father_name',
                'print_sub_label' => 'mother_name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'admissionDate',
                'label' => trans('student.admission.props.date'),
                'print_label' => 'joining_date.formatted',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'course',
                'label' => trans('academic.course.course'),
                'print_label' => 'course_name + batch_name',
                // 'print_sub_label' => 'batch_name',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'transferDate',
                'label' => trans('student.transfer.props.date'),
                'print_label' => 'date.formatted',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'reason',
                'label' => trans('student.transfer.props.reason'),
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'requester',
                'label' => trans('approval.request.props.requester'),
                'print_label' => 'request_user.name',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'status',
                'label' => trans('approval.request.props.status'),
                'print_label' => 'status.label',
                'sortable' => false,
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
        return ApprovalRequest::query()
            ->with('requestUser')
            ->whereHas('type', function ($q) {
                $q->byTeam()
                    ->where('category', Category::EVENT_BASED)
                    ->where('event', Event::STUDENT_TRANSFER);
            })
            ->when(! auth()->user()->hasRole('admin'), function ($q) {
                $q->where('request_user_id', auth()->id());
            })
            ->filter([
                'App\QueryFilters\LikeMatch:code_number',
                'App\QueryFilters\DateBetween:start_date,end_date,date',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        $records = $this->filter($request)
            ->orderBy($this->getSort(), $this->getOrder())
            ->paginate((int) $this->getPageLength(), ['*'], 'current_page');

        $studentIds = $records->pluck('model_id')->unique()->toArray();

        $students = Student::query()
            ->summary()
            ->whereIn('students.id', $studentIds)
            ->get();

        $reasonIds = $records->pluck('meta.reason_id')->unique()->toArray();

        $reasons = Option::query()
            ->whereType(OptionType::STUDENT_TRANSFER_REASON)
            ->whereIn('id', $reasonIds)
            ->get();

        $request->merge([
            'students' => $students,
            'reasons' => $reasons,
        ]);

        return TransferApprovalRequestResource::collection($records)
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
