<?php

namespace App\Models\Task;

use App\Casts\DateCast;
use App\Casts\DateTimeCast;
use App\Casts\TimeCast;
use App\Concerns\HasCustomField;
use App\Concerns\HasFilter;
use App\Concerns\HasMedia;
use App\Concerns\HasMeta;
use App\Concerns\HasTags;
use App\Concerns\HasUuid;
use App\Concerns\Task\TaskAction;
use App\Concerns\Task\TaskConstraint;
use App\Enums\CustomFieldForm;
use App\Helpers\CalHelper;
use App\Models\Employee\Employee;
use App\Models\Option;
use App\Models\Tag;
use App\Models\Team;
use App\Scopes\Task\TaskScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Task extends Model
{
    use HasCustomField, HasFactory, HasFilter, HasMedia, HasMeta, HasTags, HasUuid, LogsActivity, TaskAction, TaskConstraint, TaskScope;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'tasks';

    protected $casts = [
        'start_date' => DateCast::class,
        'due_date' => DateCast::class,
        'due_time' => TimeCast::class,
        'completed_at' => DateTimeCast::class,
        'cancelled_at' => DateTimeCast::class,
        'archived_at' => DateTimeCast::class,
        'meta' => 'array',
        'config' => 'array',
        'repeatation' => 'array',
    ];

    public function getModelName(): string
    {
        return 'Task';
    }

    public function customFieldFormName(): string
    {
        return CustomFieldForm::TASK->value;
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function memberLists(): HasMany
    {
        return $this->hasMany(Member::class, 'task_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'priority_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'category_id');
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'list_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'member_employee_id');
    }

    public function scopeFindByUuidOrFail(Builder $query, string $uuid, $field = 'message'): self
    {
        return $query->where('tasks.uuid', $uuid)
            ->select(
                'tasks.*',
                'task_members.employee_id as member_employee_id',
                'task_members.meta as member_meta',
                'task_members.is_favorite as is_favorite',
                'employees.contact_id as member_contact_id',
                'contacts.user_id as member_user_id'
            )
            ->byTeam()
            ->withOwner()
            ->with('memberLists:employee_id,task_id,is_owner')
            ->leftJoin('task_members', function ($join) {
                $join->on('tasks.id', '=', 'task_members.task_id')
                    ->leftJoin('employees', 'task_members.employee_id', '=', 'employees.id')
                    ->join('contacts', function ($join) {
                        $join->on('employees.contact_id', '=', 'contacts.id')->where('contacts.user_id', auth()->id());
                    });
            })
            ->getOrFail(trans('task.task'), $field);
    }

    public function scopeByTeam(Builder $query, ?int $teamId = null)
    {
        $teamId = $teamId ?? auth()->user()?->current_team_id;

        $query->where('tasks.team_id', $teamId);
    }

    public function getIsOwnerAttribute(): bool
    {
        if ($this->user_id == auth()->id()) {
            return true;
        }

        return $this->owner?->user_id == auth()->id() ? true : false;
    }

    public function getIsMemberAttribute(): bool
    {
        if ($this->user_id == auth()->id()) {
            return true;
        }

        return $this->member_user_id == auth()->id() ? true : false;
    }

    public function getIsCompletedAttribute(): bool
    {
        if (! $this->completed_at->value) {
            return false;
        }

        return $this->completed_at->carbon()->isPast() ? true : false;
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
            ->useLogName('task')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
