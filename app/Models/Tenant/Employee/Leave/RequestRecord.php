<?php

namespace App\Models\Tenant\Employee\Leave;

use App\Concerns\HasFilter;
use App\Concerns\HasMeta;
use App\Concerns\HasUuid;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RequestRecord extends Model
{
    protected $connection = 'tenant';

    use HasFactory, HasFilter, HasMeta, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'leave_request_records';

    protected $casts = [
        'meta' => 'array',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class, 'leave_request_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('leave_request')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
