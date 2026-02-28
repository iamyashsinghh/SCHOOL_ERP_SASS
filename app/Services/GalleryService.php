<?php

namespace App\Services;

use App\Concerns\HasStorage;
use App\Enums\Employee\AudienceType as EmployeeAudienceType;
use App\Enums\GalleryType;
use App\Enums\Student\AudienceType as StudentAudienceType;
use App\Models\Tenant\Gallery;
use App\Support\HasAudience;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GalleryService
{
    use HasAudience, HasStorage;

    public function preRequisite(Request $request): array
    {
        $types = GalleryType::getOptions();

        $studentAudienceTypes = StudentAudienceType::getOptions();

        $employeeAudienceTypes = EmployeeAudienceType::getOptions();

        return compact('types', 'studentAudienceTypes', 'employeeAudienceTypes');
    }

    public function create(Request $request): Gallery
    {
        \DB::beginTransaction();

        $gallery = Gallery::forceCreate($this->formatParams($request));

        $this->storeAudience($gallery, $request->all());

        \DB::commit();

        return $gallery;
    }

    private function formatParams(Request $request, ?Gallery $gallery = null): array
    {
        $formatted = [
            'type' => $request->type,
            'title' => $request->title,
            'date' => $request->date,
            'audience' => [
                'student_type' => $request->student_audience_type,
                'employee_type' => $request->employee_audience_type,
            ],
            'is_public' => $request->boolean('is_public'),
            'description' => $request->description,
        ];

        if (! $gallery) {
            $formatted['team_id'] = auth()->user()?->current_team_id;
        }

        $meta = $gallery?->meta ?? [];
        $meta['excerpt'] = $request->excerpt;
        $formatted['meta'] = $meta;

        return $formatted;
    }

    public function update(Request $request, Gallery $gallery): void
    {
        \DB::beginTransaction();

        $this->prepareAudienceForUpdate($gallery, $request->all());

        $gallery->forceFill($this->formatParams($request, $gallery))->save();

        $this->updateAudience($gallery, $request->all());

        \DB::commit();
    }

    public function deletable(Gallery $gallery): void {}

    public function delete(Gallery $gallery): void
    {
        foreach ($gallery->images as $galleryImage) {
            $this->deleteImageFile(
                visibility: 'public',
                path: $galleryImage->path,
            );

            $this->deleteImageFile(
                visibility: 'public',
                path: Str::of($galleryImage->path)->replaceLast('.', '-thumb.'),
            );
        }

        $gallery->delete();
    }
}
