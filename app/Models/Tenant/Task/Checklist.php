<?php

namespace App\Models\Tenant\Task;

use App\Casts\DateCast;
use App\Casts\DateTimeCast;
use App\Casts\TimeCast;
use App\Concerns\HasFilter;
use App\Concerns\HasMeta;
use App\Concerns\HasUuid;
use App\Helpers\CalHelper;
use App\Models\Tenant\Employee\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Checklist extends Model
{
    protected $connection = 'tenant';

    use HasFactory, HasFilter, HasMeta, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'task_checklists';

    protected $casts = [
        'due_date' => DateCast::class,
        'due_time' => TimeCast::class,
        'completed_at' => DateTimeCast::class,
        'meta' => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function scopeFindByUuidOrFail(Builder $query, ?string $uuid = null)
    {
        return $query
            ->whereUuid($uuid)
            ->getOrFail(trans('task.checklist.checklist'));
    }

    public function getDueDateTimeAttribute()
    {
        if (! $this->due_time->value) {
            return null;
        }

        return \Cal::time($this->due_date->value.' '.$this->due_time->value);
    }

    public function getDueAttribute()
    {
        if (! $this->due_time->value) {
            return $this->due_date;
        }

        return \Cal::dateTime($this->due_date->value.' '.$this->due_time->value);
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->is_completed) {
            return false;
        }

        $due = $this->due_date;

        if ($this->due_time->value) {
            $due = \Cal::dateTime($this->due_date->value.' '.$this->due_time->value);
        }

        if ($due->value > today()->toDateTimeString()) {
            return false;
        }

        return true;
    }

    public function getOverdueDaysAttribute(): int
    {
        if (! $this->is_overdue) {
            return 0;
        }

        return CalHelper::dateDiff(today()->toDateString(), $this->due_date->value, false);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('task_checklist')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
