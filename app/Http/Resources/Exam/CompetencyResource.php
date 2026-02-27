<?php

namespace App\Http\Resources\Exam;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CompetencyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'grade' => GradeResource::make($this->whenLoaded('grade')),
            'domains' => collect($this->domains)->map(function ($record) {
                return [
                    'uuid' => (string) Str::uuid(),
                    'position' => Arr::get($record, 'position', 0),
                    'name' => Arr::get($record, 'name'),
                    'code' => Arr::get($record, 'code'),
                    'indicators' => collect(Arr::get($record, 'indicators'))->map(function ($indicator) {
                        return [
                            'uuid' => (string) Str::uuid(),
                            'position' => Arr::get($indicator, 'position', 0),
                            'name' => Arr::get($indicator, 'name'),
                            'code' => Arr::get($indicator, 'code'),
                        ];
                    })->toArray(),
                ];
            }),
            'description' => $this->description,
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
