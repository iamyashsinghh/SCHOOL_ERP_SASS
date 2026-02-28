<?php

namespace App\Services\Resource;

use App\Actions\UpdateViewLog;
use App\Helpers\CalHelper;
use App\Http\Resources\Employee\EmployeeBasicResource;
use App\Http\Resources\MediaResource;
use App\Models\Tenant\Academic\Batch;
use App\Models\Tenant\Resource\Diary;
use App\Models\Tenant\Student\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DiaryPreviewService
{
    public function preview(Request $request): array
    {
        $date = $request->date;

        if (! CalHelper::validateDate($request->date)) {
            $date = today()->toDateString();
        }

        $dates = CalHelper::getRecentDates($date, 7);

        $studentIds = [];
        if (auth()->user()->hasAnyRole(['student', 'guardian'])) {
            $studentIds = Student::query()
                ->byPeriod()
                ->record()
                ->filterForStudentAndGuardian()
                ->get()
                ->pluck('id')
                ->all();
        }

        $batch = Batch::query()
            ->byPeriod()
            ->filterAccessible()
            ->where('uuid', $request->batch)
            ->getOrFail(trans('academic.batch.batch'), 'message');

        $diaries = Diary::query()
            ->whereHas('records', function ($q) use ($batch) {
                $q->where('batch_subject_records.batch_id', $batch->id);
            })
            ->with([
                'records' => function ($q) use ($batch) {
                    $q->where('batch_id', $batch->id);
                },
                'records.subject',
                'employee' => fn ($q) => $q->summary(),
                'media',
            ])
            ->where('date', $date)
            ->get();

        if (auth()->user()->hasAnyRole(['student', 'guardian'])) {
            $studentWiseDiaries = Diary::query()
                ->with([
                    'employee' => fn ($q) => $q->summary(),
                    'media',
                ])
                ->where('date', $date)
                ->whereHas('audiences', function ($q) use ($studentIds) {
                    $q->where('audienceable_type', '=', 'Student')
                        ->whereIn('audienceable_id', $studentIds);
                })
                ->get();
        }

        $diary = [
            'date' => \Cal::date($date),
            'batch' => $batch->course->name.' '.$batch->name,
        ];

        foreach ($diaries as $item) {

            (new UpdateViewLog)->handle($item);

            $record = $item->records()->first();
            $subject = $record?->subject?->name;

            $records['uuid'] = $item->uuid;
            $records['subject'] = $subject;
            $records['details'] = Arr::map($item->details, function ($detail) {
                return [
                    'uuid' => (string) Str::uuid(),
                    'heading' => Arr::get($detail, 'heading'),
                    'description' => Arr::get($detail, 'description'),
                ];
            });

            $records['employee'] = $item->employee ? EmployeeBasicResource::make($item?->employee) : null;
            $records['media'] = MediaResource::collection($item->media);

            $diary['records'][] = $records;
        }

        $records = [];
        foreach ($studentWiseDiaries as $item) {
            $records['uuid'] = $item->uuid;
            $records['subject'] = '-';
            $records['details'] = Arr::map($item->details, function ($detail) {
                return [
                    'uuid' => (string) Str::uuid(),
                    'heading' => Arr::get($detail, 'heading'),
                    'description' => Arr::get($detail, 'description'),
                ];
            });

            $records['employee'] = $item->employee ? EmployeeBasicResource::make($item?->employee) : null;
            $records['media'] = MediaResource::collection($item->media);

            $diary['records'][] = $records;
        }

        $diary['dates'] = $dates;

        return $diary;
    }
}
