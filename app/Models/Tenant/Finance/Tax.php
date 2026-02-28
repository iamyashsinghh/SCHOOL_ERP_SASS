<?php

namespace App\Models\Tenant\Finance;

use App\Casts\PercentCast;
use App\Concerns\HasConfig;
use App\Concerns\HasFilter;
use App\Concerns\HasMeta;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Tax extends Model
{
    protected $connection = 'tenant';

    use HasConfig, HasFactory, HasFilter, HasMeta, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'taxes';

    protected $casts = [
        'rate' => PercentCast::class,
        'components' => 'array',
        'config' => 'array',
        'meta' => 'array',
    ];

    protected $appends = ['code_with_rate'];

    protected $with = [];

    public function getCodeWithRateAttribute(): string
    {
        return $this->code.' ('.$this->rate?->formatted.')';
    }

    public function scopeByTeam(Builder $query, $teamId = null)
    {
        $teamId = $teamId ?? auth()->user()?->current_team_id;

        $query->where('team_id', $teamId);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('tax')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
