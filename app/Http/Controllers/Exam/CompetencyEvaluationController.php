<?php

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\CompetencyEvaluationRequest;
use App\Services\Exam\CompetencyEvaluationService;
use Illuminate\Http\Request;

class CompetencyEvaluationController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:exam-schedule:read')->only(['preRequisite', 'fetch']);
        $this->middleware('permission:exam:marks-record')->only(['store', 'remove']);
    }

    public function preRequisite(Request $request, CompetencyEvaluationService $service)
    {
        return response()->ok($service->preRequisite($request));
    }

    public function fetch(Request $request, CompetencyEvaluationService $service)
    {
        return $service->fetch($request);
    }

    public function store(CompetencyEvaluationRequest $request, CompetencyEvaluationService $service)
    {
        $service->store($request);

        return response()->success([
            'message' => trans('global.stored', ['attribute' => trans('exam.competency.evaluation')]),
        ]);
    }

    public function remove(Request $request, CompetencyEvaluationService $service)
    {
        $service->remove($request);

        return response()->success([
            'message' => trans('global.removed', ['attribute' => trans('exam.competency.evaluation')]),
        ]);
    }
}
