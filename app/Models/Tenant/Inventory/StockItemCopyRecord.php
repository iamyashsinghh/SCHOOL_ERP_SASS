<?php

namespace App\Models\Tenant\Inventory;

use App\Concerns\HasFilter;
use App\Concerns\HasMeta;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StockItemCopyRecord extends Model
{
    protected $connection = 'tenant';

    use HasFactory, HasFilter, HasMeta, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'stock_item_copy_records';

    protected $casts = [
        'meta' => 'array',
    ];

    public function itemRecord(): BelongsTo
    {
        return $this->belongsTo(StockItemRecord::class, 'stock_item_record_id');
    }

    public function itemCopy(): BelongsTo
    {
        return $this->belongsTo(StockItemCopy::class, 'stock_item_copy_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('stock_item_copy_record')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
