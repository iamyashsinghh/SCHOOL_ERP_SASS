<?php

namespace App\Models\Central;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class CentralUser extends Authenticatable
{
    use Notifiable;

    protected $connection = 'central';

    protected $table = 'central_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'entity_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    public const ROLE_PLATFORM_OWNER = 'platform_owner';
    public const ROLE_MINISTRY_ADMIN = 'ministry_admin';
    public const ROLE_PROVINCE_ADMIN = 'province_admin';
    public const ROLE_SUBDIVISION_ADMIN = 'subdivision_admin';

    public function isPlatformOwner(): bool
    {
        return $this->role === self::ROLE_PLATFORM_OWNER;
    }

    public function isMinistryAdmin(): bool
    {
        return $this->role === self::ROLE_MINISTRY_ADMIN;
    }

    public function isProvinceAdmin(): bool
    {
        return $this->role === self::ROLE_PROVINCE_ADMIN;
    }

    public function isSubdivisionAdmin(): bool
    {
        return $this->role === self::ROLE_SUBDIVISION_ADMIN;
    }

    /**
     * Scope for specific roles
     */
    public function scopeRole($query, $role)
    {
        return $query->where('role', $role);
    }
}
