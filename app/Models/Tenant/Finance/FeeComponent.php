<?php

namespace App\Models\Tenant\Finance;

use App\Concerns\HasFilter;
use App\Concerns\HasMeta;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FeeComponent extends Model
{
    protected $connection = 'tenant';

    use HasFactory, HasFilter, HasMeta, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'fee_components';

    protected $attributes = [];

    protected $casts = [
        'meta' => 'array',
    ];

    public function head(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class, 'fee_head_id');
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function scopeByPeriod(Builder $query, ?int $periodId = null)
    {
        $periodId = $periodId ?? auth()->user()->current_period_id;

        $query->whereHas('head', function ($q) use ($periodId) {
            $q->wherePeriodId($periodId);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('fee_component')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
