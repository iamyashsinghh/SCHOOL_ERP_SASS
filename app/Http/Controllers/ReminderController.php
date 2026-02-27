<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReminderRequest;
use App\Http\Resources\ReminderResource;
use App\Models\Reminder;
use App\Services\ReminderListService;
use App\Services\ReminderService;
use Illuminate\Http\Request;

class ReminderController
{
    public function index(Request $request, ReminderListService $service)
    {
        return $service->paginate($request);
    }

    public function store(ReminderRequest $request, ReminderService $service)
    {
        $reminder = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('reminder.reminder')]),
            'reminder' => ReminderResource::make($reminder),
        ]);
    }

    public function show(Reminder $reminder, ReminderService $service): ReminderResource
    {
        return ReminderResource::make($reminder);
    }

    public function update(ReminderRequest $request, Reminder $reminder, ReminderService $service)
    {
        $service->update($request, $reminder);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('reminder.reminder')]),
        ]);
    }

    public function destroy(Reminder $reminder, ReminderService $service)
    {
        $service->deletable($reminder);

        $reminder->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('reminder.reminder')]),
        ]);
    }
}
