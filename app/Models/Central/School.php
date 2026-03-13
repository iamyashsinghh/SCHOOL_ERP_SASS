<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'sub_division_id',
        'ministry_id',
        'province_id',
        'name',
        'code',
        'status',
        'contact_email',
        'contact_phone',
        'address',
        'logo',
        'admin_username',
        'admin_password_reference',
    ];

    public function subDivision(): BelongsTo
    {
        return $this->belongsTo(SubDivision::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * Check if school is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
