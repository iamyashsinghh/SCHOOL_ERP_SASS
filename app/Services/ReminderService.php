<?php

namespace App\Services;

use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReminderService
{
    public function create(Request $request): Reminder
    {
        \DB::beginTransaction();

        $reminder = Reminder::forceCreate($this->formatParams($request));

        $reminder->users()->attach($request->employees->pluck('user_id'));

        \DB::commit();

        return $reminder;
    }

    private function formatParams(Request $request, ?Reminder $reminder = null): array
    {
        $formatted = [
            'title' => $request->title,
            'note' => $request->note,
            'date' => $request->date,
            'notify_before' => $request->notify_before,
            'note' => $request->note,
        ];

        if (is_null($reminder)) {
            $formatted['user_id'] = auth()->id();
        }

        return $formatted;
    }

    public function update(Request $request, Reminder $reminder): void
    {
        if (! $reminder->is_editable) {
            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }

        \DB::beginTransaction();

        $reminder->forceFill($this->formatParams($request, $reminder))->save();

        $reminder->users()->sync($request->employees->pluck('user_id'));

        \DB::commit();
    }

    public function deletable(Reminder $reminder): void
    {
        if (! $reminder->is_editable) {
            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }
    }
}
