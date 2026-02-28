<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string|null  ...$guards
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $host = $request->getHost();
                $centralDomain = env('CENTRAL_DOMAIN', 'governance.localhost');
                $allowedCentralDomains = [$centralDomain, 'localhost', '127.0.0.1'];
                
                $isCentral = (app()->bound('tenant.central') && app('tenant.central')) 
                            || in_array($host, $allowedCentralDomains);

                if ($isCentral) {
                    return redirect()->route('central.dashboard');
                }
                return redirect(RouteServiceProvider::HOME);
            }
        }

        return $next($request);
    }
}
