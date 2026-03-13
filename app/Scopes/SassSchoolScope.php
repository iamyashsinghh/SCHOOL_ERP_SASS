<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * SassSchoolScope — Auto-filters ALL tenant queries by sass_school_id.
 *
 * This scope is applied globally via TenantServiceProvider so that
 * NO model files need to be touched. Every SELECT on a tenant table
 * automatically gets: WHERE sass_school_id = <current_school_id>
 */
class SassSchoolScope implements Scope
{
    /**
     * Tables that should NOT be filtered by sass_school_id.
     * These are system/framework tables that don't belong to any school.
     */
    protected static array $excludedTables = [
        'migrations',
        'failed_jobs',
        'jobs',
        'job_batches',
        'personal_access_tokens',
        'sessions',
    ];

    /**
     * Cached list of tables that have the sass_school_id column.
     */
    protected static ?array $tablesWithColumn = null;

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Only apply to models using the 'tenant' connection
        if ($model->getConnectionName() !== 'tenant') {
            return;
        }

        $table = $model->getTable();

        // Skip excluded system tables
        if (in_array($table, static::$excludedTables)) {
            return;
        }

        // Check if this table has the sass_school_id column (cached)
        if (!static::tableHasColumn($table)) {
            return;
        }

        // Get the current school ID from the container
        $sassSchoolId = app()->bound('sass_school_id') ? app('sass_school_id') : null;

        if ($sassSchoolId !== null) {
            $builder->where("{$table}.sass_school_id", $sassSchoolId);
        }
    }

    /**
     * Check if a table has the sass_school_id column (with caching).
     */
    public static function tableHasColumn(string $table): bool
    {
        // Build cache on first call
        if (static::$tablesWithColumn === null) {
            static::buildColumnCache();
        }

        return in_array($table, static::$tablesWithColumn);
    }

    /**
     * Build the cache of tables that have sass_school_id.
     */
    protected static function buildColumnCache(): void
    {
        try {
            $cacheKey = 'sass_school_tables_list';

            static::$tablesWithColumn = cache()->remember($cacheKey, 3600, function () {
                $tables = \Illuminate\Support\Facades\Schema::connection('tenant')->getTableListing();
                $result = [];

                foreach ($tables as $table) {
                    if (in_array($table, static::$excludedTables)) {
                        continue;
                    }

                    if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn($table, 'sass_school_id')) {
                        $result[] = $table;
                    }
                }

                return $result;
            });
        } catch (\Exception $e) {
            // During migrations or when DB is not ready, return empty
            static::$tablesWithColumn = [];
        }
    }

    /**
     * Clear the column cache (call after running migrations).
     */
    public static function clearCache(): void
    {
        static::$tablesWithColumn = null;
        cache()->forget('sass_school_tables_list');
    }
}
