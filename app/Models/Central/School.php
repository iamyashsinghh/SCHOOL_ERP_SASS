<?php

namespace App\Models\Tenant\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'sub_division_id',
        'name',
        'status',
        'db_name',
        'db_username',
        'db_password',
        'storage_prefix'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'db_password',
    ];

    public function subDivision(): BelongsTo
    {
        return $this->belongsTo(SubDivision::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }
}
