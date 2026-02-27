<?php

namespace App\Services\Employee;

use App\Actions\CreateContact;
use App\Actions\UpdateContact;
use App\Concerns\Auth\EnsureUniqueUserEmail;
use App\Enums\BloodGroup;
use App\Enums\CustomFieldForm;
use App\Enums\Employee\Status;
use App\Enums\Employee\Type;
use App\Enums\FamilyRelation;
use App\Enums\Gender;
use App\Enums\Locality;
use App\Enums\MaritalStatus;
use App\Enums\OptionType;
use App\Enums\UserStatus;
use App\Http\Resources\CustomFieldResource;
use App\Http\Resources\Employee\DepartmentResource;
use App\Http\Resources\Employee\DesignationResource;
use App\Http\Resources\OptionResource;
use App\Jobs\Notifications\Employee\SendEmployeeAccountCreatedNotification;
use App\Models\Contact;
use App\Models\CustomField;
use App\Models\Employee\Department;
use App\Models\Employee\Designation;
use App\Models\Employee\Employee;
use App\Models\Employee\Record;
use App\Models\Incharge;
use App\Models\Option;
use App\Models\Team\Role;
use App\Models\User;
use App\Support\FormatCodeNumber;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as SpatieRole;

class EmployeeService
{
    use EnsureUniqueUserEmail, FormatCodeNumber;

    private function codeNumber()
    {
        $numberPrefix = config('config.employee.code_number_prefix');
        $numberSuffix = config('config.employee.code_number_suffix');
        $digit = config('config.employee.code_number_digit', 0);

        $numberFormat = $numberPrefix.'%NUMBER%'.$numberSuffix;

        $numberFormat = $this->preFormatForDate($numberFormat);

        $codeNumber = (int) Employee::query()
            ->codeNumberByTeam()
            ->whereNumberFormat($numberFormat)
            ->max('number') + 1;

        return $this->getCodeNumber(number: $codeNumber, digit: $digit, format: $numberFormat);
    }

    private function validateCodeNumber(Request $request, ?string $uuid = null): array
    {
        $existingCodeNumber = Employee::byTeam()->whereCodeNumber($request->code_number)->when($uuid, function ($q, $uuid) {
            $q->where('uuid', '!=', $uuid);
        })->exists();

        if ($existingCodeNumber) {
            throw ValidationException::withMessages(['code_number' => trans('global.duplicate', ['attribute' => trans('employee.props.code_number')])]);
        }

        $codeNumberDetail = $this->codeNumber();

        return $request->code_number == Arr::get($codeNumberDetail, 'code_number') ? $codeNumberDetail : [
            'code_number' => $request->code_number,
        ];
    }

    public function preRequisite(Request $request): array
    {
        $codeNumber = Arr::get($this->codeNumber(), 'code_number');

        $genders = Gender::getOptions();

        $statuses = Status::getOptions();

        $maritalStatuses = MaritalStatus::getOptions();

        $bloodGroups = BloodGroup::getOptions();

        $localities = Locality::getOptions();

        $categories = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::MEMBER_CATEGORY->value)
            ->get());

        $castes = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::MEMBER_CASTE->value)
            ->get());

        $religions = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::RELIGION->value)
            ->get());

        $groups = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::EMPLOYEE_GROUP->value)
            ->get());

        $customFields = CustomFieldResource::collection(CustomField::query()
            ->byTeam()
            ->whereForm(CustomFieldForm::EMPLOYEE)
            ->orderBy('position')
            ->get());

        $documentTypes = OptionResource::collection(Option::query()
            ->byTeam()
            ->whereIn('type', [OptionType::DOCUMENT_TYPE, OptionType::EMPLOYEE_DOCUMENT_TYPE])
            ->where('meta->has_number', true)
            ->get());

        $types = Type::getOptions();

        $relations = FamilyRelation::getOptions();

        $employeeTypes = [
            ['label' => trans('employee.addition_types.new'), 'value' => 'new'],
            ['label' => trans('employee.addition_types.existing'), 'value' => 'existing'],
        ];

        if (count(config('config.teams', [])) > 1) {
            $employeeTypes[] = ['label' => trans('employee.addition_types.other_team_member'), 'value' => 'other_team_member'];
        }

        $roles = Role::selectOption();

        $departments = DepartmentResource::collection(Department::query()
            ->globalOrByTeam()
            ->get());

        $designations = DesignationResource::collection(Designation::query()
            ->byTeam()
            ->get());

        $employmentStatuses = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::EMPLOYMENT_STATUS->value)
            ->get());

        return compact('codeNumber', 'genders', 'statuses', 'maritalStatuses', 'types', 'employeeTypes', 'bloodGroups', 'localities', 'categories', 'castes', 'religions', 'customFields', 'groups', 'roles', 'departments', 'designations', 'employmentStatuses', 'documentTypes', 'relations');
    }

    public function create(Request $request): Employee
    {
        \DB::beginTransaction();

        if ($request->employee_type == 'new') {
            $params = $request->all();
            $params['source'] = 'employee';

            $contact = (new CreateContact)->execute($params);

            $request->merge([
                'contact_id' => $contact->id,
            ]);
        }

        $employee = Employee::forceCreate($this->formatParams($request));

        if ($request->role_ids) {
            $user = $employee->contact?->user;

            if ($user) {
                $user->assignRole(SpatieRole::find($request->role_ids));
            }
        }

        if ($request->employee_type == 'other_team_member' && $request->addition_scope == 'transfer') {
            $previousEmployee = Employee::query()
                ->where('id', $request->previous_employee_id)
                ->first();
            $previousEmployeeRecord = Record::query()
                ->where('employee_id', $previousEmployee?->id)
                ->whereNull('end_date')
                ->orderBy('start_date', 'desc')
                ->firstOrFail();

            if ($previousEmployeeRecord->start_date->value > $request->joining_date) {
                throw ValidationException::withMessages(['joining_date' => trans('employee.joining_date_less_than_leaving_date', ['attribute' => $previousEmployeeRecord->start_date->formatted])]);
            }

            $endDate = Carbon::parse($request->joining_date)->subDay()->toDateString();

            $previousEmployeeRecord->end_date = $endDate;
            $previousEmployeeRecord->is_ended = true;
            $previousEmployeeRecord->remarks = trans('employee.transferred_to', ['attribute' => config('config.team.name')]);
            $previousEmployeeRecord->save();

            $previousEmployee->leaving_date = $endDate;
            $previousEmployee->save();
        }

        $employeeRecord = Record::forceCreate([
            'employee_id' => $employee->id,
            'department_id' => $request->department_id,
            'designation_id' => $request->designation_id,
            'employment_status_id' => $request->employment_status_id,
            'start_date' => $request->joining_date,
            'meta' => [
                'media_token' => (string) Str::uuid(),
            ],
        ]);

        if ($request->employee_type == 'new' && $request->boolean('create_user_account')) {
            $this->ensureEmailDoesntBelongToOtherContact($contact, $request->email);

            $this->ensureEmailDoesntBelongToUserContact($request->email);

            if (! $contact->email) {
                $contact->email = $request->email;
                $contact->save();
            }

            $existingUser = User::query()
                ->where('email', $contact->email)
                ->orWhere('username', $request->username)
                ->first();

            if (! $existingUser) {
                $user = User::forceCreate([
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'username' => $request->username,
                    'password' => bcrypt($request->password),
                    'status' => UserStatus::ACTIVATED,
                ]);

                $user->assignRole(SpatieRole::find($request->role_ids));
            } else {
                $user = $existingUser;
            }

            $contact->user_id = $user?->id;
            $contact->save();
        }

        \DB::commit();

        if ($request->employee_type == 'new' && $request->boolean('create_user_account')) {
            SendEmployeeAccountCreatedNotification::dispatch([
                'employee_id' => $employee->id,
                'name' => $contact->name,
                'employee_code' => $employee->code_number,
                'username' => $request->username,
                'password' => $request->password,
                'designation' => $request->designation_name,
                'department' => $request->department_name,
                'team_id' => auth()->user()->current_team_id,
            ]);
        }

        return $employee;
    }

    private function formatParams(Request $request, ?Employee $employee = null): array
    {
        if ($request->employee_type == 'other_team_member' && $request->addition_scope == 'guest') {
            $codeNumberDetail = [
                'number_format' => $request->number_format,
                'number' => $request->number,
                'code_number' => $request->code_number,
            ];
        } else {
            if (config('config.employee.enable_manual_code_number')) {
                $codeNumberDetail = $this->validateCodeNumber($request);
            } else {
                $codeNumberDetail = $this->codeNumber();
            }
        }

        $formatted = [
            'type' => $request->type,
            'contact_id' => $request->contact_id,
            'joining_date' => $request->joining_date,
            'team_id' => auth()->user()?->current_team_id,
            'number_format' => Arr::get($codeNumberDetail, 'number_format'),
            'number' => Arr::get($codeNumberDetail, 'number'),
            'code_number' => $request->code_number,
        ];

        $meta = $employee?->meta ?? [];
        if ($request->employee_type == 'other_team_member' && $request->addition_scope == 'guest') {
            $meta['other_team_member'] = $request->employee_type == 'other_team_member' ? true : false;
        }

        $formatted['meta'] = $meta;

        return $formatted;
    }

    public function update(Request $request, Employee $employee): void
    {
        $employee->type = $request->type;
        $employee->save();

        $contact = $employee->contact;

        $existingContact = Contact::byTeam()->where('uuid', '!=', $contact->uuid)
            ->whereFirstName($request->input('first_name', $contact->first_name))
            ->whereMiddleName($request->input('middle_name', $contact->middle_name))
            ->whereThirdName($request->input('third_name', $contact->third_name))
            ->whereLastName($request->input('last_name', $contact->last_name))
            ->whereContactNumber($request->input('contact_number', $contact->contact_number))
            ->count();

        if ($existingContact) {
            throw ValidationException::withMessages(['message' => trans('employee.exists')]);
        }

        \DB::beginTransaction();

        (new UpdateContact)->execute($employee->contact, $request->all());

        \DB::commit();
    }

    public function deletable(Request $request, Employee $employee): void
    {
        if ($employee->contact?->user?->hasRole('admin') && ! auth()->user()->is_default) {
            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }

        $incharges = Incharge::query()
            ->whereEmployeeId($employee->id)
            ->exists();

        if ($incharges) {
            throw ValidationException::withMessages(['message' => trans('global.associated_with_dependency', ['attribute' => trans('employee.employee'), 'dependency' => trans('employee.incharge.incharge')])]);
        }

        if ($request->boolean('force')) {
            return;
        }

        throw ValidationException::withMessages(['message' => trans('general.errors.feature_under_development')]);
    }

    public function delete(Employee $employee): void
    {
        \DB::beginTransaction();

        $user = $employee->contact?->user;

        if ($user) {
            $user->removeRole($user->roles);
        }

        $employee->delete();

        \DB::commit();
    }
}
