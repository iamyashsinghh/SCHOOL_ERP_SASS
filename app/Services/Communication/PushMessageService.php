<?php

namespace App\Services\Communication;

use App\Enums\Employee\AudienceType as EmployeeAudienceType;
use App\Enums\Student\AudienceType as StudentAudienceType;
use App\Jobs\Notifications\Communication\SendBatchPushNotification;
use App\Jobs\Notifications\Communication\SendTestPushNotification;
use App\Models\Communication\Communication;
use App\Support\HasAudience;
use App\Support\MergeGuardianContact;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PushMessageService
{
    use HasAudience, MergeGuardianContact;

    public function preRequisite(Request $request): array
    {
        $studentAudienceTypes = StudentAudienceType::getOptions();

        $employeeAudienceTypes = EmployeeAudienceType::getOptions();

        return compact('studentAudienceTypes', 'employeeAudienceTypes');
    }

    public function create(Request $request): Communication
    {
        \DB::beginTransaction();

        $communication = Communication::forceCreate($this->formatParams($request));

        $this->storeAudience($communication, $request->all());

        \DB::commit();

        SendBatchPushNotification::dispatch([
            'communication_id' => $communication->id,
            'team_id' => auth()->user()->current_team_id,
        ]);

        return $communication;
    }

    public function sendTestNotification(Request $request): void
    {
        $request->validate([
            'subject' => 'required|string|min:3',
            'content' => 'required|string|min:3',
        ]);

        SendTestPushNotification::dispatchSync([
            'user_id' => auth()->id(),
            'team_id' => auth()->user()->current_team_id,
            'subject' => $request->subject,
            'content' => $request->content,
        ]);
    }

    private function getReceipients(Request $request): array
    {
        $contacts = $this->getContacts($request->all());

        $contacts = $this->mergeGuardianContact($contacts->pluck('id')->toArray());

        return $contacts->pluck('id')->all();
    }

    private function formatParams(Request $request, ?Communication $communication = null): array
    {
        $receipients = $this->getReceipients($request);

        if (! count($receipients)) {
            throw ValidationException::withMessages(['recipients' => trans('communication.push_message.no_recipient_found')]);
        }

        $formatted = [
            'subject' => $request->subject,
            'lists' => [],
            'audience' => [
                'student_type' => $request->student_audience_type,
                'employee_type' => $request->employee_audience_type,
            ],
            'recipients' => [], // don't save recipients in database
            'content' => $request->content,
        ];

        if (! $communication) {
            $formatted['type'] = 'push_message';
            $formatted['user_id'] = auth()->id();
            $formatted['period_id'] = auth()->user()->current_period_id;
        }

        $meta = $communication?->meta ?? [];

        $meta['recipient_count'] = count($receipients);

        $formatted['meta'] = $meta;

        return $formatted;
    }

    public function deletable(Communication $announcement): void
    {
        throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
    }
}
