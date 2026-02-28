<?php

namespace App\Services\Student;

use App\Enums\OptionType;
use App\Enums\QualificationResult;
use App\Http\Resources\OptionResource;
use App\Models\Tenant\Contact;
use App\Models\Tenant\Option;
use App\Models\Tenant\Qualification;
use App\Models\Tenant\Student\Student;
use Illuminate\Http\Request;

class QualificationService
{
    public function preRequisite(Request $request): array
    {
        $levels = OptionResource::collection(Option::query()
            ->byTeam()
            ->whereType(OptionType::QUALIFICATION_LEVEL->value)
            ->get());

        $results = QualificationResult::getOptions();

        return compact('levels', 'results');
    }

    public function findByUuidOrFail(Student $student, string $uuid): Qualification
    {
        return Qualification::query()
            ->whereHasMorph(
                'model',
                [Contact::class],
                function ($q) use ($student) {
                    $q->whereId($student->contact_id);
                }
            )
            ->whereUuid($uuid)
            ->getOrFail(trans('student.qualification.qualification'));
    }

    public function create(Request $request, Student $student): Qualification
    {
        \DB::beginTransaction();

        $qualification = Qualification::forceCreate($this->formatParams($request, $student));

        $student->contact->qualifications()->save($qualification);

        $qualification->addMedia($request);

        \DB::commit();

        return $qualification;
    }

    private function formatParams(Request $request, Student $student, ?Qualification $qualification = null): array
    {
        $formatted = [
            'level_id' => $request->level_id,
            'course' => $request->course,
            'institute' => $request->institute,
            'affiliated_to' => $request->affiliated_to,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'result' => $request->result,
        ];

        $meta = $qualification?->meta ?? [];

        $meta['is_submitted_original'] = $request->boolean('is_submitted_original');

        $formatted['meta'] = $meta;

        if (! $qualification) {
            //
        }

        $meta = $qualification?->meta ?? [];

        $meta['session'] = $request->input('session');
        $meta['institute_address'] = $request->institute_address;
        $meta['total_marks'] = $request->total_marks;
        $meta['obtained_marks'] = $request->obtained_marks;
        $meta['percentage'] = ($request->total_marks && is_numeric($request->total_marks)) ? round($request->obtained_marks / $request->total_marks * 100, 2) : null;
        $meta['failed_subjects'] = $request->failed_subjects;

        $formatted['meta'] = $meta;

        return $formatted;
    }

    public function update(Request $request, Student $student, Qualification $qualification): void
    {
        \DB::beginTransaction();

        $qualification->forceFill($this->formatParams($request, $student, $qualification))->save();

        $qualification->updateMedia($request);

        \DB::commit();
    }

    public function deletable(Student $student, Qualification $qualification): void
    {
        //
    }
}
