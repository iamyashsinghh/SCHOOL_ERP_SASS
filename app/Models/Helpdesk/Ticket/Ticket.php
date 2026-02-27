<?php

namespace App\Models\Helpdesk\Ticket;

use App\Casts\DateCast;
use App\Casts\DateTimeCast;
use App\Casts\TimeCast;
use App\Concerns\HasCustomField;
use App\Concerns\HasFilter;
use App\Concerns\HasMedia;
use App\Concerns\HasMeta;
use App\Concerns\HasTags;
use App\Concerns\HasUuid;
use App\Enums\CustomFieldForm;
use App\Enums\Helpdesk\Ticket\Status as TicketStatus;
use App\Helpers\CalHelper;
use App\Models\Option;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Ticket extends Model
{
    use HasCustomField, HasFactory, HasFilter, HasMedia, HasMeta, HasTags, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'tickets';

    protected $casts = [
        'status' => TicketStatus::class,
        'due_date' => DateCast::class,
        'due_time' => TimeCast::class,
        'resolved_at' => DateTimeCast::class,
        'cancelled_at' => DateTimeCast::class,
        'archived_at' => DateTimeCast::class,
        'meta' => 'array',
        'config' => 'array',
    ];

    public function getModelName(): string
    {
        return 'Ticket';
    }

    public function customFieldFormName(): string
    {
        return CustomFieldForm::TICKET->value;
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(Assignee::class);
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getIsRequesterAttribute(): bool
    {
        return $this->user_id == auth()->id();
    }

    public function getIsReviewerAttribute(): bool
    {
        return $this->assignees()->where('user_id', auth()->id())->exists();
    }

    public function isEditable(): bool
    {
        if ($this->status == TicketStatus::CLOSED) {
            return false;
        }

        return true;
    }

    public function scopeFilterAccessible(Builder $query)
    {
        if (auth()->user()->hasRole('admin')) {
            return;
        }

        $query->where(function ($q) {
            $q->where('tickets.user_id', auth()->id())
                ->orWhereHas('assignees', function ($q) {
                    $q->where('user_id', auth()->id());
                });
        });
    }

    public function scopeFindByUuidOrFail(Builder $query, string $uuid, $field = 'message'): self
    {
        return $query->where('tickets.uuid', $uuid)
            ->byTeam()
            ->filterAccessible()
            ->getOrFail(trans('helpdesk.ticket.ticket'), $field);
    }

    public function scopeByTeam(Builder $query, ?int $teamId = null)
    {
        $teamId = $teamId ?? auth()->user()?->current_team_id;

        $query->where('tickets.team_id', $teamId);
    }

    public function getIsResolvedAttribute(): bool
    {
        if (! $this->resolved_at->value) {
            return false;
        }

        return $this->resolved_at->carbon()->isPast() ? true : false;
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
            ->useLogName('ticket')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
