<?php

namespace App\View\Components\Site;

use App\Models\Calendar\Event;
use Illuminate\View\Component;

class EventList extends Component
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
        $events = Event::query()
            ->select('events.*', 'teams.name as team_name', 'periods.name as period_name')
            ->with('type')
            ->where('is_public', true)
            ->join('periods', 'events.period_id', '=', 'periods.id')
            ->join('teams', 'periods.team_id', '=', 'teams.id')
            ->orderBy('start_date', 'desc')
            ->limit($this->type == 'list' ? 10 : 3)
            ->get()
            ->groupBy('team_name');

        return view()->first(['components.site.custom.event-list', 'components.site.default.event-list'], compact('events'));
    }
}
