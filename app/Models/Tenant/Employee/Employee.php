<?php

namespace App\Models\Tenant\Employee;

use App\Casts\DateCast;
use App\Concerns\HasCustomField;
use App\Concerns\HasFilter;
use App\Concerns\HasMeta;
use App\Concerns\HasStorage;
use App\Concerns\HasTags;
use App\Concerns\HasUuid;
use App\Enums\Employee\Type;
use App\Helpers\CalHelper;
use App\Models\Tenant\Account;
use App\Models\Tenant\Contact;
use App\Models\Tenant\GroupMember;
use App\Models\Tenant\Qualification;
use App\Models\Tenant\Tag;
use App\Models\Tenant\Team;
use App\Scopes\Employee\EmployeeScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Employee extends Model
{
    protected $connection = 'tenant';

    use EmployeeScope, HasCustomField, HasFactory, HasFilter, HasMeta, HasStorage, HasTags, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'employees';

    protected $casts = [
        'type' => Type::class,
        'joining_date' => DateCast::class,
        'leaving_date' => DateCast::class,
        'config' => 'array',
        'meta' => 'array',
    ];

    protected $with = [];

    public function getIsDefaultAttribute()
    {
        return $this->getMeta('is_default') ? true : false;
    }

    public function getModelName(): string
    {
        return 'Employee';
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function groups(): MorphMany
    {
        return $this->morphMany(GroupMember::class, 'model');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function accounts(): MorphMany
    {
        return $this->morphMany(Account::class, 'accountable');
    }

    public function records(): HasMany
    {
        return $this->hasMany(Record::class);
    }

    public function qualifications(): HasMany
    {
        return $this->hasMany(Qualification::class, 'employee_id');
    }

    public function lastRecord(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    public function scopeFindByUuidOrFail(Builder $query, ?string $uuid = null)
    {
        return $query
            ->summary()
            ->filterAccessible()
            ->where('employees.uuid', '=', $uuid)
            ->getOrFail(trans('employee.employee'));
    }

    public function scopeFindSummaryByUuidOrFail(Builder $query, ?string $uuid = null)
    {
        return $query
            ->summary()
            ->filterAccessible()
            ->where('employees.uuid', '=', $uuid)
            ->getOrFail(trans('employee.employee'));
    }

    public function scopeFindDetailByUuidOrFail(Builder $query, ?string $uuid = null)
    {
        return $query
            ->detail()
            ->filterAccessible()
            ->where('employees.uuid', '=', $uuid)
            ->getOrFail(trans('employee.employee'));
    }

    public function scopeCodeNumberByTeam(Builder $query, ?int $teamId = null)
    {
        if (config('config.employee.enable_global_code_number')) {
            return;
        }

        $teamId = $teamId ?? auth()->user()?->current_team_id;

        $query->where('employees.team_id', $teamId);
    }

    public function scopeByTeam(Builder $query, ?int $teamId = null)
    {
        $teamId = $teamId ?? auth()->user()?->current_team_id;

        $query->where('employees.team_id', $teamId);
    }

    public function getPeriodAttribute(): string
    {
        return CalHelper::getPeriod($this->joining_date->value, $this->leaving_date->value);
    }

    public function getDurationAttribute(): string
    {
        return CalHelper::getDuration($this->joining_date->value, $this->leaving_date->value, 'day');
    }

    public function getPhotoUrlAttribute(): string
    {
        $photo = $this->photo;

        $default = '/images/'.($this->gender?->value ?? 'male').'.png';

        return $this->getImageFile(visibility: 'public', path: $photo, default: $default);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('employee')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
