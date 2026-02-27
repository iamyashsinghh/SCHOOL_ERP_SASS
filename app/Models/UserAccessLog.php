<?php

namespace App\Models;

use App\Concerns\HasFilter;
use App\Concerns\HasMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAccessLog extends Model
{
    use HasFilter, HasMeta;

    protected $guarded = [];

    protected $table = 'user_access_logs';

    protected $casts = [
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function log(string $event, array $meta = []): void
    {
        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        static::create([
            'user_id' => $userId,
            'event' => $event,
            'meta' => $meta,
        ]);
    }

    public static function logOnce(string $event, int $minutes = 5, array $meta = []): void
    {
        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        $alreadyLogged = static::where('user_id', $userId)
            ->where('event', $event)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->exists();

        if (! $alreadyLogged) {
            static::log($event, $meta);
        }
    }
}
