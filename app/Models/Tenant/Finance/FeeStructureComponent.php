<?php

namespace App\Models\Tenant\Finance;

use App\Casts\PriceCast;
use App\Concerns\HasFilter;
use App\Concerns\HasMeta;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FeeStructureComponent extends Model
{
    protected $connection = 'tenant';

    use HasFactory, HasFilter, HasMeta, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'fee_structure_components';

    protected $attributes = [];

    protected $casts = [
        'amount' => PriceCast::class,
        'meta' => 'array',
    ];

    protected $appends = [];

    public function record(): BelongsTo
    {
        return $this->belongsTo(FeeInstallmentRecord::class, 'fee_installment_record_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(FeeComponent::class, 'fee_component_id');
    }

    public function scopeByPeriod(Builder $query, $periodId = null)
    {
        $periodId = $periodId ?? auth()->user()->current_period_id;

        $query->whereHas('record', function ($query) use ($periodId) {
            $query->whereHas('installment', function ($query) use ($periodId) {
                $query->whereHas('structure', function ($query) use ($periodId) {
                    $query->where('period_id', $periodId);
                });
            });
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('fee_structure_component')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
