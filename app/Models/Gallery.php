<?php

namespace App\Models;

use App\Casts\DateCast;
use App\Casts\DateTimeCast;
use App\Concerns\HasFilter;
use App\Concerns\HasMeta;
use App\Concerns\HasStorage;
use App\Concerns\HasUuid;
use App\Enums\GalleryType;
use App\Scopes\AudienceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Gallery extends Model
{
    use AudienceScope, HasFactory, HasFilter, HasMeta, HasStorage, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'galleries';

    protected $casts = [
        'type' => GalleryType::class,
        'date' => DateCast::class,
        'published_at' => DateTimeCast::class,
        'audience' => 'array',
        'config' => 'array',
        'meta' => 'array',
    ];

    public function getModelName(): string
    {
        return 'Gallery';
    }

    public function images(): HasMany
    {
        return $this->hasMany(GalleryImage::class, 'gallery_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function audiences()
    {
        return $this->morphMany(Audience::class, 'shareable');
    }

    public function scopeWithThumbnail(Builder $query)
    {
        $query->addSelect(['path' => GalleryImage::select('path')
            ->whereColumn('gallery_id', 'galleries.id')
            ->limit(1),
        ]);
    }

    public function getExcerptAttribute(): ?string
    {
        return $this->getMeta('excerpt');
    }

    public function getThumbnailUrlAttribute(): string
    {
        $path = $this->path;

        $default = '/images/item/cover.jpeg';

        return $this->getImageFile(visibility: 'public', path: $path, default: $default);
    }

    public function scopeByTeam(Builder $query, ?int $teamId = null)
    {
        $teamId = $teamId ?? auth()->user()?->current_team_id;

        $query->whereTeamId($teamId);
    }

    public function scopeFilterAccessible(Builder $query)
    {
        if (auth()->user()->can('admin')) {
            return;
        }

        if (auth()->user()->can('gallery:create')) {
            return;
        }

        $query->where(function ($q) {
            $q->accessible()
                ->orWhere('is_public', true);
        });
    }

    public function scopeFindByUuidOrFail(Builder $query, ?string $uuid = null)
    {
        return $query
            ->byTeam()
            ->whereUuid($uuid)
            ->getOrFail(trans('gallery.gallery'));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('gallery')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
