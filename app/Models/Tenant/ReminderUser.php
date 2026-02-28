<?php

namespace App\Models\Tenant;

use App\Casts\DateTimeCast;
use App\Concerns\HasFilter;
use App\Concerns\HasMeta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ReminderUser extends Model
{
    protected $connection = 'tenant';

    use HasFactory, HasFilter, HasMeta, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'reminder_users';

    protected $casts = [
        'notified_at' => DateTimeCast::class,
        'meta' => 'array',
    ];

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('reminder_user')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
