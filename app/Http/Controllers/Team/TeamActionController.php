<?php

namespace App\Http\Controllers\Team;

use App\Concerns\TeamAccessible;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\ConfigRequest;
use App\Models\Tenant\Team;
use App\Services\Team\TeamActionService;

class TeamActionController extends Controller
{
    use TeamAccessible;

    public function storeConfig(ConfigRequest $request, Team $team, TeamActionService $service)
    {
        $this->isAccessible($team);

        $service->storeConfig($request, $team);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('team.team')]),
        ]);
    }
}
