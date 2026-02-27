<?php

namespace App\Actions\Config;

use App\Support\BuildConfig;
use Closure;

class GetAppConfig
{
    use BuildConfig;

    public function handle($config, Closure $next)
    {
        $config = $this->generate(
            config: $config,
            params: [
                'mask' => true,
                'show_public' => auth()->check() ? false : true,
                'hide_html' => true,
            ],
        );

        $config['system']['ac'] = true;
        $config['system']['show_setup_wizard'] = true;

        return $next($config);
    }
}
