<?php

namespace App\Providers;

use App\Models\Central\School;
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
        //
    }
}
