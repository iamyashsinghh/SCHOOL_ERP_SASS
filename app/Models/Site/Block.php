<?php

namespace App\Models\Site;

use App\Casts\EnumCast;
use App\Concerns\HasConfig;
use App\Concerns\HasFilter;
use App\Concerns\HasMeta;
use App\Concerns\HasStorage;
use App\Concerns\HasUuid;
use App\Enums\Site\BlockType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Block extends Model
{
    use HasConfig, HasFactory, HasFilter, HasMeta, HasStorage, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'site_blocks';

    protected $casts = [
        'type' => EnumCast::class.':'.BlockType::class,
        'assets' => 'array',
        'config' => 'array',
        'meta' => 'array',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function getSliderImagesAttribute(): array
    {
        return Arr::get($this->assets, 'slider_images', []);
    }

    public function getBackgroundColorAttribute(): ?string
    {
        return $this->getMeta('background_color');
    }

    public function getTextColorAttribute(): ?string
    {
        return $this->getMeta('text_color');
    }

    public function getHasFlippedAnimationAttribute(): bool
    {
        return (bool) $this->getMeta('has_flipped_animation', false);
    }

    public function getHasAccordionAttribute(): bool
    {
        return (bool) $this->getMeta('has_accordion', false);
    }

    protected function getDefaultSliderImageAttribute(): string
    {
        $cover = Arr::get($this->assets, 'slider_image');

        return $this->getImageFile(visibility: 'public', path: $cover, default: '/images/site/cover.webp');
    }

    protected function getCoverImageAttribute(): string
    {
        $cover = Arr::get($this->assets, 'cover');

        return $this->getImageFile(visibility: 'public', path: $cover, default: '/images/site/cover.webp');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('site_block')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
