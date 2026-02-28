<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\PushMessageRequest;
use App\Http\Resources\Communication\PushMessageResource;
use App\Models\Tenant\Communication\Communication;
use App\Services\Communication\PushMessageListService;
use App\Services\Communication\PushMessageService;
use Illuminate\Http\Request;

class PushMessageController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
    }

    public function preRequisite(Request $request, PushMessageService $service)
    {
        return $service->preRequisite($request);
    }

    public function index(Request $request, PushMessageListService $service)
    {
        $this->authorize('viewAnyPushMessage', Communication::class);

        return $service->paginate($request);
    }

    public function store(PushMessageRequest $request, PushMessageService $service)
    {
        $this->authorize('sendPushMessage', Communication::class);

        $communication = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('communication.push_message.push_message')]),
            'push_message' => PushMessageResource::make($communication),
        ]);
    }

    public function sendTestNotification(Request $request, PushMessageService $service)
    {
        $this->authorize('sendPushMessage', Communication::class);

        $service->sendTestNotification($request);

        return response()->success([
            'message' => trans('communication.push_message.test_notification_sent'),
        ]);
    }

    public function show(string $communication, PushMessageService $service)
    {
        $communication = Communication::findPushMessageByUuidOrFail($communication);

        $this->authorize('viewPushMessage', $communication);

        $communication->load([
            'audiences.audienceable',
            'media',
        ]);

        return PushMessageResource::make($communication);
    }

    public function downloadMedia(string $communication, string $uuid, PushMessageService $service)
    {
        $communication = Communication::findPushMessageByUuidOrFail($communication);

        $this->authorize('viewPushMessage', $communication);

        return $communication->downloadMedia($uuid);
    }
}
