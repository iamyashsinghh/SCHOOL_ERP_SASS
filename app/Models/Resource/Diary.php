<?php

namespace App\Models\Resource;

use App\Casts\DateCast;
use App\Concerns\HasConfig;
use App\Concerns\HasFilter;
use App\Concerns\HasMedia;
use App\Concerns\HasMeta;
use App\Concerns\HasUuid;
use App\Models\Academic\Batch;
use App\Models\Academic\BatchSubjectRecord;
use App\Models\Audience;
use App\Models\Employee\Employee;
use App\Models\Student\Student;
use App\Models\ViewLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Diary extends Model
{
    use HasConfig, HasFactory, HasFilter, HasMedia, HasMeta, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'student_diaries';

    protected $attributes = [];

    protected $casts = [
        'date' => DateCast::class,
        'details' => 'array',
        'config' => 'array',
        'meta' => 'array',
    ];

    public function getModelName(): string
    {
        return 'StudentDiary';
    }

    public function records()
    {
        return $this->morphMany(BatchSubjectRecord::class, 'model');
    }

    public function audiences()
    {
        return $this->morphMany(Audience::class, 'shareable');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function viewLogs()
    {
        return $this->morphMany(ViewLog::class, 'viewable');
    }

    public function scopeWithUserId(Builder $query)
    {
        $query
            ->select('student_diaries.*', 'contacts.user_id')
            ->leftJoin('employees', 'employees.id', '=', 'student_diaries.employee_id')
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
            ->where('student_diaries.uuid', $uuid)
            ->getOrFail(trans('resource.diary.diary'));
    }

    public function scopeByPeriod(Builder $query, ?int $periodId = null)
    {
        $periodId = $periodId ?? auth()->user()->current_period_id;

        $query->wherePeriodId($periodId);
    }

    public function getIsEditableAttribute(): bool
    {
        if (! auth()->user()->can('student-diary:edit')) {
            return false;
        }

        if (config('config.resource.allow_edit_diary_by_accessible_user')) {
            return true;
        }

        if (auth()->user()->hasRole('staff') && $this->user_id != auth()->id()) {
            return false;
        }

        return true;
    }

    public function getIsDeletableAttribute(): bool
    {
        if (! auth()->user()->can('student-diary:delete')) {
            return false;
        }

        if (config('config.resource.allow_delete_diary_by_accessible_user')) {
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
            ->useLogName('student_diary')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
