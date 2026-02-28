<?php

namespace App\Models\Tenant\Helpdesk\Faq;

use App\Casts\EnumCast;
use App\Concerns\HasFilter;
use App\Concerns\HasMeta;
use App\Concerns\HasTags;
use App\Concerns\HasUuid;
use App\Enums\Helpdesk\Faq\Status;
use App\Enums\Helpdesk\Faq\Visibility;
use App\Models\Tenant\Option;
use App\Models\Tenant\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Faq extends Model
{
    protected $connection = 'tenant';

    use HasFactory, HasFilter, HasMeta, HasTags, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'faqs';

    protected $casts = [
        'visibility' => EnumCast::class.':'.Visibility::class,
        'status' => EnumCast::class.':'.Status::class,
        'meta' => 'array',
    ];

    protected $with = [];

    public function getModelName(): string
    {
        return 'Faq';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'category_id');
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function scopeFindIfExists(Builder $query, string $uuid, $field = 'message'): self
    {
        return $query->whereUuid($uuid)
            ->getOrFail(trans('helpdesk.faq.faq'), $field);
    }

    public function scopeByTeam(Builder $query, ?int $teamId = null)
    {
        $teamId = $teamId ?? auth()->user()?->current_team_id;

        $query->whereTeamId($teamId);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('faq')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
