<?php

namespace App\Services\Resource;

use App\Enums\Resource\AudienceType;
use App\Http\Resources\Academic\SubjectResource;
use App\Jobs\Notifications\Resource\SendBatchDiaryNotification;
use App\Models\Tenant\Academic\BatchSubjectRecord;
use App\Models\Tenant\Academic\Subject;
use App\Models\Tenant\Audience;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Resource\Diary;
use Illuminate\Http\Request;

class DiaryService
{
    public function preRequisite(Request $request): array
    {
        $subjects = SubjectResource::collection(Subject::query()
            ->byPeriod()
            ->get());

        $audienceTypes = AudienceType::getOptions();

        return compact('subjects', 'audienceTypes');
    }

    public function create(Request $request): Diary
    {
        \DB::beginTransaction();

        $diary = Diary::forceCreate($this->formatParams($request));

        if ($request->audience_type == 'batch_wise') {
            $this->updateBatchSubjectRecords($request, $diary);
        } else {
            $this->updateStudentRecords($request, $diary);
        }

        $diary->addMedia($request);

        \DB::commit();

        SendBatchDiaryNotification::dispatch([
            'diary_id' => $diary->id,
            'sender_user_id' => auth()->id(),
            'team_id' => auth()->user()->current_team_id,
        ]);

        return $diary;
    }

    private function formatParams(Request $request, ?Diary $diary = null): array
    {
        $formatted = [
            'date' => $request->date,
            'details' => collect($request->details)->map(function ($detail) {
                return [
                    'heading' => $detail['heading'],
                    'description' => $detail['description'],
                ];
            })->toArray(),
        ];

        $config = $diary?->config ?? [];
        $config['audience_type'] = $request->audience_type;
        $formatted['config'] = $config;

        if (! $diary) {
            $formatted['period_id'] = auth()->user()->current_period_id;
            $formatted['employee_id'] = Employee::auth()->first()?->id;
        }

        return $formatted;
    }

    private function updateBatchSubjectRecords(Request $request, Diary $diary)
    {
        if ($request->audience_type == 'student_wise') {
            BatchSubjectRecord::query()
                ->whereModelType($diary->getMorphClass())
                ->whereModelId($diary->id)
                ->delete();

            return;
        }

        $usedIds = [];
        foreach ($request->batch_ids as $batchId) {
            $usedIds[] = [
                'batch_id' => $batchId,
                'subject_id' => $request->subject_id,
            ];

            BatchSubjectRecord::firstOrCreate([
                'model_type' => $diary->getMorphClass(),
                'model_id' => $diary->id,
                'batch_id' => $batchId,
                'subject_id' => $request->subject_id,
            ]);
        }

        $records = BatchSubjectRecord::query()
            ->whereModelType($diary->getMorphClass())
            ->whereModelId($diary->id)
            ->get();

        $usedIds = collect($usedIds);
        foreach ($records as $record) {
            if (! $usedIds->where('batch_id', $record->batch_id)->where('subject_id', $record->subject_id)->count()) {
                $record->delete();
            }
        }
    }

    private function updateStudentRecords(Request $request, Diary $diary)
    {
        if ($request->audience_type == 'batch_wise') {
            Audience::query()
                ->whereShareableType($diary->getMorphClass())
                ->whereShareableId($diary->id)
                ->whereAudienceableType('Student')
                ->delete();

            return;
        }

        $usedIds = [];
        foreach ($request->student_ids as $studentId) {
            $audience = Audience::query()
                ->firstOrCreate([
                    'shareable_type' => $diary->getMorphClass(),
                    'shareable_id' => $diary->id,
                    'audienceable_type' => 'Student',
                    'audienceable_id' => $studentId,
                ]);

            $usedIds[] = $studentId;
        }

        Audience::query()
            ->whereShareableType($diary->getMorphClass())
            ->whereShareableId($diary->id)
            ->whereAudienceableType('Student')
            ->whereNotIn('audienceable_id', $usedIds)
            ->delete();
    }

    public function update(Request $request, Diary $diary): void
    {
        \DB::beginTransaction();

        $diary->forceFill($this->formatParams($request, $diary))->save();

        $this->updateBatchSubjectRecords($request, $diary);

        $this->updateStudentRecords($request, $diary);

        $diary->updateMedia($request);

        \DB::commit();
    }

    public function deletable(Diary $diary): void
    {
        //
    }
}
