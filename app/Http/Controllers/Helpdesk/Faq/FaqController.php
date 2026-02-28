<?php

namespace App\Http\Controllers\Helpdesk\Faq;

use App\Http\Controllers\Controller;
use App\Http\Requests\Helpdesk\Faq\FaqRequest;
use App\Http\Resources\Helpdesk\Faq\FaqResource;
use App\Models\Tenant\Helpdesk\Faq\Faq;
use App\Services\Helpdesk\Faq\FaqListService;
use App\Services\Helpdesk\Faq\FaqService;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function preRequisite(FaqService $service)
    {
        return response()->ok($service->preRequisite());
    }

    public function index(Request $request, FaqListService $service)
    {
        $this->authorize('viewAny', Faq::class);

        return $service->paginate($request);
    }

    public function store(FaqRequest $request, FaqService $service)
    {
        $this->authorize('create', Faq::class);

        $faq = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('helpdesk.faq.faq')]),
            'faq' => FaqResource::make($faq),
        ]);
    }

    public function show(string $faq, FaqService $service): FaqResource
    {
        $faq = $service->findByUuidOrFail($faq);

        $faq->load('category', 'tags');

        $this->authorize('view', $faq);

        return FaqResource::make($faq);
    }

    public function update(FaqRequest $request, string $faq, FaqService $service)
    {
        $faq = $service->findByUuidOrFail($faq);

        $this->authorize('update', $faq);

        $service->update($request, $faq);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('helpdesk.faq.faq')]),
        ]);
    }

    public function destroy(string $faq, FaqService $service)
    {
        $faq = $service->findByUuidOrFail($faq);

        $this->authorize('delete', $faq);

        $service->deletable($faq);

        $faq->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('helpdesk.faq.faq')]),
        ]);
    }

    public function destroyMultiple(Request $request, FaqService $service)
    {
        $this->authorize('delete', Faq::class);

        $count = $service->deleteMultiple($request);

        return response()->success([
            'message' => trans('global.multiple_deleted', ['count' => $count, 'attribute' => trans('helpdesk.faq.faq')]),
        ]);
    }
}
