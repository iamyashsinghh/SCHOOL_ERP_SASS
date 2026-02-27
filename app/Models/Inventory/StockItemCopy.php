<?php

namespace App\Models\Inventory;

use App\Casts\DateCast;
use App\Casts\EnumCast;
use App\Casts\PriceCast;
use App\Concerns\HasFilter;
use App\Concerns\HasMeta;
use App\Concerns\HasTags;
use App\Concerns\HasUuid;
use App\Enums\Inventory\HoldStatus;
use App\Models\Option;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StockItemCopy extends Model
{
    use HasFactory, HasFilter, HasMeta, HasTags, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'stock_item_copies';

    protected $casts = [
        'price' => PriceCast::class,
        'invoice_date' => DateCast::class,
        'hold_status' => EnumCast::class.':'.HoldStatus::class,
        'meta' => 'array',
    ];

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'condition_id');
    }

    public function place()
    {
        return $this->morphTo();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('stock_item_copy')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
