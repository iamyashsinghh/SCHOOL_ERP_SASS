<?php

namespace App\Providers;

use App\Scopes\SassSchoolScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class TenantServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind the active tenant school to the container for dependency injection
        $this->app->singleton('tenant', function ($app) {
            return $app->instance('tenant.active', null);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // ─── SASS SCHOOL ID AUTO-INJECTION ──────────────────────────
        // These two hooks make sass_school_id work AUTOMATICALLY
        // across ALL tenant models without touching any model file.
        // ─────────────────────────────────────────────────────────────

        // 1. AUTO-FILTER: Every SELECT query on tenant models
        //    automatically gets: WHERE sass_school_id = <current_school>
        Model::addGlobalScope(new SassSchoolScope());

        // 2. AUTO-SET: Every INSERT on tenant models
        //    automatically sets: sass_school_id = <current_school>
        Model::creating(function (Model $model) {
            // Only apply to tenant connection models
            if ($model->getConnectionName() !== 'tenant') {
                return;
            }

            // Skip if sass_school_id is already set (e.g., during seeding)
            if (!empty($model->sass_school_id)) {
                return;
            }

            // Skip system tables
            if (!SassSchoolScope::tableHasColumn($model->getTable())) {
                return;
            }

            // Set the sass_school_id from the container
            if (app()->bound('sass_school_id')) {
                $model->sass_school_id = app('sass_school_id');
            }
        });

        // 3. AUTO-SET on update too (safety net for mass updates via Eloquent)
        Model::updating(function (Model $model) {
            if ($model->getConnectionName() !== 'tenant') {
                return;
            }

            // Ensure sass_school_id is never accidentally changed
            if ($model->isDirty('sass_school_id') && $model->getOriginal('sass_school_id')) {
                $model->sass_school_id = $model->getOriginal('sass_school_id');
            }
        });
    }
}
