<?php

namespace App\Models\Tenant\Exam;

use App\Concerns\HasConfig;
use App\Concerns\HasFilter;
use App\Concerns\HasMeta;
use App\Concerns\HasUuid;
use App\Models\Tenant\Student\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CompetencyRecord extends Model
{
    protected $connection = 'tenant';

    use HasConfig, HasFactory, HasFilter, HasMeta, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'exam_competency_records';

    protected $casts = [
        'records' => 'array',
        'config' => 'array',
        'meta' => 'array',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function scopeFindByUuidOrFail(Builder $query, ?string $uuid = null)
    {
        return $query
            ->byPeriod()
            ->where('uuid', $uuid)
            ->getOrFail(trans('exam.competency.competency'));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('exam_competency_record')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
