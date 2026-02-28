<?php

namespace App\Concerns;

use App\Models\Tenant\Activity;

trait RecordActivity
{
    public function record(string $activity, ?string $event = null, array $attributes = [], array $properties = []): void
    {
        Activity::forceCreate([
            'description' => [
                'activity' => $this->getActivityKey($activity),
                'attributes' => $attributes,
            ],
            'event' => $event ?? $activity,
            'subject_type' => $this->getMorphClass(),
            'subject_id' => $this->id,
            'user_id' => auth()->id(),
            'properties' => $properties,
        ]);
    }
}
