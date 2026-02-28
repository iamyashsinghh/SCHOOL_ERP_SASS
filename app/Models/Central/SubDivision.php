<?php

namespace App\Models\Tenant\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubDivision extends Model
{
    protected $connection = 'central';

    protected $fillable = ['province_id', 'name'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }
}
