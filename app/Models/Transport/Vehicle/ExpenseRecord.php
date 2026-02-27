<?php

namespace App\Models\Transport\Vehicle;

use App\Casts\DateCast;
use App\Casts\PriceCast;
use App\Concerns\HasFilter;
use App\Concerns\HasMedia;
use App\Concerns\HasMeta;
use App\Concerns\HasReminder;
use App\Concerns\HasUuid;
use App\Models\Option;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ExpenseRecord extends Model
{
    use HasFactory, HasFilter, HasMedia, HasMeta, HasReminder, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'vehicle_expense_records';

    protected $casts = [
        'date' => DateCast::class,
        'next_due_date' => DateCast::class,
        'price_per_unit' => PriceCast::class,
        'amount' => PriceCast::class,
        'meta' => 'array',
    ];

    public function getModelName(): string
    {
        return 'VehicleExpenseRecord';
    }

    public function getReminderTitle(): string
    {
        return trans('transport.vehicle.vehicle').
            ': '.
            $this->vehicle->name;
    }

    public function getReminderSubTitle(): string
    {
        return trans('transport.vehicle.expense_record.expense_record').' #'.$this->code_number;
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'type_id');
    }

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
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
            ->getOrFail(trans('transport.vehicle.expense_record.expense_record'));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('vehicle_expense_record')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
