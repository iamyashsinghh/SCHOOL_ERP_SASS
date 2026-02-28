<?php

namespace App\Models\Tenant\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    protected $connection = 'central';

    protected $fillable = ['ministry_id', 'name'];

    public function ministry(): BelongsTo
    {
        return $this->belongsTo(Ministry::class);
    }

    public function subDivisions(): HasMany
    {
        return $this->hasMany(SubDivision::class);
    }
}
