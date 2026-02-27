<?php

namespace App\Models\Finance;

use App\Casts\DateCast;
use App\Casts\DateTimeCast;
use App\Casts\PriceCast;
use App\Concerns\HasFilter;
use App\Concerns\HasMedia;
use App\Concerns\HasMeta;
use App\Concerns\HasUuid;
use App\Enums\Finance\DayClosureStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DayClosure extends Model
{
    use HasFactory, HasFilter, HasMedia, HasMeta, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'day_closures';

    protected $casts = [
        'date' => DateCast::class,
        'total' => PriceCast::class,
        'status' => DayClosureStatus::class,
        'approved_at' => DateTimeCast::class,
        'denominations' => 'array',
        'meta' => 'array',
    ];

    public function getModelName(): string
    {
        return 'DayClosure';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getIsEditableAttribute(): bool
    {
        if ($this->user_id != auth()->id()) {
            return false;
        }

        if ($this->getMeta('type', 'manual') != 'auto') {
            return false;
        }

        return true;
    }

    public function scopeByTeam(Builder $query, ?int $teamId = null)
    {
        $teamId = $teamId ?? auth()->user()?->current_team_id;

        $query->where('team_id', $teamId);
    }

    public function scopeFindByUuidOrFail(Builder $query, ?string $uuid = null)
    {
        return $query
            ->whereUuid($uuid)
            ->when(auth()->user()->can('day-closure:manage'), function ($q) {
                return $q->whereNotNull('day_closures.id');
            }, function ($q) {
                return $q->where('day_closures.user_id', auth()->id());
            })
            ->getOrFail(trans('finance.day_closure.day_closure'));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('day_closure')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
