<?php

namespace App\Services\Communication;

use App\Enums\Employee\AudienceType as EmployeeAudienceType;
use App\Enums\Student\AudienceType as StudentAudienceType;
use App\Http\Resources\Config\WhatsAppTemplateResource;
use App\Jobs\Notifications\Communication\SendBatchWhatsApp;
use App\Models\Tenant\Communication\Communication;
use App\Models\Tenant\Config\Template;
use App\Support\HasAudience;
use App\Support\MergeGuardianContact;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WhatsAppService
{
    use HasAudience, MergeGuardianContact;

    public function preRequisite(Request $request): array
    {
        $studentAudienceTypes = StudentAudienceType::getOptions();

        $employeeAudienceTypes = EmployeeAudienceType::getOptions();

        $templates = WhatsAppTemplateResource::collection(Template::query()
            ->where('type', 'whatsapp')
            ->get());

        $preDefinedVariables = [
            '##NAME##', '##COURSE_NAME##', '##BATCH_NAME##', '##COURSE_BATCH_NAME##', '##DEPARTMENT_NAME##', '##DESIGNATION_NAME##', '##DEPARTMENT_DESIGNATION_NAME##', '##INSTITUTE_NAME##', '##APP_NAME##',
        ];

        return compact('studentAudienceTypes', 'employeeAudienceTypes', 'templates', 'preDefinedVariables');
    }

    public function create(Request $request): Communication
    {
        \DB::beginTransaction();

        $communication = Communication::forceCreate($this->formatParams($request));

        $this->storeAudience($communication, $request->all());

        \DB::commit();

        $this->sendWhatsApp($communication);

        return $communication;
    }

    private function sendWhatsApp(Communication $communication): void
    {
        SendBatchWhatsApp::dispatch([
            'communication_id' => $communication->id,
            'team_id' => auth()->user()->current_team_id,
        ]);
    }

    private function getReceipients(Request $request): array
    {
        $contacts = $this->getContacts($request->all());

        $contacts = $this->mergeGuardianContact($contacts->pluck('id')->toArray());

        $contactNumbers = $contacts->pluck('contact_number')->toArray();

        $inclusion = $request->inclusion ?? [];
        $exclusion = $request->exclusion ?? [];

        foreach ($inclusion as $include) {
            $contactNumbers[] = $include;
        }

        $contactNumbers = array_diff($contactNumbers, $exclusion);

        return $contactNumbers;
    }

    private function formatParams(Request $request, ?Communication $communication = null): array
    {
        $receipients = $this->getReceipients($request);

        if (! count($receipients)) {
            throw ValidationException::withMessages(['recipients' => trans('communication.whatsapp.no_recipient_found')]);
        }

        $formatted = [
            'subject' => $request->subject,
            'lists' => [
                'inclusion' => $request->inclusion,
                'exclusion' => $request->exclusion,
            ],
            'audience' => [
                'student_type' => $request->student_audience_type,
                'employee_type' => $request->employee_audience_type,
            ],
            'recipients' => $receipients,
            'content' => $request->content,
            'template_id' => $request->template_id,
        ];

        if (! $communication) {
            $formatted['type'] = 'whatsapp';
            $formatted['user_id'] = auth()->id();
            $formatted['period_id'] = auth()->user()->current_period_id;
        }

        $meta = $communication?->meta ?? [];

        $meta['recipient_count'] = count($receipients);
        $meta['variables'] = collect($request->variables)->map(fn ($item) => [
            'name' => Str::of(Arr::get($item, 'name'))->lower()->snake()->value,
            'value' => Arr::get($item, 'value'),
        ])->values()->toArray();
        $meta['template_code'] = $request->template_code;

        $formatted['meta'] = $meta;

        return $formatted;
    }

    public function deletable(Communication $announcement): void
    {
        throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
    }
}
