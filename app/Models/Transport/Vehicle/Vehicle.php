<?php

namespace App\Models\Transport\Vehicle;

use App\Concerns\HasFilter;
use App\Concerns\HasMeta;
use App\Concerns\HasUuid;
use App\Enums\Transport\Vehicle\FuelType;
use App\Models\Document;
use App\Models\Incharge;
use App\Models\Option;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Vehicle extends Model
{
    use HasFactory, HasFilter, HasMeta, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'vehicles';

    protected $casts = [
        'fuel_type' => FuelType::class,
        'registration' => 'array',
        'owner' => 'array',
        'driver' => 'array',
        'helper' => 'array',
        'disposal' => 'array',
        'meta' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(Option::class);
    }

    public function incharge(): BelongsTo
    {
        return $this->belongsTo(Incharge::class);
    }

    public function incharges(): MorphMany
    {
        return $this->morphMany(Incharge::class, 'model');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function scopeWithCurrentIncharges(Builder $query)
    {
        $query->with([
            'incharges' => function ($q) {
                return $q->where('start_date', '<=', today()->toDateString())
                    ->where(function ($q) {
                        $q->whereNull('end_date')
                            ->orWhere('end_date', '>=', today()->toDateString());
                    });
            },
            'incharges.employee' => fn ($q) => $q->detail(),
        ]);
    }

    public function scopeWithLastIncharge(Builder $query)
    {
        $query->addSelect([
            'incharge_id' => Incharge::select('id')
                ->whereColumn('model_id', 'vehicles.id')
                ->where('model_type', 'Vehicle')
                ->where('effective_date', '<=', today()->toDateString())
                ->orderBy('effective_date', 'desc')
                ->limit(1),
        ])->with(['incharge', 'incharge.employee' => fn ($q) => $q->detail()]);
    }

    public function scopeByTeam(Builder $query, ?int $teamId = null)
    {
        $teamId = $teamId ?? auth()->user()?->current_team_id;

        $query->whereTeamId($teamId);
    }

    public function scopeFindByUuidOrFail(Builder $query, ?string $uuid = null)
    {
        return $query
            ->byTeam()
            ->whereUuid($uuid)
            ->getOrFail(trans('transport.vehicle.vehicle'));
    }

    public function scopeFindDetailByUuidOrFail(Builder $query, ?string $uuid = null)
    {
        return $query
            ->with('type')
            ->withCurrentIncharges()
            ->byTeam()
            ->whereUuid($uuid)
            ->getOrFail(trans('transport.vehicle.vehicle'));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('vehicle')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
