<?php

namespace App\View\Components\Site;

use App\Models\Tenant\Communication\Announcement;
use Illuminate\Support\Str;
use Illuminate\View\Component;

class StickyHead extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct() {}

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        $announcementPopup = Announcement::query()
            ->where('is_public', true)
            ->where('meta->show_as_popup_in_website', true)
            ->latest()
            ->first();

        $announcements = Announcement::query()
            ->where('is_public', true)
            ->whereNotNull('meta->excerpt')
            ->where('created_at', '>=', now()->subDays(7)->toDateTimeString())
            ->get();

        $items = $announcements->map(function ($announcement) {
            return [
                'title' => $announcement->getMeta('excerpt'),
                'url' => url('/pages/announcements/'.Str::slug($announcement->title).'/'.$announcement->uuid),
            ];
        })->filter(fn ($item) => ! empty($item['title']))->values()->all();

        $speed = '30s';

        return view()->first(['components.site.custom.sticky-head', 'components.site.default.sticky-head'], compact('items', 'speed', 'announcementPopup'));
    }
}
