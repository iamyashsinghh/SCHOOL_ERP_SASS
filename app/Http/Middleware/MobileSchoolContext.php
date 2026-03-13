<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MobileSchoolContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ($user = $request->user()) {
            $token = $user->currentAccessToken();
            
            if ($token && $token->can('mobile-access')) {
                // Extract sass_school_id from token abilities
                $schoolIdAbility = collect($token->abilities)->first(function ($ability) {
                    return str_starts_with($ability, 'sass_school_id:');
                });

                if ($schoolIdAbility) {
                    $schoolId = explode(':', $schoolIdAbility)[1];
                    
                    // Bind to the container so that SassSchoolScope can use it
                    app()->instance('sass_school_id', $schoolId);
                }
            }
        }

        return $next($request);
    }
}
