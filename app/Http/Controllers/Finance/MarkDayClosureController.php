<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\MarkDayClosureService;
use Illuminate\Http\Request;

class MarkDayClosureController extends Controller
{
    public function __invoke(Request $request, MarkDayClosureService $service)
    {
        $service->markDayClosure($request);

        return response()->success([
            'message' => trans('global.marked', ['attribute' => trans('finance.day_closure.day_closure')]),
        ]);
    }
}
