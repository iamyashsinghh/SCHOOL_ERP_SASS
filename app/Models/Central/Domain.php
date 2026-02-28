<?php

namespace App\Models\Tenant\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    protected $connection = 'central';

    protected $fillable = ['school_id', 'domain'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
