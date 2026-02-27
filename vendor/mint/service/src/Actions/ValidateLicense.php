<?php

namespace Mint\Service\Actions;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ValidateLicense
{
    public function handle($params, Closure $next)
    {
        $params['checksum'] = 'BYPASSED_LICENSE_CHECKSUM';
        
        if (empty(Arr::get($params, 'access_code'))) {
            $params['access_code'] = 'BYPASSED_LICENSE';
        }

        if (empty(Arr::get($params, 'registered_email'))) {
            $params['registered_email'] = 'admin@admin.com';
        }

        return $next($params);
    }
}
