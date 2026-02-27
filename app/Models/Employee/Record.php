<?php

namespace App\Models\Employee;

use App\Casts\DateCast;
use App\Concerns\HasFilter;
use App\Concerns\HasMedia;
use App\Concerns\HasMeta;
use App\Concerns\HasUuid;
use App\Helpers\CalHelper;
use App\Models\Option;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Record extends Model
{
    use HasFactory, HasFilter, HasMedia, HasMeta, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'employee_records';

    protected $casts = [
        'start_date' => DateCast::class,
        'end_date' => DateCast::class,
        'is_ended' => 'boolean',
        'meta' => 'array',
    ];

    public function getModelName(): string
    {
        return 'EmployeeRecord';
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'employment_status_id');
    }

    public function scopeWithDetail(Builder $query)
    {
        $query->select(
            'employee_records.*',
            'departments.name as department_name',
            'departments.uuid as department_uuid',
            'departments.id as department_id',
            'designations.name as designation_name',
            'designations.uuid as designation_uuid',
            'designations.id as designation_id',
            'options.name as employment_status_name',
            'options.uuid as employment_status_uuid',
            'options.id as employment_status_id',
            'teams.name as team_name',
            'teams.uuid as team_uuid',
            'teams.id as team_id',
        )
            ->join('employees', 'employee_records.employee_id', '=', 'employees.id')
            ->join('teams', 'employees.team_id', '=', 'teams.id')
            ->leftJoin('departments', 'employee_records.department_id', '=', 'departments.id')
            ->leftJoin('designations', 'employee_records.designation_id', '=', 'designations.id')
            ->leftJoin('options', 'employee_records.employment_status_id', '=', 'options.id');
    }

    public function getPeriodAttribute(): string
    {
        return CalHelper::getPeriod($this->start_date->value, $this->end_date->value);
    }

    public function getDurationAttribute(): string
    {
        return CalHelper::getDuration($this->start_date->value, $this->end_date->value, 'day');
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
