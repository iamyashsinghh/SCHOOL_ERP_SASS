<?php

namespace App\Concerns;

use App\Models\Tenant\Config\Config;
use App\Models\Tenant\Team;
use App\Support\SetConfig;

trait SetConfigForJob
{
    public function setConfig(?int $teamId = null, array $modules = ['general', 'assets', 'system'])
    {
        if ($teamId) {
            $team = Team::find($teamId);
        }

        $config = Config::query()
            ->where(function ($q) use ($teamId) {
                $q->whereNull('team_id')
                    ->orWhere('team_id', $teamId);
            })
            ->whereIn('name', $modules)
            ->pluck('value', 'name')->all();

        (new SetConfig)->set($config);

        if ($teamId) {
            config(['config.team' => $team]);
        }
    }
}
