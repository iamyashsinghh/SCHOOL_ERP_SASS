<?php

namespace App\Services\Employee;

use App\Concerns\SubordinateAccess;
use App\Enums\OptionType;
use App\Enums\VerificationStatus;
use App\Http\Resources\OptionResource;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Experience;
use App\Models\Tenant\Option;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExperiencesService
{
    use SubordinateAccess;

    public function preRequisite(Request $request): array
    {
        $employmentTypes = OptionResource::collection(Option::query()
            ->byTeam()
            ->whereType(OptionType::EMPLOYMENT_TYPE->value)
            ->get());

        return compact('employmentTypes');
    }

    public function findByUuidOrFail(string $uuid): Experience
    {
        $qualification = Experience::query()
            ->whereUuid($uuid)
            ->getOrFail(trans('employee.qualification.qualification'));

        return $qualification;
    }

    public function findEmployee(Experience $qualification): Employee
    {
        return Employee::query()
            ->summary()
            ->filterAccessible()
            ->where('contact_id', $qualification->model_id)
            ->getOrFail(trans('employee.employee'));
    }

    public function create(Request $request): Experience
    {
        \DB::beginTransaction();

        $qualification = Experience::forceCreate($this->formatParams($request));

        $qualification->addMedia($request);

        \DB::commit();

        return $qualification;
    }

    private function formatParams(Request $request, ?Experience $qualification = null): array
    {
        $formatted = [
            'model_type' => 'Contact',
            'model_id' => $request->contact_id,
            'employment_type_id' => $request->employment_type_id,
            'headline' => $request->headline,
            'title' => $request->title,
            'organization_name' => $request->organization_name,
            'location' => $request->location,
            'job_profile' => $request->job_profile,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date ?: null,
        ];

        $meta = $qualification?->meta ?? [];

        $meta['is_submitted_original'] = $request->boolean('is_submitted_original');

        if ($request->user_id == auth()->id()) {
            $meta['self_upload'] = true;
            $formatted['verified_at'] = null;
        }

        $formatted['meta'] = $meta;

        return $formatted;
    }

    private function isEditable(Request $request, Experience $qualification): void
    {
        if (! $qualification->getMeta('self_upload')) {
            if ($request->user_id == auth()->id()) {
                throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
            }

            return;
        }

        if ($request->user_id != auth()->id()) {
            throw ValidationException::withMessages(['message' => trans('employee.could_not_edit_self_service_upload')]);
        }

        if ($qualification->getMeta('status') == VerificationStatus::REJECTED->value) {
            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }

        if (empty($qualification->verified_at->value)) {
            return;
        }

        throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
    }

    public function update(Request $request, Experience $qualification): void
    {
        $this->isEditable($request, $qualification);

        \DB::beginTransaction();

        $qualification->forceFill($this->formatParams($request, $qualification))->save();

        $qualification->updateMedia($request);

        \DB::commit();
    }

    public function deletable(Request $request, Experience $qualification): void
    {
        $this->isEditable($request, $qualification);
    }
}
