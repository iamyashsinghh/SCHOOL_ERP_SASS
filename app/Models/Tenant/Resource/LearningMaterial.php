<?php

namespace App\Models\Tenant\Resource;

use App\Casts\DateTimeCast;
use App\Concerns\HasConfig;
use App\Concerns\HasFilter;
use App\Concerns\HasMedia;
use App\Concerns\HasMeta;
use App\Concerns\HasUuid;
use App\Models\Tenant\Academic\Batch;
use App\Models\Tenant\Academic\BatchSubjectRecord;
use App\Models\Tenant\Audience;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Student\Student;
use App\Models\Tenant\Team;
use App\Models\Tenant\ViewLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LearningMaterial extends Model
{
    protected $connection = 'tenant';

    use HasConfig, HasFactory, HasFilter, HasMedia, HasMeta, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'learning_materials';

    protected $attributes = [];

    protected $casts = [
        'published_at' => DateTimeCast::class,
        'audience' => 'array',
        'config' => 'array',
        'meta' => 'array',
    ];

    public function getModelName(): string
    {
        return 'LearningMaterial';
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function records()
    {
        return $this->morphMany(BatchSubjectRecord::class, 'model');
    }

    public function audiences()
    {
        return $this->morphMany(Audience::class, 'shareable');
    }

    public function viewLogs()
    {
        return $this->morphMany(ViewLog::class, 'viewable');
    }

    public function scopeWithUserId(Builder $query)
    {
        $query
            ->select('learning_materials.*', 'contacts.user_id')
            ->leftJoin('employees', 'employees.id', '=', 'learning_materials.employee_id')
            ->leftJoin('contacts', 'contacts.id', '=', 'employees.contact_id');
    }

    public function scopeFilterAccessible(Builder $query)
    {
        if (auth()->user()->hasRole('admin')) {
            return;
        }

        $employeeId = null;
        $studentIds = [];

        if (auth()->user()->hasAnyRole(['student', 'guardian'])) {
            $studentIds = Student::query()
                ->byPeriod()
                ->record()
                ->filterForStudentAndGuardian()
                ->get()
                ->pluck('id')
                ->all();
        } else {
            $employeeId = Employee::query()
                ->auth()
                ->first()?->id;
        }

        $batchIds = Batch::query()
            ->byPeriod()
            ->filterAccessible()
            ->get()
            ->pluck('id')
            ->all();

        $query->where(function ($q) use ($batchIds, $employeeId, $studentIds) {
            $q->where(function ($q) use ($employeeId) {
                $q->whereNotNull('employee_id')
                    ->where('employee_id', $employeeId);
            })->orWhereHas('records', function ($q) use ($batchIds) {
                $q->whereIn('batch_subject_records.batch_id', $batchIds);
            })->orWhereHas('audiences', function ($q) use ($studentIds) {
                $q->where('audienceable_type', 'Student')
                    ->whereIn('audienceable_id', $studentIds);
            });
        });
    }

    public function scopeFindByUuidOrFail(Builder $query, ?string $uuid = null)
    {
        return $query
            ->with('records')
            ->byPeriod()
            ->withUserId()
            ->filterAccessible()
            ->where('learning_materials.uuid', $uuid)
            ->getOrFail(trans('resource.learning_material.learning_material'));
    }

    public function scopeByPeriod(Builder $query, $periodId = null)
    {
        $periodId = $periodId ?? auth()->user()->current_period_id;

        $query->wherePeriodId($periodId);
    }

    public function getIsEditableAttribute(): bool
    {
        if (! auth()->user()->can('learning-material:edit')) {
            return false;
        }

        if (config('config.resource.allow_edit_learning_material_by_accessible_user')) {
            return true;
        }

        if (auth()->user()->hasRole('staff') && $this->user_id != auth()->id()) {
            return false;
        }

        return true;
    }

    public function getIsDeletableAttribute(): bool
    {
        if (! auth()->user()->can('learning-material:delete')) {
            return false;
        }

        if (config('config.resource.allow_delete_learning_material_by_accessible_user')) {
            return true;
        }

        if (auth()->user()->hasRole('staff') && $this->user_id != auth()->id()) {
            return false;
        }

        return true;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('learning_material')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
