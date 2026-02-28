<?php

namespace App\Models\Tenant\Employee\Attendance;

use App\Concerns\HasFilter;
use App\Concerns\HasMeta;
use App\Concerns\HasUuid;
use App\Enums\Employee\Attendance\Category as AttendanceCategory;
use App\Enums\Employee\Attendance\ProductionUnit as AttendanceProductionUnit;
use App\Models\Tenant\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Type extends Model
{
    protected $connection = 'tenant';

    use HasFactory, HasFilter, HasMeta, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'attendance_types';

    protected $casts = [
        'category' => AttendanceCategory::class,
        'unit' => AttendanceProductionUnit::class,
        'meta' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeByTeam(Builder $query, ?int $teamId = null)
    {
        $teamId = $teamId ?? auth()->user()?->current_team_id;

        $query->whereTeamId($teamId);
    }

    public function scopeDirect(Builder $query)
    {
        $query->whereNotIn('category', AttendanceCategory::productionBased());
    }

    public function scopeProductionBased(Builder $query)
    {
        $query->whereIn('category', AttendanceCategory::productionBased());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('attendance_type')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
