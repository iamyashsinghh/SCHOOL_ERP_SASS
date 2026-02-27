<?php

namespace App\Http\Resources\Employee;

use App\Enums\Gender;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentSummaryResource extends JsonResource
{
    public function toArray($request)
    {
        $documentLists = [];

        $documentTypes = $request->document_types ?? collect([]);
        $documents = $request->documents ?? collect([]);

        foreach ($documentTypes as $documentType) {
            $document = $documents->where('type_id', $documentType->id)
                ->where('documentable_id', $this->contact_id)
                ->first();

            $documentLists[str_replace('-', '_', $documentType->uuid)] = [
                'key' => str_replace('-', '_', $documentType->uuid),
                'uuid' => $documentType->uuid,
                'type' => $documentType->name,
                'is_available' => $document ? true : false,
                'status' => $document?->getDetailedStatus($documentType) ?? [
                    'label' => '-',
                    'value' => 'not_available',
                    'color' => 'danger',
                ],
            ];
        }

        return [
            'uuid' => $this->uuid,
            'code_number' => $this->code_number,
            'name' => $this->name,
            'birth_date' => is_string($this->birth_date) ? \Cal::date($this->birth_date) : $this->birth_date,
            'photo' => $this->photo_url,
            'gender' => Gender::getDetail($this->gender),
            'employment_status' => $this->employment_status_name ?? '-',
            'department' => $this->department_name ?? '-',
            'designation' => $this->designation_name ?? '-',
            'self' => $this->user_id == auth()->id() ? true : false,
            'joining_date' => $this->joining_date,
            'leaving_date' => $this->leaving_date,
            'documents' => $documentLists,
            'created_at' => \Cal::dateTime($this->created_at),
        ];
    }
}
