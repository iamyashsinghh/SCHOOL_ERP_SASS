<?php

namespace App\Http\Resources;

use App\Enums\Employee\AudienceType as EmployeeAudienceType;
use App\Enums\GalleryType;
use App\Enums\Student\AudienceType as StudentAudienceType;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class GalleryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $studentAudienceType = Arr::get($this->audience, 'student_type');
        $employeeAudienceType = Arr::get($this->audience, 'employee_type');

        $audienceTypes = [];
        if ($studentAudienceType) {
            $audienceTypes[] = StudentAudienceType::getLabel($studentAudienceType);
        }

        if ($employeeAudienceType) {
            $audienceTypes[] = EmployeeAudienceType::getLabel($employeeAudienceType);
        }

        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'title_excerpt' => Str::summary($this->title, 50),
            'images_count' => $this->images_count,
            'audiences' => AudienceResource::collection($this->whenLoaded('audiences')),
            'type' => GalleryType::getDetail($this->type),
            'images' => GalleryImageResource::collection($this->whenLoaded('images')),
            'thumbnail_url' => $this->thumbnail_url,
            'excerpt' => $this->excerpt,
            'description' => $this->description,
            'date' => $this->date,
            'is_public' => $this->is_public,
            $this->mergeWhen($this->is_public, [
                'audience_types' => [trans('general.public')],
                'excerpt' => $this->getMeta('excerpt'),
            ]),
            $this->mergeWhen(! $this->public, [
                'audience_types' => $audienceTypes,
                'student_audience_type' => StudentAudienceType::getDetail($studentAudienceType),
                'employee_audience_type' => EmployeeAudienceType::getDetail($employeeAudienceType),
            ]),
            'published_at' => $this->published_at,
            'created_at' => \Cal::dateTime($this->created_at),
            'updated_at' => \Cal::dateTime($this->updated_at),
        ];
    }
}
