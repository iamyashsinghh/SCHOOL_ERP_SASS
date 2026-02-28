<?php

namespace App\Services\Communication;

use App\Enums\Employee\AudienceType as EmployeeAudienceType;
use App\Enums\OptionType;
use App\Enums\Student\AudienceType as StudentAudienceType;
use App\Http\Resources\OptionResource;
use App\Jobs\Notifications\Communication\SendBatchAnnouncementNotification;
use App\Models\Tenant\Communication\Announcement;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Option;
use App\Support\FormatCodeNumber;
use App\Support\HasAudience;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AnnouncementService
{
    use FormatCodeNumber, HasAudience;

    private function codeNumber(): array
    {
        $numberPrefix = config('config.communication.announcement_number_prefix');
        $numberSuffix = config('config.communication.announcement_number_suffix');
        $digit = config('config.communication.announcement_number_digit', 0);

        $numberFormat = $numberPrefix.'%NUMBER%'.$numberSuffix;

        $numberFormat = $this->preFormatForDate($numberFormat);

        $codeNumber = (int) Announcement::query()
            ->byTeam()
            ->whereNumberFormat($numberFormat)
            ->max('number') + 1;

        return $this->getCodeNumber(number: $codeNumber, digit: $digit, format: $numberFormat);
    }

    public function preRequisite(Request $request): array
    {
        $types = OptionResource::collection(Option::query()
            ->byTeam()
            ->whereType(OptionType::ANNOUNCEMENT_TYPE->value)
            ->get());

        $studentAudienceTypes = StudentAudienceType::getOptions();

        $employeeAudienceTypes = EmployeeAudienceType::getOptions();

        return compact('types', 'studentAudienceTypes', 'employeeAudienceTypes');
    }

    public function create(Request $request): Announcement
    {
        \DB::beginTransaction();

        $announcement = Announcement::forceCreate($this->formatParams($request));

        $this->storeAudience($announcement, $request->all());

        $announcement->addMedia($request);

        \DB::commit();

        SendBatchAnnouncementNotification::dispatch([
            'announcement_id' => $announcement->id,
            'sender_user_id' => auth()->id(),
            'team_id' => auth()->user()->current_team_id,
        ]);

        return $announcement;
    }

    private function formatParams(Request $request, ?Announcement $announcement = null): array
    {
        $formatted = [
            'title' => $request->title,
            'type_id' => $request->type_id,
            'is_public' => $request->boolean('is_public'),
            'audience' => [
                'student_type' => $request->student_audience_type,
                'employee_type' => $request->employee_audience_type,
            ],
            'description' => clean($request->description),
        ];

        if (! $announcement) {
            $codeNumberDetail = $this->codeNumber();

            $formatted['number_format'] = Arr::get($codeNumberDetail, 'number_format');
            $formatted['number'] = Arr::get($codeNumberDetail, 'number');
            $formatted['code_number'] = Arr::get($codeNumberDetail, 'code_number');
            $formatted['employee_id'] = Employee::auth()->first()?->id;
            $formatted['published_at'] = now()->toDateTimeString();
            $formatted['period_id'] = auth()->user()->current_period_id;
        }

        $meta = $announcement?->meta ?? [];

        $meta['excerpt'] = $request->boolean('is_public') ? $request->excerpt : null;
        $formatted['meta'] = $meta;

        return $formatted;
    }

    public function update(Request $request, Announcement $announcement): void
    {
        \DB::beginTransaction();

        $this->prepareAudienceForUpdate($announcement, $request->all());

        $announcement->forceFill($this->formatParams($request, $announcement))->save();

        $this->updateAudience($announcement, $request->all());

        $announcement->updateMedia($request);

        \DB::commit();
    }

    public function deletable(Announcement $announcement): void
    {
        //
    }
}
