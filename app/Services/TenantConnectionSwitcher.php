<?php

namespace App\Services;

use App\Models\Central\School;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class TenantConnectionSwitcher
{
    /**
     * Switch the active database connection to the given school's tenant database.
     *
     * @param School $school
     * @return void
     */
    public function switch(School $school): void
    {
        // Purge the existing tenant connection to prevent leakages
        DB::purge('tenant');

        // Dynamically override the tenant connection config
        Config::set('database.connections.tenant.database', $school->db_name);
        Config::set('database.connections.tenant.username', $school->db_username);
        
        // Ensure to decrypt the securely stored password
        Config::set('database.connections.tenant.password', Crypt::decryptString($school->db_password));

        // Reconnect using the new config and make it the default connection
        DB::reconnect('tenant');
        DB::setDefaultConnection('tenant');

        // Isolation: Storage Filesystem configuration
        Config::set('filesystems.disks.local.root', storage_path('app/tenants/' . $school->id));
        Config::set('filesystems.disks.public.root', storage_path('app/public/tenants/' . $school->id));
        Config::set('filesystems.disks.public.url', env('APP_URL') . '/storage/tenants/' . $school->id);

        // Isolation: Cache and Redis prefixing to avoid cross-tenant cache bleeding
        Config::set('cache.prefix', 'tenant_' . $school->id . '_cache_');
        
        if (config('database.redis.default')) {
            Config::set('database.redis.options.prefix', 'tenant_' . $school->id . '_redis_');
        }
    }
}
