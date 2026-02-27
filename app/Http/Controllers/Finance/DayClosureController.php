<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\DayClosureRequest;
use App\Http\Resources\Finance\DayClosureResource;
use App\Models\Finance\DayClosure;
use App\Services\Finance\DayClosureListService;
use App\Services\Finance\DayClosureService;
use App\Services\Finance\MarkDayClosureService;
use Illuminate\Http\Request;

class DayClosureController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
        $this->middleware('permission:day-closure:manage')->only(['destroy']);
    }

    public function preRequisite(DayClosureService $service)
    {
        return response()->ok($service->preRequisite());
    }

    public function index(Request $request, DayClosureListService $service)
    {
        // $this->authorize('viewAny', DayClosure::class);

        return $service->paginate($request);
    }

    public function getDateWiseCollection(Request $request, DayClosureService $service)
    {
        // $this->authorize('viewAny', DayClosure::class);

        return $service->getDateWiseCollection($request);
    }

    public function store(DayClosureRequest $request, MarkDayClosureService $service)
    {
        // $this->authorize('create', DayClosure::class);

        $dayClosure = $service->markDayClosure($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('finance.day_closure.day_closure')]),
            'transaction' => DayClosureResource::make($dayClosure),
        ]);
    }

    public function show(string $dayClosure, DayClosureService $service)
    {
        $dayClosure = DayClosure::findByUuidOrFail($dayClosure);

        $dayClosure->load('user');

        // $this->authorize('view', $dayClosure);

        return DayClosureResource::make($dayClosure);
    }

    public function update(DayClosureRequest $request, string $dayClosure, MarkDayClosureService $service)
    {
        $dayClosure = DayClosure::findByUuidOrFail($dayClosure);

        // $this->authorize('update', $dayClosure);

        $service->markDayClosure($request, $dayClosure);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('finance.day_closure.day_closure')]),
        ]);
    }

    public function destroy(string $dayClosure, DayClosureService $service)
    {
        $dayClosure = DayClosure::findByUuidOrFail($dayClosure);

        // $this->authorize('delete', $dayClosure);

        $service->deletable($dayClosure);

        $service->delete($dayClosure);

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('finance.day_closure.day_closure')]),
        ]);
    }
}
