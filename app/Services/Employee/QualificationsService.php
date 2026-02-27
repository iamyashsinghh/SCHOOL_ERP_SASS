<?php

namespace App\Services\Employee;

use App\Concerns\SubordinateAccess;
use App\Enums\OptionType;
use App\Enums\QualificationResult;
use App\Enums\VerificationStatus;
use App\Http\Resources\OptionResource;
use App\Models\Employee\Employee;
use App\Models\Option;
use App\Models\Qualification;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QualificationsService
{
    use SubordinateAccess;

    public function preRequisite(Request $request): array
    {
        $levels = OptionResource::collection(Option::query()
            ->byTeam()
            ->whereType(OptionType::QUALIFICATION_LEVEL->value)
            ->get());

        $results = QualificationResult::getOptions();

        return compact('levels', 'results');
    }

    public function findByUuidOrFail(string $uuid): Qualification
    {
        $qualification = Qualification::query()
            ->whereUuid($uuid)
            ->getOrFail(trans('employee.qualification.qualification'));

        return $qualification;
    }

    public function findEmployee(Qualification $qualification): Employee
    {
        return Employee::query()
            ->summary()
            ->filterAccessible()
            ->where('contact_id', $qualification->model_id)
            ->getOrFail(trans('employee.employee'));
    }

    public function create(Request $request): Qualification
    {
        \DB::beginTransaction();

        $qualification = Qualification::forceCreate($this->formatParams($request));

        $qualification->addMedia($request);

        \DB::commit();

        return $qualification;
    }

    private function formatParams(Request $request, ?Qualification $qualification = null): array
    {
        $formatted = [
            'model_type' => 'Contact',
            'model_id' => $request->contact_id,
            'level_id' => $request->level_id,
            'course' => $request->course,
            'institute' => $request->institute,
            'affiliated_to' => $request->affiliated_to,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'result' => $request->result,
        ];

        $meta = $qualification?->meta ?? [];

        $meta['is_submitted_original'] = $request->boolean('is_submitted_original');

        $meta['session'] = $request->input('session');
        $meta['institute_address'] = $request->institute_address;
        $meta['total_marks'] = $request->total_marks;
        $meta['obtained_marks'] = $request->obtained_marks;
        $meta['percentage'] = $request->total_marks ? round($request->obtained_marks / $request->total_marks * 100, 2) : null;
        $meta['failed_subjects'] = $request->failed_subjects;

        if ($request->user_id == auth()->id()) {
            $meta['self_upload'] = true;
            $formatted['verified_at'] = null;
        }

        $formatted['meta'] = $meta;

        return $formatted;
    }

    private function isEditable(Request $request, Qualification $qualification): void
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

    public function update(Request $request, Qualification $qualification): void
    {
        $this->isEditable($request, $qualification);

        \DB::beginTransaction();

        $qualification->forceFill($this->formatParams($request, $qualification))->save();

        $qualification->updateMedia($request);

        \DB::commit();
    }

    public function deletable(Request $request, Qualification $qualification): void
    {
        $this->isEditable($request, $qualification);
    }
}
