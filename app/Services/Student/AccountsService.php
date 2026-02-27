<?php

namespace App\Services\Student;

use App\Enums\VerificationStatus;
use App\Models\Account;
use App\Models\Student\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountsService
{
    public function preRequisite(Request $request): array
    {
        return [];
    }

    public function findByUuidOrFail(string $uuid): Account
    {
        $account = Account::query()
            ->whereUuid($uuid)
            ->getOrFail(trans('student.account.account'));

        return $account;
    }

    public function findStudent(Account $account): Student
    {
        return Student::query()
            ->summary()
            ->byPeriod()
            ->filterAccessible()
            ->where('contact_id', $account->accountable_id)
            ->getOrFail(trans('student.student'));
    }

    public function create(Request $request): Account
    {
        \DB::beginTransaction();

        $account = Account::forceCreate($this->formatParams($request));

        if (
            Account::query()
                ->where('accountable_id', $request->contact_id)
                ->where('accountable_type', 'Contact')
                ->count() == 1
        ) {
            $account->is_primary = true;
            $account->save();
        }

        $account->addMedia($request);

        \DB::commit();

        return $account;
    }

    private function formatParams(Request $request, ?Account $account = null): array
    {
        $formatted = [
            'accountable_type' => 'Contact',
            'accountable_id' => $request->contact_id,
            'name' => $request->name,
            'alias' => $request->alias,
            'number' => $request->number,
            'bank_details' => [
                'bank_name' => $request->bank_name,
                'branch_name' => $request->branch_name,
                'bank_code1' => $request->bank_code1,
                'bank_code2' => $request->bank_code2,
                'bank_code3' => $request->bank_code3,
            ],
        ];

        $meta = $account?->meta ?? [];

        if ($request->user_id == auth()->id()) {
            $meta['self_upload'] = true;
            $formatted['verified_at'] = null;
        }

        $formatted['meta'] = $meta;

        return $formatted;
    }

    private function isEditable(Request $request, Account $account): void
    {
        if (! $account->getMeta('self_upload')) {
            if ($request->user_id == auth()->id()) {
                throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
            }

            return;
        }

        if ($request->user_id != auth()->id()) {
            throw ValidationException::withMessages(['message' => trans('student.could_not_edit_self_service_upload')]);
        }

        if ($account->getMeta('status') == VerificationStatus::REJECTED->value) {
            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }

        if (empty($account->verified_at->value)) {
            return;
        }

        throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
    }

    public function update(Request $request, Account $account): void
    {
        $this->isEditable($request, $account);

        \DB::beginTransaction();

        $account->forceFill($this->formatParams($request, $account))->save();

        $account->updateMedia($request);

        \DB::commit();
    }

    public function deletable(Request $request, Account $account): void
    {
        $this->isEditable($request, $account);
    }
}
