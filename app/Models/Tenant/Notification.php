<?php

namespace App\Models\Tenant;

use App\Casts\DateTimeCast;
use App\Concerns\HasFilter;
use App\Concerns\HasMeta;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $connection = 'tenant';

    use HasFilter, HasMeta, HasUuid;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'app_notifications';

    protected $casts = [
        'read_at' => DateTimeCast::class,
        'data' => 'array',
        'meta' => 'array',
    ];

    protected $with = [];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
