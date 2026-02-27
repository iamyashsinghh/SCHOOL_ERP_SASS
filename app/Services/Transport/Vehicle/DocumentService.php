<?php

namespace App\Services\Transport\Vehicle;

use App\Enums\DocumentExpiryStatus;
use App\Enums\OptionType;
use App\Http\Resources\Transport\Vehicle\Config\DocumentTypeResource;
use App\Http\Resources\Transport\Vehicle\VehicleResource;
use App\Models\Document;
use App\Models\Option;
use App\Models\Transport\Vehicle\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DocumentService
{
    public function preRequisite(Request $request): array
    {
        $vehicles = VehicleResource::collection(Vehicle::query()
            ->byTeam()
            ->get());

        $types = DocumentTypeResource::collection(Option::query()
            ->byTeam()
            ->whereType(OptionType::VEHICLE_DOCUMENT_TYPE->value)
            ->get());

        $statuses = DocumentExpiryStatus::getOptions();

        return compact('vehicles', 'types', 'statuses');
    }

    public function findByUuidOrFail(string $uuid): Document
    {
        return Document::query()
            ->whereHasMorph(
                'documentable',
                [Vehicle::class],
                function (Builder $query) {
                    $query->byTeam();
                }
            )
            ->whereUuid($uuid)
            ->getOrFail(trans('transport.vehicle.document.document'));
    }

    public function create(Request $request): Document
    {
        \DB::beginTransaction();

        $vehicleDocument = Document::forceCreate($this->formatParams($request));

        $vehicleDocument->addMedia($request);

        \DB::commit();

        return $vehicleDocument;
    }

    private function formatParams(Request $request, ?Document $vehicleDocument = null): array
    {
        $formatted = [
            'documentable_type' => 'Vehicle',
            'documentable_id' => $request->vehicle_id,
            'type_id' => $request->type_id,
            'title' => $request->title,
            'number' => $request->number,
            'issue_date' => $request->issue_date,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date ?: null,
            'description' => $request->description,
        ];

        if (! $vehicleDocument) {
            //
        }

        return $formatted;
    }

    public function update(Request $request, Document $vehicleDocument): void
    {
        \DB::beginTransaction();

        $vehicleDocument->forceFill($this->formatParams($request, $vehicleDocument))->save();

        $vehicleDocument->updateMedia($request);

        \DB::commit();
    }

    public function deletable(Document $vehicleDocument): void
    {
        //
    }
}
