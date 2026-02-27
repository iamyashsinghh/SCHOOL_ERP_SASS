<?php

namespace App\Http\Controllers;

use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Services\TeamListService;
use App\Concerns\TeamAccessible;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    use TeamAccessible;

    public function preRequisite(Request $request, TeamService $service)
    {
        return $service->preRequisite($request);
    }

    public function index(Request $request, TeamListService $service)
    {
        return $service->paginate($request);
    }

    public function show(Team $team) : TeamResource
    {
        $this->isAccessible($team);

        $team->load('organization');

        return TeamResource::make($team);
    }
}
