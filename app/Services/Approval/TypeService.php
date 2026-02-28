<?php

namespace App\Services\Approval;

use App\Enums\Approval\Category;
use App\Enums\Approval\Event;
use App\Enums\OptionType;
use App\Http\Resources\Employee\DepartmentResource;
use App\Http\Resources\OptionResource;
use App\Models\Tenant\Approval\Level as ApprovalLevel;
use App\Models\Tenant\Approval\Request as ApprovalRequest;
use App\Models\Tenant\Approval\Type as ApprovalType;
use App\Models\Tenant\Employee\Department;
use App\Models\Tenant\Option;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class TypeService
{
    public function preRequisite(Request $request): array
    {
        $categories = Category::getOptions();

        $events = Event::getOptions();

        $departments = DepartmentResource::collection(Department::query()
            ->globalOrByTeam()
            ->get());

        $priorities = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::APPROVAL_REQUEST_PRIORITY)
            ->get());

        $actions = [
            ['label' => trans('approval.actions.edit'), 'value' => 'edit'],
            ['label' => trans('approval.actions.reject'), 'value' => 'reject'],
            ['label' => trans('approval.actions.hold'), 'value' => 'hold'],
            ['label' => trans('approval.actions.cancel'), 'value' => 'cancel'],
            ['label' => trans('approval.actions.return'), 'value' => 'return'],
        ];

        $itemBasedTypes = [
            ['label' => trans('approval.item_based_types.item_with_quantity'), 'value' => 'item_with_quantity'],
            ['label' => trans('approval.item_based_types.item_without_quantity'), 'value' => 'item_without_quantity'],
        ];

        return compact('categories', 'events', 'departments', 'priorities', 'actions', 'itemBasedTypes');
    }

    public function create(Request $request): ApprovalType
    {
        if (in_array($request->event, [Event::STUDENT_TRANSFER->value, Event::EMPLOYEE_LEAVE->value])) {
            throw ValidationException::withMessages(['message' => trans('general.errors.feature_under_development')]);
        }

        \DB::beginTransaction();

        $approvalType = ApprovalType::forceCreate($this->formatParams($request));

        $this->updateLevels($request, $approvalType);

        \DB::commit();

        return $approvalType;
    }

    private function formatParams(Request $request, ?ApprovalType $approvalType = null): array
    {
        $formatted = [
            'name' => $request->name,
            'category' => $request->category,
            'event' => $request->event,
            'department_id' => $request->department_id,
            'priority_id' => $request->priority_id,
            'description' => $request->description,
        ];

        if (! $approvalType) {
            $formatted['team_id'] = auth()->user()?->current_team_id;
        }

        $config = $approvalType?->config ?? [];

        $config['is_active'] = $request->boolean('is_active');
        $config['enable_file_upload'] = $request->boolean('enable_file_upload');
        $config['is_file_upload_required'] = $request->boolean('is_file_upload_required');

        if ($request->category == Category::ITEM_BASED->value) {
            $config['item_based_type'] = $request->item_based_type;
        } elseif ($request->category == Category::CONTACT_BASED->value) {
            $config['enable_contact_number'] = $request->boolean('enable_contact_number');
            $config['is_contact_number_required'] = $request->boolean('is_contact_number_required');
            $config['enable_email'] = $request->boolean('enable_email');
            $config['is_email_required'] = $request->boolean('is_email_required');
            $config['enable_website'] = $request->boolean('enable_website');
            $config['is_website_required'] = $request->boolean('is_website_required');
            $config['enable_tax_number'] = $request->boolean('enable_tax_number');
            $config['is_tax_number_required'] = $request->boolean('is_tax_number_required');
            $config['enable_address'] = $request->boolean('enable_address');
            $config['is_address_required'] = $request->boolean('is_address_required');
        } elseif ($request->category == Category::PAYMENT_BASED->value) {
            $config['enable_invoice_number'] = $request->boolean('enable_invoice_number');
            $config['is_invoice_number_required'] = $request->boolean('is_invoice_number_required');
            $config['enable_invoice_date'] = $request->boolean('enable_invoice_date');
            $config['is_invoice_date_required'] = $request->boolean('is_invoice_date_required');
            $config['enable_payment_mode'] = $request->boolean('enable_payment_mode');
            $config['is_payment_mode_required'] = $request->boolean('is_payment_mode_required');
            $config['enable_payment_details'] = $request->boolean('enable_payment_details');
            $config['is_payment_details_required'] = $request->boolean('is_payment_details_required');
        } elseif ($request->category == Category::EVENT_BASED->value) {
            $config['code'] = $request->code;
        }

        $config['due_in'] = $request->due_in ? $request->due_in : null;

        $formatted['config'] = $config;

        return $formatted;
    }

    private function updateLevels(Request $request, ApprovalType $approvalType): void
    {
        $employeeIds = [];
        foreach ($request->levels as $index => $level) {
            $employeeIds[] = Arr::get($level, 'employee_id');

            $approvalLevel = ApprovalLevel::firstOrCreate([
                'type_id' => $approvalType->id,
                'employee_id' => Arr::get($level, 'employee_id'),
            ]);

            $approvalLevel->setConfig([
                'is_other_team_member' => (bool) Arr::get($level, 'other_team_member'),
                'actions' => Arr::get($level, 'actions', []),
            ]);
            $approvalLevel->position = $index + 1;
            $approvalLevel->save();
        }

        ApprovalLevel::query()
            ->whereTypeId($approvalType->id)
            ->whereNotIn('employee_id', $employeeIds)
            ->delete();
    }

    public function update(Request $request, ApprovalType $approvalType): void
    {
        \DB::beginTransaction();

        $approvalType->forceFill($this->formatParams($request, $approvalType))->save();

        $this->updateLevels($request, $approvalType);

        \DB::commit();
    }

    public function deletable(Request $request, ApprovalType $approvalType): void
    {
        $approvalRequestExists = ApprovalRequest::query()
            ->whereTypeId($approvalType->id)
            ->exists();

        if ($approvalRequestExists) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('approval.type.type'), 'dependency' => trans('approval.request.request')])]);
        }
    }
}
