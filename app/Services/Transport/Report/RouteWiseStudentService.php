<?php

namespace App\Services\Transport\Report;

use App\Http\Resources\Transport\RouteResource;
use App\Http\Resources\Transport\StoppageResource;
use App\Models\Tenant\Transport\Route;
use App\Models\Tenant\Transport\Stoppage;

class RouteWiseStudentService
{
    public function preRequisite(): array
    {
        $routes = RouteResource::collection(Route::query()
            ->with('vehicle')
            ->byPeriod()
            ->get());

        $stoppages = StoppageResource::collection(Stoppage::query()
            ->byPeriod()
            ->get());

        return compact('routes', 'stoppages');
    }
}
