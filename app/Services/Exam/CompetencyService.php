<?php

namespace App\Services\Exam;

use App\Http\Resources\Exam\GradeResource;
use App\Models\Exam\Competency;
use App\Models\Exam\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class CompetencyService
{
    public function preRequisite(Request $request)
    {
        $grades = GradeResource::collection(Grade::query()
            ->byPeriod()
            ->get());

        return compact('grades');
    }

    public function create(Request $request): Competency
    {
        \DB::beginTransaction();

        $competency = Competency::forceCreate($this->formatParams($request));

        \DB::commit();

        return $competency;
    }

    private function formatParams(Request $request, ?Competency $competency = null): array
    {
        $formatted = [
            'name' => $request->name,
            'grade_id' => $request->grade_id,
            'domains' => collect($request->domains)->map(function ($record, $index) {
                return [
                    'position' => $index + 1,
                    'name' => Arr::get($record, 'name'),
                    'code' => Arr::get($record, 'code'),
                    'indicators' => collect(Arr::get($record, 'indicators'))->map(function ($indicator, $indIndex) {
                        return [
                            'position' => $indIndex + 1,
                            'name' => Arr::get($indicator, 'name'),
                            'code' => Arr::get($indicator, 'code'),
                        ];
                    })->toArray(),
                ];
            })->toArray(),
            'description' => $request->description,
        ];

        if (! $competency) {
            $formatted['period_id'] = auth()->user()->current_period_id;
        }

        return $formatted;
    }

    public function update(Request $request, Competency $competency): void
    {
        \DB::beginTransaction();

        $competency->forceFill($this->formatParams($request, $competency))->save();

        \DB::commit();
    }

    public function deletable(Competency $competency): void
    {
        //
    }
}
