<?php

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\CompetencyRequest;
use App\Http\Resources\Exam\CompetencyResource;
use App\Models\Exam\Competency;
use App\Services\Exam\CompetencyListService;
use App\Services\Exam\CompetencyService;
use Illuminate\Http\Request;

class CompetencyController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
    }

    public function preRequisite(Request $request, CompetencyService $service)
    {
        return response()->ok($service->preRequisite($request));
    }

    public function index(Request $request, CompetencyListService $service)
    {
        return $service->paginate($request);
    }

    public function store(CompetencyRequest $request, CompetencyService $service)
    {
        $competency = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('exam.competency.competency')]),
            'competency' => CompetencyResource::make($competency),
        ]);
    }

    public function show(string $competency, CompetencyService $service)
    {
        $competency = Competency::findByUuidOrFail($competency);

        $competency->load('grade');

        return CompetencyResource::make($competency);
    }

    public function update(CompetencyRequest $request, string $competency, CompetencyService $service)
    {
        $competency = Competency::findByUuidOrFail($competency);

        $service->update($request, $competency);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('exam.competency.competency')]),
        ]);
    }

    public function destroy(string $competency, CompetencyService $service)
    {
        $competency = Competency::findByUuidOrFail($competency);

        $service->deletable($competency);

        $competency->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('exam.competency.competency')]),
        ]);
    }
}
