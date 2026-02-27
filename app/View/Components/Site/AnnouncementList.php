<?php

namespace App\View\Components\Site;

use App\Models\Communication\Announcement;
use Illuminate\View\Component;

class AnnouncementList extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(public string $type = 'list')
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        $announcements = Announcement::query()
            ->select('announcements.*', 'teams.name as team_name', 'periods.name as period_name')
            ->with('type')
            ->where('is_public', true)
            ->join('periods', 'announcements.period_id', '=', 'periods.id')
            ->join('teams', 'periods.team_id', '=', 'teams.id')
            ->orderBy('created_at', 'desc')
            ->limit($this->type == 'list' ? 10 : 3)
            ->get()
            ->groupBy('team_name');

        return view()->first(['components.site.custom.announcement-list', 'components.site.default.announcement-list'], compact('announcements'));
    }
}
