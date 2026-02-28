<?php

namespace App\Models\Tenant\Transport\Vehicle;

use App\Casts\DateCast;
use App\Casts\PriceCast;
use App\Concerns\HasFilter;
use App\Concerns\HasMedia;
use App\Concerns\HasMeta;
use App\Concerns\HasUuid;
use App\Models\Tenant\Option;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CaseRecord extends Model
{
    protected $connection = 'tenant';

    use HasFactory, HasFilter, HasMedia, HasMeta, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'vehicle_case_records';

    protected $casts = [
        'date' => DateCast::class,
        'penalty' => PriceCast::class,
        'meta' => 'array',
    ];

    public function getModelName(): string
    {
        return 'VehicleCaseRecord';
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'type_id');
    }

    public function scopeByTeam(Builder $query, ?int $teamId = null)
    {
        $teamId = $teamId ?? auth()->user()?->current_team_id;

        $query->whereHas('vehicle', function ($q) use ($teamId) {
            $q->byTeam($teamId);
        });
    }

    public function scopeFindByUuidOrFail(Builder $query, ?string $uuid = null)
    {
        return $query
            ->whereHas('vehicle', function ($q) {
                $q->byTeam();
            })
            ->whereUuid($uuid)
            ->getOrFail(trans('transport.vehicle.case_record.case_record'));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('vehicle_case_record')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
