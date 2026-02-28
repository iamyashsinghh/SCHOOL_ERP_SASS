<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            $host = $request->getHost();
            $centralDomain = env('CENTRAL_DOMAIN', 'governance.localhost');
            $allowedCentralDomains = [$centralDomain, 'localhost', '127.0.0.1'];
            
            $isCentral = (app()->bound('tenant.central') && app('tenant.central')) 
                        || in_array($host, $allowedCentralDomains);

            if ($isCentral) {
                return route('central.login');
            }
            
            return route('app');
        }
    }
}
