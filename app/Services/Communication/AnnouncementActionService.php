<?php

namespace App\Services\Communication;

use App\Models\Tenant\Communication\Announcement;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AnnouncementActionService
{
    public function pin(Request $request, Announcement $announcement): void
    {
        if ($announcement->getMeta('pinned_at')) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        $announcement->setMeta([
            'pinned_at' => now()->toDateTimeString(),
        ]);
        $announcement->save();
    }

    public function unpin(Request $request, Announcement $announcement): void
    {
        if (empty($announcement->getMeta('pinned_at'))) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        $announcement->setMeta([
            'pinned_at' => null,
        ]);
        $announcement->save();
    }

    public function toggleShowAsPopup(Request $request, Announcement $announcement): void
    {
        if ($announcement->getMeta('show_as_popup_in_website')) {
            $announcement->setMeta([
                'show_as_popup_in_website' => false,
            ]);
            $announcement->save();

            return;
        }

        Announcement::query()
            ->byPeriod()
            ->where('id', '!=', $announcement->id)
            ->whereJsonContains('meta->show_as_popup_in_website', true)
            ->update([
                'meta->show_as_popup_in_website' => false,
            ]);

        $announcement->setMeta([
            'show_as_popup_in_website' => true,
        ]);
        $announcement->save();
    }
}
