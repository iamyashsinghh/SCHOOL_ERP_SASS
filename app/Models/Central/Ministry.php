<?php

namespace App\Models\Tenant\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ministry extends Model
{
    protected $connection = 'central';

    protected $fillable = ['name', 'status'];

    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class);
    }
}
