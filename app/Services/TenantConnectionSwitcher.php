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
    }
}
