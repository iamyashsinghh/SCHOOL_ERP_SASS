<?php

namespace App\Services;

use App\Models\Central\School;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class TenantConnectionSwitcher
{
    /**
     * Set up the tenant context for the given school.
     * 
     * In shared DB mode, we don't switch databases anymore.
     * We just set the sass_school_id and isolate storage/cache.
     *
     * @param School $school
     * @return void
     */
    public function switch(School $school): void
    {
        // Set the sass_school_id for auto-scoping
        app()->instance('sass_school_id', $school->id);

        // Ensure tenant connection is the default
        DB::setDefaultConnection('tenant');

        // Isolation: Storage Filesystem configuration
        Config::set('filesystems.disks.local.root', storage_path('app/tenants/' . $school->id));
        Config::set('filesystems.disks.public.root', storage_path('app/public/tenants/' . $school->id));
        Config::set('filesystems.disks.public.url', env('APP_URL') . '/storage/tenants/' . $school->id);

        // Isolation: Cache and Redis prefixing
        Config::set('cache.prefix', 'tenant_' . $school->id . '_cache_');
        
        if (config('database.redis.default')) {
            Config::set('database.redis.options.prefix', 'tenant_' . $school->id . '_redis_');
        }
    }
}
