<?php

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam\Exam;
use App\Services\Exam\ExamActionService;
use Illuminate\Http\Request;

class ExamActionController extends Controller
{
    public function storeConfig(Request $request, Exam $exam, ExamActionService $service)
    {
        $service->storeConfig($request, $exam);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('exam.exam')]),
        ]);
    }

    public function reorder(Request $request, ExamActionService $service)
    {
        $service->reorder($request);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('exam.exam')]),
        ]);
    }

    public function uploadSignature(Request $request, ExamActionService $service, Exam $exam, string $type)
    {
        $service->uploadSignature($request, $exam, $type);

        return response()->ok();
    }

    public function removeSignature(Request $request, ExamActionService $service, Exam $exam, string $type)
    {
        $service->removeSignature($request, $exam, $type);

        return response()->ok();
    }
}
